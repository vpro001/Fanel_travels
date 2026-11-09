<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/visa.php';

$names = visa_status_names();
$status = get('status');
$q = trim(get('q'));
$dest = (int) get('destination');
$view = get('view');   // mine, unassigned, unpaid, soon, to_check
$me = $_SESSION['staff']['id'];

$where = [];
$params = [];
if (isset($names[$status])) {
    $where[] = 'a.status = ?';
    $params[] = $status;
} elseif ($status == '') {
    $where[] = "a.status NOT IN ('completed')";   // finished ones only under their own filter
}
if ($q != '') {
    $where[] = '(a.reference LIKE ? OR a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR a.passport_number LIKE ?)';
    $like = '%' . $q . '%';
    // Phones are saved as +234803...; drop a leading 0 or 234 so "0803 123" finds them too
    $digits = preg_replace('/^(234|0)/', '', preg_replace('/\D/', '', $q));
    array_push($params, $like, $like, $like, strlen($digits) >= 5 ? '%' . $digits . '%' : $like, $like);
}
if ($dest) {
    $where[] = 'a.destination_id = ?';
    $params[] = $dest;
}
$open = "a.status IN ('submitted', 'under_review', 'documents_requested', 'processing')";
if ($view == 'mine') { $where[] = 'a.assigned_to = ?'; $params[] = $me; }
if ($view == 'unassigned') { $where[] = "a.assigned_to IS NULL AND $open"; }
if ($view == 'unpaid') { $where[] = "a.fee_status = 'unpaid' AND $open"; }
if ($view == 'soon') { $where[] = "a.travel_date <= CURDATE() + INTERVAL 14 DAY AND $open"; }
if ($view == 'to_check') { $where[] = "EXISTS (SELECT 1 FROM visa_checklist c WHERE c.application_id = a.id AND c.status = 'received') AND $open"; }

$sql = "SELECT a.*, d.country_name, u.full_name AS staff_name,
          (SELECT COUNT(*) FROM visa_checklist c WHERE c.application_id = a.id) AS items,
          (SELECT COUNT(*) FROM visa_checklist c WHERE c.application_id = a.id AND c.status = 'accepted') AS accepted,
          (SELECT COUNT(*) FROM visa_checklist c WHERE c.application_id = a.id AND c.status = 'received') AS to_check,
          (SELECT COUNT(*) FROM visa_checklist c WHERE c.application_id = a.id AND c.required = 1 AND c.status IN ('missing', 'rejected')) AS outstanding
        FROM visa_applications a
        JOIN visa_destinations d ON d.id = a.destination_id
        LEFT JOIN users u ON u.id = a.assigned_to"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY FIELD(a.status, 'processing', 'documents_requested', 'under_review', 'submitted') DESC, a.travel_date ASC, a.id DESC LIMIT 300";
$apps = db_all($sql, $params);

