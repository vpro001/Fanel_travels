<?php
require 'includes/init.php';
require 'includes/visa.php';
require 'includes/uploads.php';
require 'includes/booking.php';
require_once 'includes/paystack.php';

// Track a visa application with its reference and email.
$ref = strtoupper(get('ref'));
$email = get('email');
$access = isset($_SESSION['visa_access']) ? $_SESSION['visa_access'] : [];

$app = null;
if (preg_match('/^FNV-[A-Z0-9]{8}$/', $ref)) {
    $found = db_one("SELECT a.*, d.country_name, d.slug FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.reference = ?", [$ref]);
    if ($found && (in_array($ref, $access) || strcasecmp($email, $found['email']) == 0)) {
        $app = $found;
        $_SESSION['visa_access'][] = $ref;
    }
}
$not_found = ($ref != '' || $email != '') && !$app;

$can_pay = false;
if ($app) {
    $can_pay = $app['fee_status'] == 'unpaid' && $app['service_fee_kobo'] > 0 && $app['status'] != 'submitted' && visa_is_open($app['status']) && paystack_ready();
}

// Customer uploads documents: one file per checklist item (item[<id>]) plus extras
$upload_error = '';
if ($app && $_SERVER['REQUEST_METHOD'] == 'POST' && post('action') == 'upload') {
    csrf_check();
    if (!visa_is_open($app['status'])) {
        redirect('visa-track.php?ref=' . urlencode($ref));
    }
    $items = [];
    foreach (visa_checklist($app['id']) as $c) $items[$c['id']] = $c;
    $sent = [];
    foreach (list_files('item') as $item_id => $file) {
        if ($file['error'] == UPLOAD_ERR_NO_FILE || !isset($items[$item_id]) || $items[$item_id]['status'] == 'accepted') continue;
        $r = save_upload($file, 'visa');
        if (!$r['ok']) { $upload_error = $items[$item_id]['label'] . ': ' . $r['error']; break; }
        visa_save_document($app['id'], $r, $item_id);
        $sent[] = $items[$item_id]['label'];
    }
    foreach (array_slice(list_files('other'), 0, 5) as $file) {
        if ($upload_error || $file['error'] == UPLOAD_ERR_NO_FILE) continue;
        $r = save_upload($file, 'visa');
        if (!$r['ok']) { $upload_error = $file['name'] . ': ' . $r['error']; break; }
        visa_save_document($app['id'], $r, null, 'customer', 0, 'supporting');
        $sent[] = $r['original_name'];
    }
    if ($sent) {
        visa_customer_uploaded($app['id'], $sent);
    }
    if (!$upload_error && $sent) {
        flash('Thank you. We have your documents.');
        redirect('visa-track.php?ref=' . urlencode($ref) . '#documents');
    } elseif (!$upload_error) {
        $upload_error = 'Please choose at least one file.';
    }
}

// Pay the service fee online
if ($app && $_SERVER['REQUEST_METHOD'] == 'POST' && post('action') == 'pay') {
    csrf_check();
    if ($can_pay) {
        $reference = $app['reference'] . '-' . bin2hex(random_bytes(3));
        db_insert('payments', ['reference' => $reference, 'booking_type' => 'visa', 'booking_id' => $app['id'], 'purpose' => 'full',
            'amount_kobo' => $app['service_fee_kobo'], 'currency' => 'NGN', 'email' => $app['email']]);
        $pay = paystack_initialize($app['email'], $app['service_fee_kobo'], $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $app['reference'], 'booking_type' => 'visa']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        write_log('payments', 'Paystack initialize failed (visa)', ['reference' => $reference, 'message' => $pay['message']]);
        flash("We couldn't open the payment page. Please try again in a moment, or pay at our office.", 'error');
    }
    redirect('visa-track.php?ref=' . urlencode($ref));
}

