<?php
// Visa applications: status rules, document checklists, the service fee and customer emails.
// Status steps (docs/STATE_MACHINES.md):
//   submitted → under_review → documents_requested → processing → approved / refused → completed

require_once __DIR__ . '/mail.php';

function visa_status_names()
{
    return [
        'submitted'           => 'Application received',
        'under_review'        => 'Being reviewed',
        'documents_requested' => 'Documents needed from you',
        'processing'          => 'Submitted to the embassy',
        'approved'            => 'Visa approved',
        'refused'             => 'Visa refused',
        'completed'           => 'Completed',
    ];
}

function visa_type_names()
{
    return [
        'tourist'   => 'Tourist',
        'business'  => 'Business',
        'visit'     => 'Family visit',
        'student'   => 'Student',
        'work'      => 'Work',
        'residence' => 'Residence permit',
        'transit'   => 'Transit',
    ];
}

function visa_service_names()
{
    return [
        'application' => ['Full application', 'We handle everything: document check, forms, appointment and interview preparation.'],
        'support'     => ['Document check only', 'You apply yourself; we review your documents and forms first.'],
        'visit'       => ['Visit visa', 'For visiting family or friends, with help preparing the invitation documents.'],
    ];
}

// Our fee, or a note that it's confirmed later (destinations with no fixed fee have 0)
function visa_fee_text($kobo)
{
    return $kobo > 0 ? naira($kobo) : 'Confirmed after review';
}

// Which statuses each status can move to
function visa_next_statuses($status)
{
    $rules = [
        'submitted'           => ['under_review', 'documents_requested'],
        'under_review'        => ['documents_requested', 'processing', 'refused'],
        'documents_requested' => ['under_review', 'processing'],
        'processing'          => ['approved', 'refused', 'documents_requested'],
        'approved'            => ['completed'],
        'refused'             => ['completed'],
        'completed'           => [],
    ];
    return isset($rules[$status]) ? $rules[$status] : [];
}

// Customers can upload while the application is still open
function visa_is_open($status)
{
    return in_array($status, ['submitted', 'under_review', 'documents_requested', 'processing']);
}

// Move an application to a new status (only if allowed), record it in the history
// and email the customer. $note is shown to the customer when $public is true.
function visa_status($application_id, $new_status, $note = '', $public = true, $staff_id = null)
{
    $app = db_one("SELECT * FROM visa_applications WHERE id = ?", [$application_id]);
    if (!$app || !in_array($new_status, visa_next_statuses($app['status']))) {
        return false;
    }
    $stmt = db_query("UPDATE visa_applications SET status = ? WHERE id = ? AND status = ?", [$new_status, $application_id, $app['status']]);
    if (mysqli_stmt_affected_rows($stmt) != 1) {
        return false;   // someone else changed it at the same moment
    }
    db_insert('visa_status_history', [
        'application_id' => $application_id,
        'from_status' => $app['status'],
        'to_status' => $new_status,
        'note' => $note,
        'is_public' => $public ? 1 : 0,
        'changed_by' => $staff_id,
    ]);
    visa_email($application_id, $new_status, $public ? $note : '');
    return true;
}

// An update that doesn't change the status (appointment booked, fee paid, document checked...).
// Public notes show on the tracking page and, with $email, are emailed to the customer.
function visa_note($application_id, $note, $public = false, $staff_id = null, $email_subject = '')
{
    $app = db_one("SELECT status FROM visa_applications WHERE id = ?", [$application_id]);
    db_insert('visa_status_history', [
        'application_id' => $application_id,
        'from_status' => $app['status'],
        'to_status' => $app['status'],
        'note' => $note,
        'is_public' => $public ? 1 : 0,
        'changed_by' => $staff_id,
    ]);
    if ($public && $email_subject != '') {
        visa_message($application_id, $email_subject, $note);
    }
}

// ------------------------------------------------------------ checklist

// The documents a destination asks for: [['label' => ..., 'required' => true/false], ...]
// Lines starting with * must come with the application.
function visa_checklist_lines($destination)
{
    $text = trim((string) $destination['checklist']);
    if ($text == '') {
        $text = "*Passport photo (bio-data) page\n*Passport photograph\nSupporting documents";
    }
    $lines = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line == '' || $line == '*') continue;
        $required = $line[0] == '*';
        $lines[] = ['label' => substr(trim(ltrim($line, '*')), 0, 200), 'required' => $required];
    }
    return $lines;
}

function visa_checklist($application_id)
{
    return db_all("SELECT * FROM visa_checklist WHERE application_id = ? ORDER BY sort_order, id", [$application_id]);
}

// Checklist items still waiting on the customer: required ones that are missing or were rejected
function visa_outstanding($application_id)
{
    return db_all("SELECT * FROM visa_checklist WHERE application_id = ? AND required = 1 AND status IN ('missing', 'rejected') ORDER BY sort_order, id", [$application_id]);
}

