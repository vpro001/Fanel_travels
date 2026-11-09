<?php
require 'includes/init.php';
require 'includes/visa.php';
require 'includes/uploads.php';

$country = db_one("SELECT * FROM visa_destinations WHERE slug = ? AND is_active = 1", [get('country')]);
if (!$country) {
    redirect('visa.php');
}

$services = visa_service_names();
$type_names = visa_type_names();
$types = array_intersect_key($type_names, array_flip(explode(',', $country['visa_types'])));
$checklist = visa_checklist_lines($country);

$f = ['service' => 'application', 'visa_type' => key($types), 'full_name' => '', 'email' => '', 'phone' => '', 'passport_number' => '', 'travel_date' => '', 'return_date' => '', 'purpose' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    foreach ($f as $key => $v) {
        $f[$key] = post($key);
    }
    if (!rate_limit('visa-apply:' . $_SERVER['REMOTE_ADDR'], 5, 3600)) $errors['form'] = 'You have sent several applications in the last hour. Please call us if you need help.';
    if (!isset($services[$f['service']])) $errors['service'] = 'Choose a service.';
    if (!isset($types[$f['visa_type']])) $errors['visa_type'] = 'Choose a visa type.';
    if (strlen($f['full_name']) < 3) $errors['full_name'] = 'Enter your full name as it is on your passport.';
    if (!valid_email($f['email'])) $errors['email'] = 'Enter your email address. We send updates there.';
    if (clean_phone($f['phone']) == '') $errors['phone'] = 'Enter your phone number, e.g. 0803 123 4567.';
    if ($f['passport_number'] != '' && !preg_match('/^[A-Za-z0-9]{5,20}$/', $f['passport_number'])) $errors['passport_number'] = 'Check the passport number.';
    if (!valid_date($f['travel_date']) || $f['travel_date'] <= date('Y-m-d')) $errors['travel_date'] = 'Enter the date you plan to travel.';
    if ($f['return_date'] != '' && (!valid_date($f['return_date']) || $f['return_date'] < $f['travel_date'])) $errors['return_date'] = 'The return date must be after the travel date.';

    // One upload per checklist item; the ones marked required must be there
    $files = list_files('doc');
    foreach ($checklist as $n => $item) {
        $given = isset($files[$n]) && $files[$n]['error'] != UPLOAD_ERR_NO_FILE;
        if ($item['required'] && !$given) $errors["doc$n"] = 'Please upload this document.';
    }

    $saved = [];   // [checklist index or null, upload result]
    if (!$errors) {
        foreach ($checklist as $n => $item) {
            if (!isset($files[$n]) || $files[$n]['error'] == UPLOAD_ERR_NO_FILE) continue;
            $r = save_upload($files[$n], 'visa');
            if (!$r['ok']) { $errors["doc$n"] = $r['error']; break; }
            $saved[] = [$n, $r];
        }
        foreach (array_slice(list_files('other'), 0, 5) as $file) {
            if ($errors || $file['error'] == UPLOAD_ERR_NO_FILE) continue;
            $r = save_upload($file, 'visa');
            if (!$r['ok']) { $errors['other'] = $file['name'] . ': ' . $r['error']; break; }
            $saved[] = [null, $r];
        }
        if ($errors) {
            // Something failed: don't keep half an application's files
            foreach ($saved as $s) @unlink(__DIR__ . '/storage/uploads/visa/' . $s[1]['stored_name']);
        }
    }

    if (!$errors) {
        $reference = make_reference('FNV');
        db_begin();
        $id = db_insert('visa_applications', [
            'reference' => $reference,
            'user_id' => user_id_or_null(),
            'destination_id' => $country['id'],
            'visa_type' => $f['visa_type'],
            'service' => $f['service'],
            'full_name' => substr($f['full_name'], 0, 120),
            'email' => $f['email'],
            'phone' => clean_phone($f['phone']),
            'passport_number' => $f['passport_number'] ? strtoupper($f['passport_number']) : null,
            'travel_date' => $f['travel_date'],
            'return_date' => $f['return_date'] ?: null,
            'purpose' => substr($f['purpose'], 0, 500),
            'service_fee_kobo' => $country['service_fee_kobo'],
        ]);
        // This application's own copy of the checklist
        $item_ids = [];
        foreach ($checklist as $n => $item) {
            $item_ids[$n] = db_insert('visa_checklist', ['application_id' => $id, 'label' => $item['label'], 'required' => $item['required'] ? 1 : 0, 'sort_order' => $n]);
        }
        foreach ($saved as $s) {
            visa_save_document($id, $s[1], $s[0] === null ? null : $item_ids[$s[0]], 'customer', 0, $s[0] === null ? 'supporting' : 'checklist');
        }
        db_insert('visa_status_history', ['application_id' => $id, 'to_status' => 'submitted', 'note' => 'We have your application and documents.', 'is_public' => 1]);
        db_commit();

        visa_email($id, 'submitted', "We'll review your documents and contact you within two working days with the next steps"
            . ($country['service_fee_kobo'] > 0 ? ' and how to pay our service fee.' : ', including our service fee.'));
        admin_visa_alert($id, 'New application', $country['country_name'] . ' visa, travelling ' . nice_date($f['travel_date']));
        $_SESSION['visa_access'][] = $reference;
        redirect('visa-track.php?ref=' . urlencode($reference) . '&new=1');
    }
}