// Fee not marked paid yet? Ask Paystack once more in case the return page and webhook both missed it.
if ($app && $app['fee_status'] == 'unpaid' && paystack_ready()) {
    $last = db_one("SELECT reference FROM payments WHERE booking_type = 'visa' AND booking_id = ? AND status = 'initialized' AND created_at < NOW() - INTERVAL 30 SECOND ORDER BY id DESC LIMIT 1", [$app['id']]);
    if ($last) {
        $verify = paystack_verify($last['reference']);
        if ($verify['ok'] && in_array($verify['data']['status'], ['success', 'failed', 'abandoned'])) {
            confirm_payment($last['reference'], $verify['data']);
            redirect('visa-track.php?ref=' . urlencode($ref));
        }
    }
}

if ($app) {
    $history = db_all("SELECT * FROM visa_status_history WHERE application_id = ? AND is_public = 1 ORDER BY id DESC", [$app['id']]);
    $checklist = visa_checklist($app['id']);
    $files_by_item = [];
    foreach (db_all("SELECT checklist_id, original_name FROM visa_documents WHERE application_id = ? AND uploaded_by = 'customer' ORDER BY id", [$app['id']]) as $d) {
        $files_by_item[(int) $d['checklist_id']][] = $d['original_name'];
    }
    $shared = db_all("SELECT id, original_name, created_at FROM visa_documents WHERE application_id = ? AND shared = 1 ORDER BY id DESC", [$app['id']]);
    $outstanding = array_filter($checklist, function ($c) { return $c['required'] && in_array($c['status'], ['missing', 'rejected']); });
    $last_payment = db_one("SELECT status FROM payments WHERE booking_type = 'visa' AND booking_id = ? ORDER BY id DESC LIMIT 1", [$app['id']]);
}
$names = visa_status_names();
$steps = ['submitted', 'under_review', 'processing', 'approved', 'completed'];

$title = 'Track your visa application';
$page = 'visa';
include 'includes/header.php';
?>
<section class="bg-navy-900 text-cloud-50">
  <div class="container-page py-10 sm:py-14">
    <p class="eyebrow text-sun-300">Visa tracking</p>
    <h1 class="mt-3 text-4xl font-extrabold tracking-[-0.03em] sm:text-5xl"><?= $app ? e($app['country_name']) . ' visa' : 'Track your application' ?></h1>
    <?php if ($app): ?><p class="mt-2 font-mono text-lg tracking-wider text-sun-300"><?= e($app['reference']) ?></p><?php endif; ?>
  </div>
</section>

<section class="container-page py-10 sm:py-14">
<?php if (!$app): ?>
  <div class="mx-auto max-w-lg">
    <?php if ($not_found): ?>
      <p class="mb-6 rounded-xl bg-bad-100 p-4 text-sm text-bad-700" role="alert">We couldn't find an application with that reference and email. Check both and try again.</p>
    <?php endif; ?>
    <form method="get" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-navy-900/5">
      <p class="text-navy-700">Use the reference from your email (it starts with FNV-) and the email you applied with.</p>
      <div><label class="field-label" for="ref">Reference</label><input id="ref" name="ref" class="field font-mono uppercase tracking-wider" placeholder="FNV-XXXXXXXX" value="<?= e($ref) ?>" required></div>
      <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" class="field" value="<?= e($email) ?>" required></div>
      <button class="btn btn-dark w-full py-3">Track application</button>
    </form>
  </div>
<?php else:
    $current = $app['status'];
    $position = array_search($current == 'documents_requested' ? 'under_review' : ($current == 'refused' ? 'approved' : $current), $steps);
    $item_badge = [
        'missing'  => ['Needed', 'bg-warn-100 text-warn-700'],
        'received' => ['Received, being checked', 'bg-brand-50 text-brand-700'],
        'accepted' => ['Accepted', 'bg-ok-100 text-ok-700'],
        'rejected' => ['Please send again', 'bg-bad-100 text-bad-700'],
    ];
    $file_input = 'mt-2 block w-full rounded-xl bg-white p-2 text-sm ring-1 ring-cloud-300 file:mr-3 file:rounded-full file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white';
