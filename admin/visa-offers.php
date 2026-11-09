<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/posters.php';
require '../includes/visa.php';
require '../includes/uploads.php';

// Visa and holiday posts: the flyers shown on the homepage and the Visa page.
$id = (int) get('id');
$edit = $id ? db_one("SELECT * FROM visa_offers WHERE id = ?", [$id]) : null;
$destinations = db_all("SELECT id, country_name, visa_types, is_active FROM visa_destinations ORDER BY sort_order, country_name");
$dest_by_id = array_column($destinations, null, 'id');
$type_names = visa_type_names();
$errors = [];

// Remove an uploaded image's files (the first flyers in assets/img/fanel/ are left alone)
function delete_offer_image($name)
{
    if (strpos($name, 'offers/') === 0) delete_site_image($name);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = post('action');
    $me = $_SESSION['staff']['id'];

    if ($action == 'toggle' || $action == 'delete') {
        $o = db_one("SELECT * FROM visa_offers WHERE id = ?", [(int) post('id')]);
        if ($o && $action == 'toggle') {
            db_query("UPDATE visa_offers SET is_active = 1 - is_active WHERE id = ?", [$o['id']]);
            flash('"' . $o['title'] . '" is now ' . ($o['is_active'] ? 'hidden.' : 'on the site.'));
        } elseif ($o) {
            db_query("DELETE FROM visa_offers WHERE id = ?", [$o['id']]);
            delete_offer_image($o['image']);
            db_insert('audit_log', ['user_id' => $me, 'action' => 'delete', 'entity_type' => 'visa_offer', 'entity_id' => $o['id'], 'details' => json_encode(['title' => $o['title']]), 'ip' => $_SERVER['REMOTE_ADDR']]);
            flash('"' . $o['title'] . '" deleted.');
        }
        redirect('visa-offers.php');
    }

    // Save (new or edit)
    $data = [
        'kind' => post('kind') == 'holiday' ? 'holiday' : 'visa',
        'title' => substr(post('title'), 0, 150),
        'summary' => substr(post('summary'), 0, 500) ?: null,
        'destination_id' => (int) post('destination_id') ?: null,
        'visa_type' => isset($type_names[post('visa_type')]) ? post('visa_type') : null,
        'expires_on' => valid_date(post('expires_on')) ? post('expires_on') : null,
        'sort_order' => (int) post('sort_order'),
        'is_active' => post('is_active') ? 1 : 0,
    ];
    if (strlen($data['title']) < 3) $errors[] = 'Give the post a title, e.g. "Qatar work visa".';
    if ($data['kind'] == 'holiday') {
        $data['destination_id'] = null;
        $data['visa_type'] = null;
    } elseif (post('destination_id') == 'several') {
        // A flyer for several countries: visitors pick one on the Visa page
        $data['destination_id'] = null;
        $data['visa_type'] = null;
    } elseif (!$data['destination_id'] || !isset($dest_by_id[$data['destination_id']])) {
        $errors[] = 'Choose the country people apply for. If it isn\'t in the list, add it in Visa destinations first.';
    } elseif ($data['visa_type'] && !in_array($data['visa_type'], explode(',', $dest_by_id[$data['destination_id']]['visa_types']))) {
        $errors[] = $dest_by_id[$data['destination_id']]['country_name'] . ' is not set up for ' . strtolower($type_names[$data['visa_type']]) . ' visas. Add that type in Visa destinations, or choose another.';
    } elseif (!$dest_by_id[$data['destination_id']]['is_active']) {
        $errors[] = $dest_by_id[$data['destination_id']]['country_name'] . ' is hidden in Visa destinations, so nobody could apply. Show it there first.';
    }
    if (!$id && empty($_FILES['image']['name'])) $errors[] = 'Choose the flyer image.';

    $new_image = null;
    if (!$errors && !empty($_FILES['image']['name'])) {
        list($new_image, $err) = save_site_image($_FILES['image'], 'offers');
        if ($err) $errors[] = $err;
        else $data['image'] = $new_image;
    }

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($data)));
            db_query("UPDATE visa_offers SET $sets WHERE id = ?", array_merge(array_values($data), [$id]));
            if ($new_image) delete_offer_image($edit['image']);
        } else {
            $data['created_by'] = $me;
            $id = db_insert('visa_offers', $data);
        }
        db_insert('audit_log', ['user_id' => $me, 'action' => 'save', 'entity_type' => 'visa_offer', 'entity_id' => $id, 'details' => json_encode(['title' => $data['title']]), 'ip' => $_SERVER['REMOTE_ADDR']]);
        flash('"' . $data['title'] . '" saved.' . ($data['is_active'] ? ' It is on the site now.' : ' It is hidden until you show it.'));
        redirect('visa-offers.php');
    }
    $edit = array_merge($edit ?: [], $data);
}