$title = 'Apply: ' . $country['country_name'] . ' visa';
$page = 'visa';
include 'includes/header.php';

$field = 'field';
$file_input = 'mt-2 block w-full rounded-xl bg-cloud-50 p-2 text-sm ring-1 ring-cloud-300 file:mr-3 file:rounded-full file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-700';
$label = 'block font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-navy-600';
function show_error($errors, $key) { return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : ''; }
?>
<section class="bg-navy-950 text-cloud-50">
  <div class="container-page py-10 sm:py-14">
    <a href="visa.php?country=<?= e($country['slug']) ?>" class="text-xs uppercase tracking-wider text-navy-300 hover:text-sun-300 font-semibold">← <?= e($country['country_name']) ?> requirements</a>
    <h1 class="mt-3 text-4xl font-extrabold tracking-[-0.03em] sm:text-5xl"><?= e($country['country_name']) ?> visa application</h1>
    <p class="mt-2 text-navy-200">Takes about 5 minutes. You'll get a reference number to track every step.</p>
  </div>
</section>

<section class="container-page py-10 sm:py-14">
  <form method="post" enctype="multipart/form-data" novalidate class="mx-auto max-w-3xl space-y-6">
    <?= csrf_field() ?>
    <?php if ($errors): ?>
      <div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert"><p class="font-semibold"><?= isset($errors['form']) ? e($errors['form']) : 'Please check the highlighted details.' ?></p></div>
    <?php endif; ?>

    <fieldset class="card p-5 sm:p-6">
      <legend class="sr-only">Service</legend>
      <p class="font-display text-xl font-bold">1. How can we help?</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <?php foreach ($services as $key => $s): ?>
          <label class="relative">
            <input type="radio" name="service" value="<?= $key ?>" class="peer sr-only" <?= $f['service'] == $key ? 'checked' : '' ?>>
            <span class="block h-full rounded-2xl p-4 ring-1 ring-cloud-300 transition peer-checked:bg-brand-600 peer-checked:text-white peer-checked:ring-brand-600 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-brand-500">
              <b class="block"><?= $s[0] ?></b><span class="mt-1 block text-sm opacity-80"><?= $s[1] ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 sm:w-64">
        <label class="<?= $label ?>" for="visa_type">Visa type</label>
        <select id="visa_type" name="visa_type" class="<?= $field ?>">
          <?php foreach ($types as $key => $name): ?><option value="<?= $key ?>" <?= $f['visa_type'] == $key ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select>
        <?= show_error($errors, 'visa_type') ?>
      </div>
    </fieldset>

    <fieldset class="card p-5 sm:p-6">
      <legend class="sr-only">Your details</legend>
      <p class="font-display text-xl font-bold">2. About you and your trip</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="full_name">Full name (as on your passport)</label>
          <input id="full_name" name="full_name" class="<?= $field ?>" autocomplete="name" value="<?= e($f['full_name']) ?>">
          <?= show_error($errors, 'full_name') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="email">Email</label>
          <input id="email" type="email" name="email" class="<?= $field ?>" autocomplete="email" value="<?= e($f['email']) ?>">
          <?= show_error($errors, 'email') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="phone">Mobile number</label>
          <input id="phone" type="tel" name="phone" class="<?= $field ?> num" autocomplete="tel" placeholder="0803 123 4567" value="<?= e($f['phone']) ?>">
          <?= show_error($errors, 'phone') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="passport_number">Passport number <span class="normal-case tracking-normal font-normal">(optional)</span></label>
          <input id="passport_number" name="passport_number" class="<?= $field ?> uppercase" value="<?= e($f['passport_number']) ?>">
          <?= show_error($errors, 'passport_number') ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="<?= $label ?>" for="travel_date">Travel date</label>
            <input id="travel_date" type="date" name="travel_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="<?= $field ?> num" value="<?= e($f['travel_date']) ?>">
            <?= show_error($errors, 'travel_date') ?>
          </div>
          <div>
            <label class="<?= $label ?>" for="return_date">Return</label>
            <input id="return_date" type="date" name="return_date" class="<?= $field ?> num" value="<?= e($f['return_date']) ?>">
            <?= show_error($errors, 'return_date') ?>
          </div>
        </div>
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="purpose">Purpose of the trip</label>
          <textarea id="purpose" name="purpose" rows="3" class="<?= $field ?>" placeholder="e.g. Visiting my sister in Manchester for her graduation"><?= e($f['purpose']) ?></textarea>
        </div>
      </div>
    </fieldset>

    <fieldset class="card p-5 sm:p-6">
      <legend class="sr-only">Documents</legend>
      <p class="font-display text-xl font-bold">3. Documents</p>
      <p class="mt-1 text-sm text-navy-600">PDF, JPG or PNG, up to 5 MB each. A clear phone photo is fine. Only our visa staff can see these files.
        Items marked <span class="font-semibold text-bad-700">Required</span> must come with the application; you can send the rest later from your tracking page.</p>
      <ul class="mt-5 divide-y divide-cloud-200 rounded-2xl ring-1 ring-cloud-200">
        <?php foreach ($checklist as $n => $item): ?>
          <li class="p-4 <?= isset($errors["doc$n"]) ? 'bg-bad-100/50' : '' ?>">
            <label class="flex flex-wrap items-center gap-2 font-semibold" for="doc<?= $n ?>">
              <?= icon('document', 'size-5 text-brand-600') ?> <?= e($item['label']) ?>
              <?php if ($item['required']): ?><span class="badge bg-bad-100 text-bad-700">Required</span><?php else: ?><span class="badge bg-cloud-100 text-navy-600">Now or later</span><?php endif; ?>
            </label>
            <input id="doc<?= $n ?>" type="file" name="doc[<?= $n ?>]" accept=".pdf,.jpg,.jpeg,.png" class="<?= $file_input ?>">
            <?= show_error($errors, "doc$n") ?>
          </li>
        <?php endforeach; ?>
        <li class="p-4">
          <label class="font-semibold" for="other">Anything else <span class="font-normal text-navy-500">(optional, up to 5 files: letters, previous visas...)</span></label>
          <input id="other" type="file" name="other[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="<?= $file_input ?>">
          <?= show_error($errors, 'other') ?>
        </li>
      </ul>
    </fieldset>

    <div class="rounded-3xl bg-navy-900 p-5 text-cloud-50 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:p-6">
      <div>
        <p class="text-[11px] uppercase tracking-wider text-navy-300 font-semibold">Our service fee</p>
        <p class="font-display text-2xl font-bold num"><?= visa_fee_text($country['service_fee_kobo']) ?></p>
        <p class="mt-1 text-xs text-navy-300">Nothing to pay now. After we review your documents you can pay online by card, transfer or USSD, or at our office.</p>
      </div>
      <button class="btn btn-accent mt-4 w-full px-7 py-4 text-base sm:mt-0 sm:w-auto">Send application</button>
    </div>
  </form>
</section>
<?php include 'includes/footer.php'; ?>