$counts = [];
foreach (db_all("SELECT status, COUNT(*) AS n FROM visa_applications GROUP BY status") as $r) $counts[$r['status']] = $r['n'];
$view_counts = db_one("SELECT
    SUM(assigned_to = ? AND $open) AS mine,
    SUM(assigned_to IS NULL AND $open) AS unassigned,
    SUM(fee_status = 'unpaid' AND $open) AS unpaid,
    SUM(travel_date <= CURDATE() + INTERVAL 14 DAY AND $open) AS soon,
    SUM(EXISTS (SELECT 1 FROM visa_checklist c WHERE c.application_id = a.id AND c.status = 'received') AND $open) AS to_check
  FROM visa_applications a", [$me]);
$destinations = db_all("SELECT id, country_name FROM visa_destinations ORDER BY sort_order, country_name");

// Keep the other filters when one changes
function visas_link($change)
{
    $p = array_merge(['status' => get('status'), 'q' => get('q'), 'destination' => get('destination'), 'view' => get('view')], $change);
    return 'visas.php?' . http_build_query(array_filter($p, function ($v) { return $v !== '' && $v !== null && $v !== 0; }));
}

$title = 'Visa applications';
$section = 'visas';
include '../includes/admin-header.php';
$chip = 'rounded-full px-3 py-1.5 ring-1 transition';
$on = 'bg-navy-900 text-white ring-navy-900';
$off = 'bg-white ring-cloud-300 hover:bg-cloud-100';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
  <h1 class="text-3xl font-extrabold">Visa applications</h1>
  <form method="get" class="flex flex-wrap gap-2">
    <?php foreach (['status', 'view'] as $k): if (get($k) != ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e(get($k)) ?>"><?php endif; endforeach; ?>
    <input name="q" value="<?= e($q) ?>" class="field mt-0 w-64 py-2" placeholder="Reference, name, email, phone, passport">
    <select name="destination" class="field mt-0 w-48 py-2" onchange="this.form.submit()">
      <option value="">All countries</option>
      <?php foreach ($destinations as $d): ?><option value="<?= $d['id'] ?>" <?= $dest == $d['id'] ? 'selected' : '' ?>><?= e($d['country_name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-dark">Search</button>
  </form>
</div>

<!-- Work queues -->
<div class="mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
  <?php foreach ([
      'to_check'   => ['Documents to check', 'Customer uploaded, waiting for us'],
      'soon'       => ['Travelling within 14 days', 'Still open'],
      'unpaid'     => ['Fee not paid', 'Open applications'],
      'unassigned' => ['Nobody handling', 'Open applications'],
      'mine'       => ['Assigned to me', 'Open applications'],
  ] as $k => $v): $n = (int) $view_counts[$k]; ?>
    <a href="<?= e(visas_link(['view' => $view == $k ? '' : $k])) ?>" class="rounded-2xl p-4 ring-1 transition <?= $view == $k ? 'bg-navy-900 text-white ring-navy-900' : 'bg-white ring-navy-900/5 hover:ring-navy-300' ?>">
      <p class="font-display text-3xl font-bold <?= $n && $view != $k && in_array($k, ['to_check', 'soon']) ? 'text-sun-600' : '' ?>"><?= $n ?></p>
      <p class="text-sm font-semibold"><?= $v[0] ?></p>
      <p class="text-xs <?= $view == $k ? 'text-navy-200' : 'text-navy-500' ?>"><?= $v[1] ?></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="mt-5 flex flex-wrap gap-2 text-sm">
  <a href="<?= e(visas_link(['status' => ''])) ?>" class="<?= $chip ?> <?= $status == '' ? $on : $off ?>">All open</a>
  <?php foreach ($names as $k => $n): ?>
    <a href="<?= e(visas_link(['status' => $k])) ?>" class="<?= $chip ?> <?= $status == $k ? $on : $off ?>"><?= $n ?> <span class="opacity-60"><?= isset($counts[$k]) ? $counts[$k] : 0 ?></span></a>
  <?php endforeach; ?>
  <?php if ($q || $dest || $view): ?><a href="visas.php" class="<?= $chip ?> <?= $off ?> text-bad-700">Clear filters</a><?php endif; ?>
</div>

<div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-navy-900/5">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-cloud-200 text-xs text-navy-600">
      <tr><th class="px-5 py-3 font-medium">Reference</th><th class="px-3 py-3 font-medium">Applicant</th><th class="px-3 py-3 font-medium">Destination</th><th class="px-3 py-3 font-medium">Travel</th><th class="px-3 py-3 font-medium">Documents</th><th class="px-3 py-3 font-medium">Fee</th><th class="px-3 py-3 font-medium">Handled by</th><th class="px-5 py-3 font-medium">Status</th></tr>
    </thead>
    <tbody class="divide-y divide-cloud-200">
      <?php foreach ($apps as $a):
          $days = (int) floor((strtotime($a['travel_date']) - strtotime(date('Y-m-d'))) / 86400);
          $urgent = visa_is_open($a['status']) && $days <= 14;
      ?>
        <tr class="hover:bg-cloud-50">
          <td class="px-5 py-3"><a class="font-mono font-semibold text-brand-700 hover:underline" href="visa.php?id=<?= $a['id'] ?>"><?= e($a['reference']) ?></a><br><span class="text-xs text-navy-600"><?= date('j M Y', strtotime($a['created_at'])) ?></span></td>
          <td class="px-3 py-3"><?= e($a['full_name']) ?><br><span class="text-xs text-navy-600"><?= e($a['phone']) ?></span></td>
          <td class="px-3 py-3"><?= e($a['country_name']) ?><br><span class="text-xs text-navy-600"><?= e(visa_type_names()[$a['visa_type']] ?? $a['visa_type']) ?></span></td>
          <td class="px-3 py-3 num <?= $urgent ? 'font-semibold text-bad-700' : '' ?>"><?= nice_date($a['travel_date']) ?><?php if ($urgent): ?><br><span class="text-xs"><?= $days < 0 ? 'passed' : 'in ' . plural($days, 'day') ?></span><?php endif; ?></td>
          <td class="px-3 py-3">
            <?php if ($a['items']): ?>
              <span class="num"><?= (int) $a['accepted'] ?>/<?= (int) $a['items'] ?> accepted</span>
              <?php if ($a['to_check']): ?><br><span class="badge bg-brand-50 text-brand-700"><?= (int) $a['to_check'] ?> to check</span><?php endif; ?>
              <?php if ($a['outstanding']): ?><br><span class="badge bg-warn-100 text-warn-700"><?= (int) $a['outstanding'] ?> missing</span><?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="px-3 py-3"><span class="badge <?= $a['fee_status'] == 'unpaid' ? 'bg-warn-100 text-warn-700' : 'bg-ok-100 text-ok-700' ?>"><?= e($a['fee_status']) ?></span></td>
          <td class="px-3 py-3 text-navy-700"><?= e($a['staff_name'] ?: '—') ?></td>
          <td class="px-5 py-3"><?= status_badge($a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$apps): ?><tr><td colspan="8" class="px-5 py-10 text-center text-navy-600">No applications match.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/admin-footer.php'; ?>
