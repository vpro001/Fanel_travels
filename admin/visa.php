<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/visa.php';
require '../includes/uploads.php';

$id = (int) get('id');
$app = db_one("SELECT a.*, d.country_name, d.slug FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.id = ?", [$id]);
if (!$app) {
    redirect('visas.php');
}
$names = visa_status_names();
$me = $_SESSION['staff']['id'];
$back = 'visa.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $note = post('note');
    $action = post('action');

    // ---- Status ----
    if ($action == 'status') {
        $to = post('status');
        if ($to == 'documents_requested' && $note == '' && !visa_outstanding($id)) {
            flash('Say which documents the customer needs to send: reject a document or add one to the checklist, or write it in the note.', 'error');
        } elseif ($to == 'refused' && $note == '') {
            flash('Add a short note for the customer explaining the decision.', 'error');
        } elseif (visa_status($id, $to, $note, post('public') == '1', $me)) {
            if ($to == 'approved' && valid_date(post('valid_until'))) {
                db_query("UPDATE visa_applications SET visa_valid_until = ? WHERE id = ?", [post('valid_until'), $id]);
            }
            flash('Status changed to "' . $names[$to] . '". The customer has been emailed.');
        } else {
            flash('That change is not allowed from the current status.', 'error');
        }

    // ---- Notes and assignment ----
    } elseif ($action == 'note' && $note != '') {
        $public = post('public') == '1';
        visa_note($id, $note, $public, $me, $public ? 'A message from our visa team' : '');
        flash($public ? 'Message sent to the customer.' : 'Note added (staff only).');
    } elseif ($action == 'assign') {
        $staff = (int) post('staff_id');
        $who = db_one("SELECT id, full_name FROM users WHERE id = ? AND role IN ('staff', 'admin') AND is_active = 1", [$staff]);
        db_query("UPDATE visa_applications SET assigned_to = ? WHERE id = ?", [$who ? $who['id'] : null, $id]);
        visa_note($id, $who ? 'Assigned to ' . $who['full_name'] . '.' : 'Unassigned.', false, $me);
        flash($who ? 'Assigned to ' . $who['full_name'] . '.' : 'Unassigned.');

    // ---- Checklist ----
    } elseif (in_array($action, ['accept', 'reject'])) {
        $item = db_one("SELECT * FROM visa_checklist WHERE id = ? AND application_id = ?", [(int) post('item'), $id]);
        if ($item && $action == 'accept') {
            db_query("UPDATE visa_checklist SET status = 'accepted', note = NULL WHERE id = ?", [$item['id']]);
            visa_note($id, 'Accepted: ' . $item['label'] . '.', false, $me);
            flash('"' . $item['label'] . '" accepted.');
        } elseif ($item && $note == '') {
            flash('Write why the document needs to be sent again. The customer sees this.', 'error');
        } elseif ($item && $item['status'] == 'missing') {
            // Never sent: make it required (it stays "missing")
            db_query("UPDATE visa_checklist SET required = 1, note = ? WHERE id = ?", [substr($note, 0, 500), $item['id']]);
            visa_note($id, 'Now required: ' . $item['label'] . ' (' . $note . ').', false, $me);
            flash('"' . $item['label'] . '" is now required. Use "Ask the customer for documents" below to email them.');
        } elseif ($item) {
            db_query("UPDATE visa_checklist SET status = 'rejected', required = 1, note = ? WHERE id = ?", [substr($note, 0, 500), $item['id']]);
            visa_note($id, 'Please send "' . $item['label'] . '" again: ' . $note, false, $me);
            flash('"' . $item['label'] . '" marked to send again. Use "Ask the customer for documents" below to email them.');
        }
    } elseif ($action == 'add_item') {
        $label = trim(post('label'));
        if (strlen($label) < 3) {
            flash('Write the name of the document.', 'error');
        } else {
            $max = db_one("SELECT COALESCE(MAX(sort_order), 0) AS m FROM visa_checklist WHERE application_id = ?", [$id]);
            db_insert('visa_checklist', ['application_id' => $id, 'label' => substr($label, 0, 200), 'required' => 1, 'note' => $note ? substr($note, 0, 500) : null,
                'added_by' => 'staff', 'sort_order' => $max['m'] + 1]);
            visa_note($id, 'Added to the checklist: ' . $label . '.', false, $me);
            flash('"' . $label . '" added. Use "Ask the customer for documents" to email them the list.');
        }
    } elseif ($action == 'request_docs') {
        // Email the customer everything that's outstanding, moving to "documents needed" when allowed
        if (!visa_outstanding($id)) {
            flash('Nothing is outstanding. Reject a document or add one to the checklist first.', 'error');
        } elseif (in_array('documents_requested', visa_next_statuses($app['status']))) {
            visa_status($id, 'documents_requested', $note, true, $me);
            flash('The customer has been emailed the list of documents to send.');
        } else {
            $list = implode(', ', array_column(visa_outstanding($id), 'label'));
            visa_note($id, ($note ? $note . "\n" : '') . 'Please send: ' . $list . '. You can upload them on this page.', true, $me, 'Documents needed');
            flash('The customer has been emailed the list of documents to send.');
        }

    // ---- Fee ----
    } elseif ($action == 'fee_amount' && $app['fee_status'] == 'unpaid') {
        $kobo = (int) round((float) str_replace(',', '', post('fee')) * 100);
        if ($kobo < 0) {
            flash('Enter a valid amount.', 'error');
        } else {
            db_query("UPDATE visa_applications SET service_fee_kobo = ? WHERE id = ? AND fee_status = 'unpaid'", [$kobo, $id]);
            visa_note($id, 'Service fee set to ' . naira($kobo) . '.', post('public') == '1', $me, post('public') == '1' ? 'Service fee' : '');
            flash('Fee set to ' . naira($kobo) . '.');
        }
    } elseif ($action == 'fee_paid') {
        $methods = ['Bank transfer', 'Cash at the office', 'POS at the office'];
        $method = in_array(post('method'), $methods) ? post('method') : 'Bank transfer';
        if ($app['service_fee_kobo'] <= 0) {
            flash('Set the fee amount first.', 'error');
        } elseif (visa_fee_paid($id, $method, $me)) {
            db_insert('audit_log', ['user_id' => $me, 'action' => 'visa_fee_paid', 'entity_type' => 'visa_application', 'entity_id' => $id,
                'details' => json_encode(['method' => $method, 'kobo' => $app['service_fee_kobo']]), 'ip' => $_SERVER['REMOTE_ADDR']]);
            flash('Fee recorded as paid. The customer has been emailed a confirmation.');
        }
    } elseif ($action == 'fee_waive' && $app['fee_status'] == 'unpaid') {
        db_query("UPDATE visa_applications SET fee_status = 'waived' WHERE id = ? AND fee_status = 'unpaid'", [$id]);
        visa_note($id, 'Service fee waived.' . ($note ? ' ' . $note : ''), false, $me);
        flash('Fee waived.');

    // ---- Appointment ----
    } elseif ($action == 'appointment') {
        $when = str_replace('T', ' ', post('appointment_at'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $when) || strtotime($when) === false) {
            flash('Choose the appointment date and time.', 'error');
        } else {
            $place = substr(post('appointment_place'), 0, 200);
            db_query("UPDATE visa_applications SET appointment_at = ?, appointment_place = ? WHERE id = ?", [$when . ':00', $place ?: null, $id]);
            $text = 'Your appointment is on ' . date('l j F Y \a\t g:i A', strtotime($when)) . ($place ? ' at ' . $place : '') . '.'
                . ($note ? "\n" . $note : '') . "\nBring your original passport and the documents we listed.";
            visa_note($id, $text, true, $me, 'Appointment booked');
            flash('Appointment saved. The customer has been emailed.');
        }

    // ---- Files from staff ----
    } elseif ($action == 'staff_file') {
        $r = save_upload(isset($_FILES['file']) ? $_FILES['file'] : [], 'visa');
        if (!$r['ok']) {
            flash($r['error'], 'error');
        } else {
            $shared = post('shared') == '1' ? 1 : 0;
            visa_save_document($id, $r, null, 'staff', $shared, $shared ? 'from_us' : 'internal');
            if ($shared) {
                visa_note($id, 'We have added a document for you: ' . $r['original_name'] . ($note ? "\n" . $note : '') . "\nDownload it from your application page.", true, $me, 'A document from us');
                flash('File shared. The customer has been emailed a link to their application page.');
            } else {
                visa_note($id, 'Staff file added: ' . $r['original_name'] . '.', false, $me);
                flash('File saved (staff only).');
            }
        }
    }
    redirect($back);
}