$all = db_all("SELECT o.*, d.country_name FROM visa_offers o LEFT JOIN visa_destinations d ON d.id = o.destination_id ORDER BY o.kind = 'holiday', o.sort_order, o.id DESC");
$show_form = $edit || get('new') || $errors;
$e = $edit ?: ['kind' => get('kind') == 'holiday' ? 'holiday' : 'visa', 'title' => '', 'summary' => '', 'image' => '', 'destination_id' => null, 'visa_type' => null, 'expires_on' => null, 'sort_order' => 100, 'is_active' => 1];

$title = 'Visa & holiday posts';
$section = 'visa-offers';
include '../includes/admin-header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-3xl font-extrabold">Visa &amp; holiday posts</h1>
  <div class="flex gap-2">
    <a href="visa-offers.php?new=1" class="btn btn-dark">+ New visa post</a>
    <a href="visa-offers.php?new=1&kind=holiday" class="btn btn-ghost">+ New holiday post</a>
  </div>
</div>
<p class="mt-2 max-w-3xl text-sm text-navy-700">Flyers for visa opportunities and holidays. Visa posts show on the homepage and the Visa page, holiday posts on the homepage. The first three (lowest order numbers) also appear at the top of the homepage. Every visa post opens the online application for its country, with the visa type already chosen. Holiday posts link to the holidays page.</p>

<?php if ($show_form): ?>
  <?php if ($errors): ?><div class="mt-5 rounded-xl bg-bad-100 p-4 text-sm text-bad-700"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="visa-offers.php<?= $id ? '?id=' . $id : '' ?>" class="mt-6 grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-navy-900/5 lg:grid-cols-[16rem_1fr]">
    <?= csrf_field() ?>
    <div>
      <?php if ($e['image']): ?>
        <img src="../<?= e(offer_image_url($e['image'], 480)) ?>" alt="" class="w-full rounded-xl ring-1 ring-cloud-200">
      <?php else: ?>
        <div class="flex aspect-square w-full items-center justify-center rounded-xl bg-cloud-100 text-sm text-navy-500">No image yet</div>
      <?php endif; ?>
      <label class="field-label mt-3" for="image"><?= $e['image'] ? 'Replace the image' : 'Flyer image' ?></label>
      <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png" class="mt-1.5 block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">
      <p class="mt-1 text-xs text-navy-600">JPG or PNG, up to 8 MB. Square flyers look best.</p>
    </div>
    <div class="grid content-start gap-4 sm:grid-cols-2">
      <fieldset class="sm:col-span-2"><legend class="field-label">Type</legend>
        <div class="mt-2 flex gap-4 text-sm">
          <label class="flex items-center gap-2"><input type="radio" name="kind" value="visa" class="size-4 accent-brand-600" <?= $e['kind'] == 'visa' ? 'checked' : '' ?>> Visa opportunity</label>
          <label class="flex items-center gap-2"><input type="radio" name="kind" value="holiday" class="size-4 accent-brand-600" <?= $e['kind'] == 'holiday' ? 'checked' : '' ?>> Holiday idea</label>
        </div>
      </fieldset>
      <div class="sm:col-span-2"><label class="field-label" for="title">Title</label><input id="title" name="title" maxlength="150" class="field" value="<?= e($e['title']) ?>" placeholder="e.g. Qatar work visa"></div>
      <div class="sm:col-span-2"><label class="field-label" for="summary">Details <span class="font-normal text-navy-500">(optional, shown when the flyer is opened)</span></label>
        <textarea id="summary" name="summary" rows="3" maxlength="500" class="field" placeholder="e.g. Two-year work permit, processing in about 4 weeks. Open to men and women."><?= e($e['summary']) ?></textarea></div>
      <div><label class="field-label" for="destination_id">People apply for <span class="font-normal text-navy-500">(visa posts)</span></label>
        <select id="destination_id" name="destination_id" class="field">
          <option value="">Choose the country</option>
          <?php foreach ($destinations as $d): ?><option value="<?= $d['id'] ?>" <?= $e['destination_id'] == $d['id'] ? 'selected' : '' ?>><?= e($d['country_name']) ?><?= $d['is_active'] ? '' : ' (hidden)' ?></option><?php endforeach; ?>
          <option value="several" <?= $id && $e['kind'] == 'visa' && !$e['destination_id'] ? 'selected' : '' ?>>Several countries: they choose on the Visa page</option>
        </select>
        <p class="mt-1 text-xs text-navy-600">Country missing? <a class="link" href="visa-destinations.php?new=1">Add it in Visa destinations</a> first.</p></div>
      <div><label class="field-label" for="visa_type">Visa type</label>
        <select id="visa_type" name="visa_type" class="field">
          <option value="">Let them choose</option>
          <?php foreach ($type_names as $k => $l): ?><option value="<?= $k ?>" <?= $e['visa_type'] == $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select></div>
      <div><label class="field-label" for="expires_on">Take down after <span class="font-normal text-navy-500">(optional)</span></label><input id="expires_on" type="date" name="expires_on" class="field" value="<?= e($e['expires_on']) ?>"></div>
      <div><label class="field-label" for="sort_order">Order <span class="font-normal text-navy-500">(lower shows first)</span></label><input id="sort_order" type="number" name="sort_order" class="field" value="<?= (int) $e['sort_order'] ?>"></div>
      <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_active" value="1" class="size-4 accent-brand-600" <?= $e['is_active'] ? 'checked' : '' ?>> Show on the site</label>
      <div class="flex gap-3 sm:col-span-2"><button class="btn btn-dark">Save</button><a class="btn btn-ghost" href="visa-offers.php">Cancel</a></div>
    </div>
  </form>