?>
  <div class="mx-auto max-w-5xl space-y-6">
    <?php if (get('new')): ?>
      <div class="rounded-2xl bg-ok-100 p-5 text-ok-700" role="status">
        <p class="font-display text-xl font-bold">Application sent</p>
        <p class="mt-1">Keep your reference <b class="font-mono"><?= e($app['reference']) ?></b>. We've emailed it to <?= e($app['email']) ?> and will be in touch within two working days.</p>
      </div>
    <?php endif; ?>

    <!-- Status and progress -->
    <div class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-sm font-semibold text-navy-500">Status</p>
          <p class="mt-1 font-display text-3xl font-bold <?= $current == 'refused' ? 'text-bad-700' : ($current == 'documents_requested' ? 'text-warn-700' : ($current == 'approved' ? 'text-ok-700' : '')) ?>"><?= e($names[$current]) ?></p>
        </div>
        <?php $done_items = count(array_filter($checklist, function ($c) { return $c['status'] == 'accepted'; })); ?>
        <?php if ($checklist): ?>
          <p class="rounded-full bg-cloud-100 px-4 py-2 text-sm font-semibold"><?= $done_items ?> of <?= count($checklist) ?> documents accepted</p>
        <?php endif; ?>
      </div>
      <ol class="mt-6 grid grid-cols-5 gap-2">
        <?php foreach ($steps as $n => $step):
            $done = $n < $position || ($n == $position && $current == 'completed');
            $now = $n == $position && $current != 'completed';
            $label = $step == 'approved' && $current == 'refused' ? 'Refused' : ['submitted' => 'Received', 'under_review' => 'Review', 'processing' => 'Embassy', 'approved' => 'Decision', 'completed' => 'Done'][$step];
        ?>
          <li>
            <div class="h-2 rounded-full <?= $done ? 'bg-ok-700' : ($now ? ($current == 'refused' ? 'bg-bad-700' : 'bg-sun-500') : 'bg-cloud-200') ?>"></div>
            <p class="mt-2 text-xs font-semibold <?= $now ? 'text-navy-900' : 'text-navy-500' ?>"><?= $label ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <?php if ($current == 'approved' || $shared): ?>
      <!-- Results: the visa and anything else we've sent -->
      <div class="rounded-3xl <?= $current == 'approved' ? 'bg-ok-700 text-white' : 'bg-navy-900 text-white' ?> p-6 sm:p-8">
        <?php if ($current == 'approved'): ?>
          <p class="font-display text-2xl font-bold">Your visa has been approved</p>
          <?php if ($app['visa_valid_until']): ?><p class="mt-1">Valid until <b class="num"><?= nice_date($app['visa_valid_until']) ?></b>. Check the dates on your visa before you travel.</p><?php endif; ?>
        <?php else: ?>
          <p class="font-display text-2xl font-bold">Documents from us</p>
        <?php endif; ?>
        <?php if ($shared): ?>
          <ul class="mt-5 grid gap-3 sm:grid-cols-2">
            <?php foreach ($shared as $d): ?>
              <li><a href="visa-file.php?id=<?= $d['id'] ?>" class="flex items-center gap-3 rounded-2xl bg-white/10 p-4 transition hover:bg-white/20">
                <?= icon('document', 'size-6 shrink-0') ?>
                <span class="min-w-0"><b class="block truncate"><?= e($d['original_name']) ?></b><span class="text-xs opacity-80">Added <?= date('j M Y', strtotime($d['created_at'])) ?> · Download</span></span>
              </a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($outstanding && visa_is_open($current)): ?>
      <!-- What we need from the customer -->
      <form method="post" enctype="multipart/form-data" class="rounded-3xl bg-warn-100 p-6 sm:p-8">
        <?= csrf_field() ?><input type="hidden" name="action" value="upload">
        <p class="font-display text-2xl font-bold text-warn-700">We need <?= count($outstanding) == 1 ? 'one document' : count($outstanding) . ' documents' ?> from you</p>
        <p class="mt-1 text-sm text-navy-800">PDF, JPG or PNG, up to 5 MB each. A clear phone photo is fine.</p>
        <?php if ($upload_error): ?><p class="mt-3 rounded-xl bg-white p-3 text-sm text-bad-700" role="alert"><?= e($upload_error) ?></p><?php endif; ?>
        <ul class="mt-5 space-y-3">
          <?php foreach ($outstanding as $c): ?>
            <li class="rounded-2xl bg-white p-4">
              <label class="font-semibold" for="item<?= $c['id'] ?>"><?= e($c['label']) ?></label>
              <?php if ($c['note']): ?><p class="mt-1 text-sm <?= $c['status'] == 'rejected' ? 'text-bad-700' : 'text-navy-600' ?>"><?= $c['status'] == 'rejected' ? 'Why we need it again: ' : '' ?><?= e($c['note']) ?></p><?php endif; ?>
              <input id="item<?= $c['id'] ?>" type="file" name="item[<?= $c['id'] ?>]" accept=".pdf,.jpg,.jpeg,.png" class="<?= $file_input ?>">
            </li>
          <?php endforeach; ?>
        </ul>
        <button class="btn btn-accent mt-5 px-6 py-3">Send documents</button>
      </form>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
      <div class="space-y-6">
        <!-- Full checklist -->
        <div id="documents" class="card scroll-mt-28 p-6">
          <p class="font-display text-xl font-bold">Your documents</p>
          <ul class="mt-4 divide-y divide-cloud-200">
            <?php foreach ($checklist as $c): $b = $item_badge[$c['status']]; ?>
              <li class="flex flex-wrap items-start justify-between gap-2 py-3">
                <div class="min-w-0">
                  <p class="font-semibold"><?= e($c['label']) ?><?= $c['required'] ? '' : ' <span class="text-xs font-normal text-navy-500">(if you have it)</span>' ?></p>
                  <?php if (!empty($files_by_item[$c['id']])): ?><p class="truncate text-xs text-navy-500"><?= e(implode(', ', $files_by_item[$c['id']])) ?></p><?php endif; ?>
                  <?php if ($c['status'] == 'rejected' && $c['note']): ?><p class="text-sm text-bad-700"><?= e($c['note']) ?></p><?php endif; ?>
                </div>
                <span class="badge <?= $b[1] ?>"><?= $c['status'] == 'missing' && !$c['required'] ? 'Optional' : $b[0] ?></span>
              </li>
            <?php endforeach; ?>
            <?php if (!empty($files_by_item[0])): ?>
              <li class="py-3"><p class="font-semibold">Other files you sent</p><p class="text-xs text-navy-500"><?= e(implode(', ', $files_by_item[0])) ?></p></li>
            <?php endif; ?>
          </ul>
          <?php
          $optional_open = array_filter($checklist, function ($c) { return !$c['required'] && $c['status'] == 'missing'; });
          if (visa_is_open($current)): ?>
            <details class="mt-4 rounded-2xl bg-cloud-50 p-4">
              <summary class="font-semibold text-brand-700">Send more documents</summary>
              <form method="post" enctype="multipart/form-data" class="mt-3 space-y-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="upload">
                <?php foreach ($optional_open as $c): ?>
                  <div><label class="text-sm font-semibold" for="opt<?= $c['id'] ?>"><?= e($c['label']) ?></label>
                    <input id="opt<?= $c['id'] ?>" type="file" name="item[<?= $c['id'] ?>]" accept=".pdf,.jpg,.jpeg,.png" class="<?= $file_input ?>"></div>
                <?php endforeach; ?>
                <div><label class="text-sm font-semibold" for="other">Anything else (up to 5 files)</label>
                  <input id="other" type="file" name="other[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="<?= $file_input ?>"></div>
                <button class="btn btn-primary">Upload</button>
              </form>
            </details>
          <?php endif; ?>
        </div>

        <!-- Updates -->
        <div class="card p-6">
          <p class="font-display text-xl font-bold">Updates</p>
          <ol class="mt-4 space-y-5 border-l-2 border-cloud-200 pl-5">
            <?php foreach ($history as $h): ?>
              <li class="relative">
                <span class="absolute -left-[1.62rem] top-1.5 size-3 rounded-full bg-brand-600 ring-4 ring-white"></span>
                <p class="font-semibold"><?= $h['from_status'] == $h['to_status'] ? 'Update' : e($names[$h['to_status']]) ?></p>
                <p class="text-xs text-navy-500 num"><?= date('j M Y, H:i', strtotime($h['created_at'])) ?></p>
                <?php if ($h['note']): ?><p class="mt-1 text-sm text-navy-700"><?= nl2br(e($h['note'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>

      <div class="space-y-6">
        <!-- Service fee -->
        <div class="card p-6">
          <p class="font-display text-xl font-bold">Our service fee</p>
          <p class="mt-2 font-display text-3xl font-bold num"><?= visa_fee_text($app['service_fee_kobo']) ?></p>
          <?php if ($app['fee_status'] == 'paid'): ?>
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-ok-100 px-3 py-1 text-sm font-semibold text-ok-700"><?= icon('check', 'size-4') ?> Paid <?= $app['fee_paid_at'] ? date('j M Y', strtotime($app['fee_paid_at'])) : '' ?></p>
          <?php elseif ($app['fee_status'] == 'waived'): ?>
            <p class="mt-2 text-sm text-navy-600">No service fee for this application.</p>
          <?php elseif ($can_pay): ?>
            <?php if ($last_payment && in_array($last_payment['status'], ['failed', 'abandoned'])): ?><p class="mt-2 text-sm text-bad-700">Your last payment didn't go through. No money was taken.</p><?php endif; ?>
            <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
              <button class="btn btn-accent w-full py-3.5 text-base">Pay <?= naira($app['service_fee_kobo']) ?> now</button>
            </form>
            <p class="mt-2 text-xs text-navy-500">Card, bank transfer or USSD, secured by Paystack. You can also pay at our office.</p>
          <?php elseif ($app['fee_status'] == 'unpaid' && visa_is_open($current)): ?>
            <p class="mt-2 text-sm text-navy-600"><?= $current == 'submitted' ? "Nothing to pay yet. Once we've reviewed your documents you can pay here or at our office." : 'Pay at our office, or call us for payment details.' ?></p>
          <?php endif; ?>
          <p class="mt-3 text-xs text-navy-500">The embassy's own visa fee is separate.</p>
        </div>

        <?php if ($app['appointment_at']): ?>
          <div class="rounded-3xl bg-brand-600 p-6 text-white">
            <p class="text-sm font-semibold text-brand-100">Your appointment</p>
            <p class="mt-1 font-display text-2xl font-bold"><?= date('l j F Y', strtotime($app['appointment_at'])) ?></p>
            <p class="text-lg num"><?= date('g:i A', strtotime($app['appointment_at'])) ?></p>
            <?php if ($app['appointment_place']): ?><p class="mt-2 flex gap-2 text-sm"><?= icon('pin', 'size-5 shrink-0') ?> <?= e($app['appointment_place']) ?></p><?php endif; ?>
            <p class="mt-3 text-xs text-brand-100">Bring your original passport and the documents we listed. Arrive early.</p>
          </div>
        <?php endif; ?>

        <div class="card p-6 text-sm">
          <p class="font-display text-xl font-bold">Your application</p>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-navy-500">Name</dt><dd class="text-right font-medium"><?= e($app['full_name']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-navy-500">Visa</dt><dd class="text-right"><?= e(visa_type_names()[$app['visa_type']] ?? ucfirst($app['visa_type'])) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-navy-500">Service</dt><dd class="text-right"><?= e(visa_service_names()[$app['service']][0]) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-navy-500">Travel date</dt><dd class="num"><?= nice_date($app['travel_date']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-navy-500">Applied</dt><dd class="num"><?= date('j M Y', strtotime($app['created_at'])) ?></dd></div>
          </dl>
        </div>

        <div class="rounded-3xl bg-cloud-100 p-6 text-sm">
          <p class="font-semibold">Questions about your application?</p>
          <p class="mt-1 text-navy-600">Call <a class="link" href="tel:<?= preg_replace('/\D/', '', setting('contact_phone')) ?>"><?= e(contact('phone')) ?></a> and give your reference <b class="font-mono"><?= e($app['reference']) ?></b>, or visit us at <?= e(contact('address')) ?>.</p>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