$checklist = visa_checklist($id);
$docs = db_all("SELECT * FROM visa_documents WHERE application_id = ? ORDER BY id", [$id]);
$docs_by_item = [];
foreach ($docs as $d) $docs_by_item[(int) $d['checklist_id']][] = $d;
$history = db_all("SELECT h.*, u.full_name FROM visa_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.application_id = ? ORDER BY h.id DESC", [$id]);
$staff_list = db_all("SELECT id, full_name FROM users WHERE role IN ('staff', 'admin') AND is_active = 1 ORDER BY full_name");
$payments = db_all("SELECT * FROM payments WHERE booking_type = 'visa' AND booking_id = ? ORDER BY id DESC", [$id]);
$next = visa_next_statuses($app['status']);
$outstanding = visa_outstanding($id);
$days_to_travel = (int) floor((strtotime($app['travel_date']) - strtotime(date('Y-m-d'))) / 86400);
$wa = ltrim($app['phone'], '+');

$title = $app['reference'];
$section = 'visas';
include '../includes/admin-header.php';
$small = 'text-xs font-semibold uppercase tracking-wider text-navy-500';
$item_badge = ['missing' => 'bg-warn-100 text-warn-700', 'received' => 'bg-brand-50 text-brand-700', 'accepted' => 'bg-ok-100 text-ok-700', 'rejected' => 'bg-bad-100 text-bad-700'];
$item_text = ['missing' => 'Missing', 'received' => 'To check', 'accepted' => 'Accepted', 'rejected' => 'Sent back'];
?>
<a href="visas.php" class="text-sm text-navy-600 hover:text-navy-900">← Visa applications</a>
<div class="mt-2 flex flex-wrap items-center gap-3">
  <h1 class="font-mono text-3xl font-bold"><?= e($app['reference']) ?></h1>
  <?= status_badge($app['status']) ?>
  <span class="badge <?= $app['fee_status'] == 'unpaid' ? 'bg-warn-100 text-warn-700' : 'bg-ok-100 text-ok-700' ?>">fee <?= e($app['fee_status']) ?></span>
  <?php if (visa_is_open($app['status']) && $days_to_travel <= 14): ?>
    <span class="badge bg-bad-100 text-bad-700"><?= $days_to_travel < 0 ? 'travel date passed' : 'travels in ' . plural($days_to_travel, 'day') ?></span>
  <?php endif; ?>