<?php endif; ?>

<?php foreach (['visa' => 'Visa opportunities', 'holiday' => 'Holiday ideas'] as $kind => $heading):
    $rows = array_filter($all, function ($o) use ($kind) { return $o['kind'] == $kind; }); ?>
  <h2 class="mt-10 font-display text-xl font-bold"><?= $heading ?> <span class="text-base font-normal text-navy-500"><?= count($rows) ?></span></h2>
  <?php if (!$rows): ?><p class="mt-3 text-sm text-navy-600">None yet.</p><?php endif; ?>
  <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
    <?php foreach ($rows as $o):
        $expired = $o['expires_on'] && $o['expires_on'] < date('Y-m-d');
        $badge = !$o['is_active'] ? ['Hidden', 'bg-navy-100 text-navy-700'] : ($expired ? ['Expired', 'bg-bad-100 text-bad-700'] : ['On the site', 'bg-ok-100 text-ok-700']);
    ?>
      <div class="flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-navy-900/5 <?= !$o['is_active'] || $expired ? 'opacity-70' : '' ?>">
        <img src="../<?= e(offer_image_url($o['image'], 480)) ?>" alt="" loading="lazy" class="size-24 shrink-0 rounded-xl object-cover">
        <div class="min-w-0 flex-1">
          <p class="font-semibold leading-snug"><?= e($o['title']) ?></p>
          <p class="mt-0.5 text-xs text-navy-600">
            <?= $o['kind'] == 'holiday' ? 'Links to holidays' : ($o['country_name'] ? 'Application: ' . e($o['country_name']) . ($o['visa_type'] ? ', ' . e(strtolower($type_names[$o['visa_type']] ?? $o['visa_type'])) : '') : 'Several countries') ?> · order <?= (int) $o['sort_order'] ?>
            <?= $o['expires_on'] ? '<br>Until ' . e(nice_date($o['expires_on'])) : '' ?>
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="badge <?= $badge[1] ?>"><?= $badge[0] ?></span>
            <a class="text-sm font-semibold text-brand-700 hover:underline" href="visa-offers.php?id=<?= $o['id'] ?>">Edit</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="text-sm font-semibold text-navy-700 hover:underline"><?= $o['is_active'] ? 'Hide' : 'Show' ?></button></form>
            <form method="post" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="text-sm font-semibold text-bad-700 hover:underline">Delete</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php include '../includes/admin-footer.php'; ?>