// Save one uploaded file against an application (and a checklist item, if given)
function visa_save_document($application_id, $upload, $checklist_id = null, $by = 'customer', $shared = 0, $doc_type = 'checklist')
{
    db_insert('visa_documents', [
        'application_id' => $application_id,
        'checklist_id' => $checklist_id,
        'doc_type' => $doc_type,
        'original_name' => $upload['original_name'],
        'stored_name' => $upload['stored_name'],
        'mime' => $upload['mime'],
        'size_bytes' => $upload['size_bytes'],
        'sha256' => $upload['sha256'],
        'uploaded_by' => $by,
        'shared' => $shared,
    ]);
    if ($checklist_id) {
        db_query("UPDATE visa_checklist SET status = 'received', note = NULL WHERE id = ? AND application_id = ?", [$checklist_id, $application_id]);
    }
}

// After the customer uploads: once nothing required is outstanding, hand it back to staff
function visa_customer_uploaded($application_id, $file_names)
{
    $app = db_one("SELECT status FROM visa_applications WHERE id = ?", [$application_id]);
    $note = 'You sent: ' . implode(', ', $file_names) . '.';
    if ($app['status'] == 'documents_requested' && !visa_outstanding($application_id)) {
        visa_status($application_id, 'under_review', $note . ' We are reviewing them.');
    } else {
        visa_note($application_id, $note, true);
    }
    admin_visa_alert($application_id, 'New documents uploaded', $note);
}

// ------------------------------------------------------------ service fee

// Mark the fee as paid (Paystack, or recorded by staff for cash / transfer / POS). Runs once.
function visa_fee_paid($application_id, $method, $staff_id = null)
{
    $stmt = db_query("UPDATE visa_applications SET fee_status = 'paid', fee_paid_at = NOW(), fee_method = ? WHERE id = ? AND fee_status = 'unpaid'", [$method, $application_id]);
    if (mysqli_stmt_affected_rows($stmt) != 1) {
        return false;
    }
    $app = db_one("SELECT service_fee_kobo FROM visa_applications WHERE id = ?", [$application_id]);
    visa_note($application_id, 'We have received your service fee of ' . naira($app['service_fee_kobo']) . ' (' . $method . '). Thank you.', true, $staff_id, 'Payment received');
    return true;
}

// ------------------------------------------------------------ emails

function visa_email_html($app, $message_html)
{
    $link = SITE_URL . '/visa-track.php?ref=' . urlencode($app['reference']) . '&email=' . urlencode($app['email']);
    return '<div style="font-family:Arial,sans-serif;color:#0a1f44;max-width:600px;line-height:1.5">'
        . '<p>Hello ' . e($app['full_name']) . ',</p>'
        . $message_html
        . '<p><a href="' . e($link) . '" style="display:inline-block;background:#1d55d0;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none">See your application</a></p>'
        . '<p>' . e(SITE_NAME) . '<br>' . e(contact('phone')) . ' · ' . e(contact('address')) . '</p></div>';
}

function visa_email($application_id, $status, $note = '')
{
    $app = db_one("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.id = ?", [$application_id]);
    $names = visa_status_names();
    $html = '<p>Your ' . e($app['country_name']) . ' visa application <b>' . e($app['reference']) . '</b> is now: <b>' . e($names[$status]) . '</b>.</p>'
        . ($note ? '<p>' . nl2br(e($note)) . '</p>' : '');
    if ($status == 'documents_requested') {
        $items = visa_outstanding($application_id);
        if ($items) {
            $html .= '<p>Please send:</p><ul>';
            foreach ($items as $i) {
                $html .= '<li><b>' . e($i['label']) . '</b>' . ($i['note'] ? ': ' . e($i['note']) : '') . '</li>';
            }
            $html .= '</ul><p>You can upload them on your application page.</p>';
        }
    }
    send_mail($app['email'], 'Visa application ' . $app['reference'] . ': ' . $names[$status], visa_email_html($app, $html));
}

// Any other message to the customer
function visa_message($application_id, $subject, $text)
{
    $app = db_one("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.id = ?", [$application_id]);
    $html = '<p>An update on your ' . e($app['country_name']) . ' visa application <b>' . e($app['reference']) . '</b>:</p><p>' . nl2br(e($text)) . '</p>';
    send_mail($app['email'], 'Visa application ' . $app['reference'] . ': ' . $subject, visa_email_html($app, $html));
}

// Tell the office when a customer does something (uploads, pays)
function admin_visa_alert($application_id, $subject, $text)
{
    if (!defined('ADMIN_EMAIL') || ADMIN_EMAIL == '') return;
    $app = db_one("SELECT reference, full_name FROM visa_applications WHERE id = ?", [$application_id]);
    send_mail(ADMIN_EMAIL, 'Visa ' . $app['reference'] . ': ' . $subject,
        '<p>' . e($app['full_name']) . ' (' . e($app['reference']) . '): ' . e($text) . '</p><p><a href="' . e(SITE_URL . '/admin/visa.php?id=' . $application_id) . '">Open in the back office</a></p>');
}