</div>
<p class="mt-1 text-navy-700"><?= e($app['country_name']) ?> · <?= e(visa_type_names()[$app['visa_type']] ?? $app['visa_type']) ?> visa · <?= e(visa_service_names()[$app['service']][0]) ?> · applied <?= date('j M Y', strtotime($app['created_at'])) ?></p>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
  <div class="space-y-6">
    <!-- Applicant -->
    <section class="card p-6">
      <dl class="grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="<?= $small ?>">Applicant</dt><dd class="mt-1 font-semibold"><?= e($app['full_name']) ?></dd></div>
        <div><dt class="<?= $small ?>">Passport</dt><dd class="mt-1 font-mono"><?= e($app['passport_number'] ?: '—') ?></dd></div>
        <div class="sm:col-span-2"><dt class="<?= $small ?>">Contact</dt>
          <dd class="mt-2 flex flex-wrap gap-2">
            <a class="btn btn-ghost py-1.5" href="tel:<?= e($app['phone']) ?>"><?= icon('phone', 'size-4') ?> <?= e($app['phone']) ?></a>
            <a class="btn py-1.5 bg-[#12813f] text-white hover:bg-[#0e6b34]" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hello ' . $app['full_name'] . ', this is ' . SITE_NAME . ' about your ' . $app['country_name'] . ' visa application ' . $app['reference'] . '.') ?>" target="_blank" rel="noopener"><?= icon('chat', 'size-4') ?> WhatsApp</a>
            <a class="btn btn-ghost py-1.5" href="mailto:<?= e($app['email']) ?>?subject=<?= rawurlencode('Your visa application ' . $app['reference']) ?>"><?= icon('mail', 'size-4') ?> <?= e($app['email']) ?></a>
          </dd></div>
        <div><dt class="<?= $small ?>">Travel</dt><dd class="mt-1 num"><?= nice_date($app['travel_date']) ?><?= $app['return_date'] ? ' – ' . nice_date($app['return_date']) : '' ?></dd></div>
        <div><dt class="<?= $small ?>">Handled by</dt>
          <dd class="mt-1">
            <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="assign">
              <select name="staff_id" class="field mt-0 py-1.5 text-sm"><option value="0">Nobody yet</option>
                <?php foreach ($staff_list as $st): ?><option value="<?= $st['id'] ?>" <?= $app['assigned_to'] == $st['id'] ? 'selected' : '' ?>><?= e($st['full_name']) ?><?= $st['id'] == $me ? ' (me)' : '' ?></option><?php endforeach; ?>
              </select><button class="btn btn-ghost py-1.5">Save</button></form>
          </dd></div>
        <div class="sm:col-span-2"><dt class="<?= $small ?>">Purpose</dt><dd class="mt-1"><?= e($app['purpose'] ?: '—') ?></dd></div>
      </dl>
    </section>

    <!-- Checklist -->
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-display text-lg font-bold">Document checklist</h2>
        <p class="text-sm text-navy-600"><?= count(array_filter($checklist, function ($c) { return $c['status'] == 'accepted'; })) ?> of <?= count($checklist) ?> accepted · <?= count($outstanding) ?> outstanding</p>
      </div>
      <ul class="mt-4 space-y-3">
        <?php foreach ($checklist as $c): ?>
          <li class="rounded-2xl p-4 ring-1 ring-cloud-200">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <p class="font-semibold"><?= e($c['label']) ?>
                <?php if (!$c['required']): ?><span class="text-xs font-normal text-navy-500">(optional)</span><?php endif; ?>
                <?php if ($c['added_by'] == 'staff'): ?><span class="text-xs font-normal text-navy-500">· added by staff</span><?php endif; ?></p>
              <span class="badge <?= $item_badge[$c['status']] ?>"><?= $item_text[$c['status']] ?></span>
            </div>
            <?php if ($c['note']): ?><p class="mt-1 text-sm <?= $c['status'] == 'rejected' ? 'text-bad-700' : 'text-navy-600' ?>"><?= e($c['note']) ?></p><?php endif; ?>
            <?php foreach (isset($docs_by_item[$c['id']]) ? $docs_by_item[$c['id']] : [] as $d): ?>
              <p class="mt-2 flex items-center gap-2 text-sm"><?= icon('document', 'size-4 shrink-0 text-navy-500') ?>
                <a class="link truncate" href="download.php?id=<?= $d['id'] ?>"><?= e($d['original_name']) ?></a>
                <span class="shrink-0 text-xs text-navy-500"><?= round($d['size_bytes'] / 1024) ?> KB · <?= date('j M H:i', strtotime($d['created_at'])) ?></span></p>
            <?php endforeach; ?>
            <?php if (in_array($c['status'], ['received', 'missing', 'rejected']) && visa_is_open($app['status'])): ?>
              <div class="mt-3 flex flex-wrap items-start gap-2">
                <?php if ($c['status'] == 'received'): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="item" value="<?= $c['id'] ?>"><button class="btn btn-primary py-1.5"><?= icon('check', 'size-4') ?> Accept</button></form>
                <?php endif; ?>
                <?php if ($c['status'] != 'missing' || !$c['required']): ?>
                <details class="min-w-0 flex-1">
                  <summary class="btn btn-ghost py-1.5"><?= $c['status'] == 'missing' ? 'Make required' : 'Send back' ?></summary>
                  <form method="post" class="mt-2 flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="item" value="<?= $c['id'] ?>">
                    <input name="note" class="field mt-0 py-1.5 text-sm" placeholder="Reason the customer sees, e.g. Photo is blurred" required>
                    <button class="btn btn-dark shrink-0 py-1.5">Save</button></form>
                </details>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if (visa_is_open($app['status'])): ?>
        <div class="mt-5 grid gap-4 border-t border-cloud-200 pt-5 lg:grid-cols-2">
          <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="add_item">
            <p class="font-semibold">Add a document to the checklist</p>
            <input name="label" class="field mt-0" placeholder="e.g. Sponsor's bank statement" required>
            <input name="note" class="field mt-0" placeholder="Details for the customer (optional)">
            <button class="btn btn-ghost">Add</button>
          </form>
          <form method="post" class="space-y-2 rounded-2xl bg-warn-100 p-4"><?= csrf_field() ?><input type="hidden" name="action" value="request_docs">
            <p class="font-semibold text-warn-700">Ask the customer for documents</p>
            <p class="text-sm text-navy-700"><?= $outstanding ? 'Emails them: ' . e(implode(', ', array_column($outstanding, 'label'))) . '.' : 'Nothing outstanding right now.' ?></p>
            <textarea name="note" rows="2" class="field mt-0" placeholder="Extra message (optional)"></textarea>
            <button class="btn btn-accent disabled:cursor-not-allowed disabled:opacity-40" <?= $outstanding ? '' : 'disabled' ?>>Email the request</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!empty($docs_by_item[0])): ?>
        <h3 class="mt-6 font-semibold">Other files</h3>
        <ul class="mt-2 divide-y divide-cloud-200 text-sm">
          <?php foreach ($docs_by_item[0] as $d): ?>
            <li class="flex items-center justify-between gap-3 py-2">
              <span class="min-w-0"><a class="link truncate" href="download.php?id=<?= $d['id'] ?>"><?= e($d['original_name']) ?></a>
                <span class="block text-xs text-navy-500">from <?= e($d['uploaded_by']) ?><?= $d['shared'] ? ' · shared with the customer' : ($d['uploaded_by'] == 'staff' ? ' · staff only' : '') ?> · <?= date('j M H:i', strtotime($d['created_at'])) ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- History -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">History</h2>
      <ol class="mt-3 space-y-3 text-sm">
        <?php foreach ($history as $h): ?>
          <li class="rounded-xl p-3 <?= $h['is_public'] ? 'bg-cloud-50' : 'bg-warn-100/60' ?>">
            <p><b><?= $h['from_status'] == $h['to_status'] ? 'Note' : e($names[$h['to_status']]) ?></b>
              <span class="text-xs text-navy-600">· <?= date('j M Y H:i', strtotime($h['created_at'])) ?> · <?= e($h['full_name'] ?: 'customer/system') ?> · <?= $h['is_public'] ? 'customer can see' : 'staff only' ?></span></p>
            <?php if ($h['note']): ?><p class="mt-1 text-navy-800"><?= nl2br(e($h['note'])) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <div class="space-y-6">
    <!-- Status -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">Change status</h2>
      <?php if ($next): ?>
        <form method="post" class="mt-4 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="field mt-0" id="next-status"><?php foreach ($next as $n): ?><option value="<?= $n ?>"><?= $names[$n] ?></option><?php endforeach; ?></select>
          <?php if (in_array('approved', $next)): ?>
            <div><label class="field-label" for="valid_until">If approved: visa valid until</label><input id="valid_until" type="date" name="valid_until" class="field"></div>
          <?php endif; ?>
          <textarea name="note" rows="4" class="field mt-0" placeholder="Message for the customer"></textarea>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="public" value="1" checked class="size-4 accent-brand-600"> Show this note to the customer</label>
          <button class="btn btn-dark w-full">Update and email the customer</button>
        </form>
        <?php if ($app['status'] == 'under_review' && $outstanding): ?><p class="mt-3 text-xs text-warn-700">Some required documents are still outstanding.</p><?php endif; ?>
        <?php if ($app['status'] == 'under_review' && $app['fee_status'] == 'unpaid' && $app['service_fee_kobo'] > 0): ?><p class="mt-1 text-xs text-warn-700">The service fee hasn't been paid yet.</p><?php endif; ?>
      <?php else: ?><p class="mt-3 text-sm text-navy-600">This application is completed.</p><?php endif; ?>
      <?php if ($app['visa_valid_until']): ?><p class="mt-3 text-sm">Visa valid until <b class="num"><?= nice_date($app['visa_valid_until']) ?></b></p><?php endif; ?>
    </section>

    <!-- Fee -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">Service fee</h2>
      <p class="mt-2 font-display text-3xl font-bold num"><?= visa_fee_text($app['service_fee_kobo']) ?></p>
      <?php if ($app['fee_status'] == 'paid'): ?>
        <p class="mt-1 text-sm text-ok-700">Paid <?= $app['fee_paid_at'] ? date('j M Y H:i', strtotime($app['fee_paid_at'])) : '' ?> · <?= e($app['fee_method']) ?></p>
      <?php elseif ($app['fee_status'] == 'waived'): ?>
        <p class="mt-1 text-sm text-navy-600">Waived</p>
      <?php else: ?>
        <p class="mt-1 text-sm text-warn-700">Not paid yet<?= $app['status'] != 'submitted' && $app['service_fee_kobo'] > 0 ? '. The customer can pay online from their tracking page.' : '. The customer can pay online once you move it past "Application received".' ?></p>
        <form method="post" class="mt-4 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="fee_amount">
          <label class="field-label" for="fee">Amount (₦)</label>
          <div class="flex gap-2"><input id="fee" name="fee" inputmode="decimal" class="field mt-0 num" value="<?= $app['service_fee_kobo'] ? number_format($app['service_fee_kobo'] / 100, 0, '.', '') : '' ?>"><button class="btn btn-ghost shrink-0">Set</button></div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="public" value="1" class="size-4 accent-brand-600"> Email the customer the new amount</label>
        </form>
        <form method="post" class="mt-4 space-y-2 border-t border-cloud-200 pt-4"><?= csrf_field() ?><input type="hidden" name="action" value="fee_paid">
          <label class="field-label" for="method">Paid at the office or by transfer</label>
          <div class="flex gap-2"><select id="method" name="method" class="field mt-0"><option>Bank transfer</option><option>Cash at the office</option><option>POS at the office</option></select>
            <button class="btn btn-primary shrink-0" onclick="return confirm('Record <?= e(naira($app['service_fee_kobo'])) ?> as paid?')">Record</button></div>
        </form>
        <details class="mt-3 text-sm"><summary class="cursor-pointer text-navy-600">Waive the fee</summary>
          <form method="post" class="mt-2 flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="fee_waive"><input name="note" class="field mt-0 py-1.5 text-sm" placeholder="Reason (staff only)"><button class="btn btn-ghost shrink-0 py-1.5">Waive</button></form>
        </details>
      <?php endif; ?>
      <?php if ($payments): ?>
        <ul class="mt-4 space-y-1 border-t border-cloud-200 pt-3 text-xs text-navy-600">
          <?php foreach ($payments as $p): ?><li class="flex justify-between gap-2"><span class="font-mono"><?= e($p['reference']) ?></span><span><?= status_badge($p['status']) ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- Appointment -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">Appointment</h2>
      <?php if ($app['appointment_at']): ?><p class="mt-2 text-sm"><b><?= date('D j M Y, g:i A', strtotime($app['appointment_at'])) ?></b><?= $app['appointment_place'] ? '<br>' . e($app['appointment_place']) : '' ?></p><?php endif; ?>
      <form method="post" class="mt-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="appointment">
        <input type="datetime-local" name="appointment_at" class="field mt-0" value="<?= $app['appointment_at'] ? date('Y-m-d\TH:i', strtotime($app['appointment_at'])) : '' ?>" required>
        <input name="appointment_place" class="field mt-0" placeholder="Where, e.g. VFS Global, Lagos" value="<?= e($app['appointment_place']) ?>">
        <textarea name="note" rows="2" class="field mt-0" placeholder="Extra instructions (optional)"></textarea>
        <button class="btn btn-ghost w-full"><?= $app['appointment_at'] ? 'Change and email the customer' : 'Save and email the customer' ?></button>
      </form>
    </section>

    <!-- Files from us -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">Add a file</h2>
      <p class="mt-1 text-sm text-navy-600">The approved visa, an appointment letter, a filled form... PDF, JPG or PNG, up to 5 MB.</p>
      <form method="post" enctype="multipart/form-data" class="mt-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="staff_file">
        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full rounded-xl bg-cloud-50 p-2 text-sm ring-1 ring-cloud-300 file:mr-3 file:rounded-full file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="shared" value="1" checked class="size-4 accent-brand-600"> The customer can download it (and gets an email)</label>
        <input name="note" class="field mt-0" placeholder="Message with the file (optional)">
        <button class="btn btn-ghost w-full">Upload</button>
      </form>
    </section>

    <!-- Notes -->
    <section class="card p-6">
      <h2 class="font-display text-lg font-bold">Note or message</h2>
      <form method="post" class="mt-3 space-y-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="note" rows="3" class="field mt-0" placeholder="A note for the team, or a message to the customer"></textarea>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="public" value="1" class="size-4 accent-brand-600"> Send to the customer (shows on their page and is emailed)</label>
        <button class="btn btn-ghost w-full">Save</button>
      </form>
    </section>
  </div>
</div>
<?php include '../includes/admin-footer.php'; ?>
