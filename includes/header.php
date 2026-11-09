<?php
// Site header. Set these before including it:
//   $title = 'Contact us';   page title
//   $page  = 'flights';      highlights that menu item (flights, hotels, visa, tours)
if (!isset($title)) $title = '';
if (!isset($page)) $page = '';
if (!isset($description)) $description = "Fanel Travels LTD, Port Harcourt: flights, hotels, visa assistance, travel insurance and holiday packages. Connecting you to the world. Pay securely in Naira.";

$menu = ['flights.php' => 'Flights', 'hotels.php' => 'Hotels', 'visa.php' => 'Visa', 'tours.php' => 'Tours'];
?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ? e($title) . ' · ' . SITE_NAME : SITE_NAME . ' — Flights, hotels, visas and tours' ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#0a1f44">
<?php
// Search engines and link previews (WhatsApp, Facebook, X). Private pages set $noindex = true.
$canonical = SITE_URL . '/' . basename(strtok($_SERVER['REQUEST_URI'], '?'));
$private = ['booking.php', 'hotel-booking.php', 'tour-booking.php', 'account.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'flight-book.php', 'hotel-book.php', 'visa-track.php', 'manage-booking.php', 'flight-results.php', 'hotel-results.php'];
if (in_array(basename($_SERVER['SCRIPT_NAME']), $private)) $noindex = true;
?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php else: ?><link rel="canonical" href="<?= e($canonical . (get('slug') ? '?slug=' . urlencode(get('slug')) : (get('country') ? '?country=' . urlencode(get('country')) : ''))) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($title ?: SITE_NAME) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e(SITE_URL) ?>/assets/img/fanel/logo-900.webp">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<?php include __DIR__ . '/analytics.php'; ?>
<?php $base = ''; include __DIR__ . '/head-assets.php'; ?>
</head>
<body class="min-h-dvh flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] btn btn-primary">Skip to content</a>

<?php if (FLIGHT_DATA === 'demo'): ?>
<div class="print:hidden bg-navy-950 text-sun-300 text-center text-xs font-semibold py-1.5">Demo data · flight and hotel results are samples, not live fares</div>
<?php endif; ?>

<?php $phone_digits = preg_replace('/\D/', '', setting('contact_phone')); ?>
<div class="print:hidden hidden bg-sun-500 text-navy-950 md:block">
  <div class="container-page flex h-9 items-center justify-between gap-6 text-[13px] font-medium">
    <p class="flex items-center gap-1.5 truncate"><?= icon('pin', 'size-4 shrink-0') ?> <?= e(contact('address')) ?></p>
    <div class="flex shrink-0 items-center gap-5">
      <span class="hidden items-center gap-1.5 lg:flex"><?= icon('clock', 'size-4') ?> <?= e(contact('hours')) ?></span>
      <a class="flex items-center gap-1.5 font-semibold hover:underline" href="<?= $phone_digits ? 'tel:' . $phone_digits : 'contact.php' ?>"><?= icon('phone', 'size-4') ?> <span class="num"><?= e(contact('phone')) ?></span></a>
      <a class="hidden items-center gap-1.5 hover:underline xl:flex" href="mailto:<?= e(setting('contact_email')) ?>"><?= icon('mail', 'size-4') ?> <?= e(contact('email')) ?></a>
    </div>
  </div>
</div>

<header class="print:hidden sticky top-0 z-50 bg-navy-900/95 text-white shadow-lg shadow-navy-950/20 backdrop-blur-md">
  <div class="container-page flex h-[4.5rem] items-center justify-between gap-4">
    <a href="index.php" class="shrink-0" aria-label="<?= e(SITE_NAME) ?>, home">
      <img src="assets/img/fanel/logo-header.webp" alt="<?= e(SITE_NAME) ?>" width="640" height="242" class="h-12 w-auto sm:h-[3.4rem]">
    </a>

    <nav class="hidden md:flex items-center gap-1 text-sm font-semibold">
      <?php foreach ($menu as $link => $label): $on = $page . '.php' == $link; ?>
        <a href="<?= $link ?>" <?= $on ? 'aria-current="page"' : '' ?> class="rounded-full px-4 py-2 transition <?= $on ? 'bg-white text-navy-900' : 'text-navy-100 hover:bg-white/10 hover:text-white' ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <a href="about.php" class="rounded-full px-4 py-2 transition <?= basename($_SERVER['SCRIPT_NAME']) == 'about.php' ? 'bg-white text-navy-900' : 'text-navy-100 hover:bg-white/10 hover:text-white' ?>">About</a>
      <span class="mx-2 h-6 w-px bg-white/15" aria-hidden="true"></span>
      <a href="<?= empty($_SESSION['user']) ? 'login.php' : 'account.php' ?>" class="rounded-full px-3 py-2 transition hover:text-sun-300 <?= $page == 'account' ? 'text-sun-300' : 'text-navy-100' ?>"><?= empty($_SESSION['user']) ? 'Sign in' : 'My account' ?></a>
      <a href="manage-booking.php" class="btn btn-accent ml-1 py-2">Manage booking</a>
    </nav>

    <details class="md:hidden relative">
      <summary class="list-none rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">Menu</summary>
      <nav class="absolute right-0 z-40 mt-3 w-64 rounded-2xl bg-white p-2 text-navy-900 shadow-2xl ring-1 ring-navy-900/10">
        <?php foreach ($menu as $link => $label): ?>
          <a href="<?= $link ?>" class="block rounded-xl px-3 py-3 font-medium hover:bg-cloud-100"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="about.php" class="block rounded-xl px-3 py-3 font-medium hover:bg-cloud-100">About us</a>
        <a href="contact.php" class="block rounded-xl px-3 py-3 font-medium hover:bg-cloud-100">Contact us</a>
        <a href="<?= empty($_SESSION['user']) ? 'login.php' : 'account.php' ?>" class="block rounded-xl px-3 py-3 font-medium hover:bg-cloud-100"><?= empty($_SESSION['user']) ? 'Sign in' : 'My account' ?></a>
        <a href="manage-booking.php" class="btn btn-accent mt-1 w-full">Manage booking</a>
        <?php if ($phone_digits): ?><a href="tel:<?= $phone_digits ?>" class="mt-2 flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold text-brand-700"><?= icon('phone', 'size-4') ?> <?= e(contact('phone')) ?></a><?php endif; ?>
      </nav>
    </details>
  </div>
</header>

<?php if (isset($_SESSION['flash'])): ?>
  <div class="container-page mt-4">
    <p class="rounded-xl px-4 py-3 text-sm font-medium <?= $_SESSION['flash']['type'] == 'error' ? 'bg-bad-100 text-bad-700' : 'bg-ok-100 text-ok-700' ?>">
      <?= e($_SESSION['flash']['message']) ?>
    </p>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<main id="main" class="flex-1">
