<?php
require 'includes/init.php';

// Search links go two weeks ahead, returning a week later
$route_depart = date('Y-m-d', strtotime('+14 days'));
$route_return = date('Y-m-d', strtotime('+21 days'));

function route_link($from, $to)
{
    global $route_depart, $route_return;
    return "flight-results.php?trip=return&from[]=$from&to[]=$to&depart[]=$route_depart&return=$route_return&adults=1&cabin=economy";
}

// Straight-line distance between two airports in km, worked out from their coordinates
function airport_km($a, $b)
{
    $p = db_one("SELECT latitude, longitude FROM airports WHERE iata_code = ?", [$a]);
    $q = db_one("SELECT latitude, longitude FROM airports WHERE iata_code = ?", [$b]);
    $lat1 = deg2rad($p['latitude']);
    $lat2 = deg2rad($q['latitude']);
    $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad($q['longitude'] - $p['longitude']) / 2) ** 2;
    return round(6371 * 2 * asin(sqrt($h)));
}

// Routes from Port Harcourt: a few local ones, and five featured cities
$local_routes = [['LOS', 'Lagos'], ['ABV', 'Abuja'], ['KAN', 'Kano'], ['ENU', 'Enugu']];

// Destination features (photos in assets/img/)
$destinations = [
    ['London', 'United Kingdom', 'LHR', 'london', 'Tower Bridge in London at sunset', 'Family visits, study and the West End.'],
    ['Dubai', 'United Arab Emirates', 'DXB', 'dubai', 'Burj Khalifa and the Dubai skyline', 'Shopping, sunshine and an evening in the desert.'],
    ['Accra', 'Ghana', 'ACC', 'accra', 'Kwame Nkrumah Memorial Park in Accra', 'A short hop from Port Harcourt. No visa needed for Nigerians.'],
    ['New York', 'United States', 'JFK', 'new-york', 'Manhattan skyline across the Hudson River', 'Business, family and the city that never stops.'],
    ['Toronto', 'Canada', 'YYZ', 'toronto', 'Toronto skyline and the CN Tower', 'Visiting family, school and new beginnings.'],
];

$services = [
    ['plane', 'Flight reservations and ticketing', 'Domestic and international airline bookings and ticket issuance.', 'flights.php', 'Search flights'],
    ['bed', 'Hotel reservations', 'Accommodation bookings and hotel arrangements, locally and internationally.', 'hotels.php', 'Find a hotel'],
    ['passport', 'Visa assistance', 'Guidance and support with visa applications and travel documents.', 'visa.php', 'Start a visa'],
    ['shield', 'Travel insurance', 'Travel protection for individuals and groups.', 'contact.php?topic=insurance', 'Get a quote'],
    ['palm', 'Tourism and holiday packages', 'Tailored holidays, sightseeing and leisure travel.', 'tours.php', 'See holidays'],
    ['compass', 'Travel consultancy', 'Travel advice, itinerary planning and travel management.', 'contact.php?topic=consultancy', 'Talk to us'],
];

require 'includes/posters.php';

$visas = db_all("SELECT * FROM visa_destinations WHERE is_active = 1 ORDER BY sort_order LIMIT 6");

$tours = db_all("
    SELECT p.*, MIN(d.start_date) AS next_date
    FROM tour_packages p
    LEFT JOIN tour_departures d ON d.package_id = p.id AND d.status = 'open' AND d.start_date >= CURDATE()
    WHERE p.is_published = 1 AND p.is_featured = 1
    GROUP BY p.id
    ORDER BY p.sort_order
    LIMIT 3
");

$whatsapp = preg_replace('/\D/', '', setting('contact_whatsapp'));
$phone_digits = preg_replace('/\D/', '', setting('contact_phone'));

$title = '';
$page = 'home';
include 'includes/header.php';
?>

<!-- =============================== HERO =============================== -->
<section class="relative isolate overflow-hidden bg-navy-900 text-white">
  <!-- Background: deep navy into globe blue, with a faint orbit like the logo -->
  <div class="absolute inset-0 -z-20 bg-[radial-gradient(120%_90%_at_85%_10%,#1d55d0_0%,#0f2c5c_45%,#0a1f44_75%)]"></div>
  <svg class="absolute -right-40 -top-40 -z-10 size-[46rem] text-white/[0.06]" viewBox="0 0 400 400" fill="none" aria-hidden="true">
    <circle cx="200" cy="200" r="160" stroke="currentColor" stroke-width="1.5"/><circle cx="200" cy="200" r="120" stroke="currentColor" stroke-width="1.5"/>
    <ellipse cx="200" cy="200" rx="60" ry="160" stroke="currentColor" stroke-width="1.5"/><path d="M40 200h320M60 130h280M60 270h280" stroke="currentColor" stroke-width="1.5"/>
  </svg>

  <div class="container-page grid gap-12 pt-12 pb-40 sm:pt-16 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:pt-20 lg:pb-48">
    <div>
      <p class="eyebrow text-sun-300">Travel agency · Port Harcourt</p>
      <h1 class="mt-5 text-[2.75rem] font-bold leading-[1.02] sm:text-6xl lg:text-7xl">
        Connecting you<br>to <span class="swoosh text-sun-400">the world.</span>
      </h1>
      <p class="mt-6 max-w-xl text-lg leading-relaxed text-navy-100">Flights, hotels, visas, travel insurance and holidays for families, business travellers and companies, booked by people you can call or visit. Pay in Naira.</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="#search" class="btn btn-accent px-6 py-3.5 text-base">Book a trip <?= icon('arrow-right', 'size-5') ?></a>
        <a href="visa.php" class="btn px-6 py-3.5 text-base text-white ring-1 ring-inset ring-white/30 hover:bg-white/10">Visa assistance</a>
      </div>
      <dl class="mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-white/15 pt-6 text-sm">
        <div><dt class="text-navy-300">Airlines</dt><dd class="mt-1 font-display text-xl font-semibold sm:text-2xl">300+</dd></div>
        <div><dt class="text-navy-300">Open</dt><dd class="mt-1 font-display text-xl font-semibold sm:text-2xl">Mon–Sat</dd></div>
        <div><dt class="text-navy-300">Pay by</dt><dd class="mt-1 font-display text-xl font-semibold sm:text-2xl">Card · USSD</dd></div>
      </dl>
    </div>

    <!-- Three Fanel flyers, fanned out -->
    <div class="relative mx-auto hidden h-[30rem] w-full max-w-md sm:block" aria-hidden="true">
      <div class="absolute left-0 top-10 w-60 -rotate-[8deg] overflow-hidden rounded-3xl shadow-2xl ring-4 ring-white/90"><?= photo('fanel/seychelles', '', 'aspect-square w-full object-cover', false, [480, 800], '15rem') ?></div>
      <div class="absolute right-0 top-0 w-60 rotate-[7deg] overflow-hidden rounded-3xl shadow-2xl ring-4 ring-white/90"><?= photo('fanel/dubai-multiple-entry', '', 'aspect-square w-full object-cover', false, [480, 800], '15rem') ?></div>
      <div class="absolute bottom-0 left-1/2 w-64 -translate-x-1/2 rotate-[-1deg] overflow-hidden rounded-3xl shadow-2xl ring-4 ring-white"><?= photo('fanel/kenya-visa', '', 'aspect-square w-full object-cover', false, [480, 800], '16rem') ?></div>
      <div class="absolute -left-4 bottom-16 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-navy-900 shadow-xl">
        <span class="flex size-10 items-center justify-center rounded-full bg-sun-100 text-sun-700"><?= icon('passport', 'size-5') ?></span>
        <span class="text-sm leading-tight"><b class="block">Visa desk</b><span class="text-navy-500">Documents checked for you</span></span>
      </div>
    </div>
  </div>
</section>

<!-- Search box, pulled up over the bottom of the hero -->
<section id="search" class="relative z-10 -mt-28 scroll-mt-28 lg:-mt-32">
  <div class="container-page">
    <?php $tab = 'flights'; include 'includes/search-box.php'; ?>
  </div>
</section>

<!-- =============================== SERVICES =============================== -->
<section class="container-page py-20 sm:py-28">
  <div class="reveal mx-auto max-w-2xl text-center">
    <p class="eyebrow text-sun-700">What we do</p>
    <h2 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Everything for the trip, in one place</h2>
    <p class="mt-4 text-lg text-navy-600">Tell us where you're going. We'll handle the ticket, the room, the paperwork and the cover.</p>
  </div>

  <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($services as $n => $sv): ?>
      <a href="<?= e($sv[3]) ?>" style="--delay: <?= $n * 70 ?>ms" class="reveal card group relative flex flex-col p-7 transition hover:-translate-y-1 hover:shadow-[0_24px_50px_-20px_rgba(29,85,208,0.45)]">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white"><?= icon($sv[0], 'size-7') ?></span>
        <h3 class="mt-6 text-xl font-semibold"><?= e($sv[1]) ?></h3>
        <p class="mt-2 flex-1 text-navy-600"><?= e($sv[2]) ?></p>
        <span class="mt-6 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700"><?= e($sv[4]) ?> <?= icon('arrow-right', 'size-4 transition group-hover:translate-x-1') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- =============================== VISA DESK =============================== -->
<section class="relative overflow-hidden bg-cloud-100 py-20 sm:py-28">
  <div class="container-page">
    <div class="reveal flex flex-wrap items-end justify-between gap-6">
      <div class="max-w-2xl">
        <p class="eyebrow text-sun-700">From our visa desk</p>
        <h2 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Visas we're processing now</h2>
        <p class="mt-4 text-lg text-navy-600">Tourist, work and residence visas. Tap a flyer to see it in full, or ask us about your destination.</p>
      </div>
      <div class="flex gap-2" aria-hidden="true">
        <button type="button" data-scroll="#visa-posters" data-dir="-1" class="flex size-12 items-center justify-center rounded-full bg-white text-navy-900 shadow-sm ring-1 ring-navy-900/10 hover:bg-navy-900 hover:text-white" tabindex="-1"><?= icon('arrow-right', 'size-5 rotate-180') ?></button>
        <button type="button" data-scroll="#visa-posters" data-dir="1" class="flex size-12 items-center justify-center rounded-full bg-white text-navy-900 shadow-sm ring-1 ring-navy-900/10 hover:bg-navy-900 hover:text-white" tabindex="-1"><?= icon('arrow-right', 'size-5') ?></button>
      </div>
    </div>
  </div>

  <div id="visa-posters" class="snap-row snap-row-page mt-10 flex gap-5 overflow-x-auto pb-4">
    <?php foreach ($visa_posters as $p) echo poster_card($p); ?>
  </div>

  <div class="container-page mt-14 grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:items-start">
    <ol class="grid gap-4 sm:grid-cols-2">
      <?php foreach ([
          ['Check', 'We tell you exactly what your destination asks for.'],
          ['Prepare', 'We review your documents and fill the forms with you.'],
          ['Appointment', 'We book it and prepare you for the interview.'],
          ['Track', 'Follow every step online until you have your decision.'],
      ] as $k => $step): ?>
        <li class="flex gap-4 rounded-2xl bg-white p-5 ring-1 ring-navy-900/5">
          <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sun-500 font-display text-lg font-bold text-navy-950"><?= $k + 1 ?></span>
          <span><b class="font-display text-lg font-semibold"><?= $step[0] ?></b><br><span class="text-sm text-navy-600"><?= $step[1] ?></span></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <div>
      <p class="font-semibold">Apply online for</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <?php foreach ($visas as $visa): ?>
          <a href="visa.php?country=<?= e($visa['slug']) ?>" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-navy-900/10 transition hover:bg-brand-600 hover:text-white hover:ring-brand-600">
            <?= e($visa['country_name']) ?> <span class="font-normal opacity-70 num">· from <?= naira($visa['service_fee_kobo']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="mt-3 text-xs text-navy-500">The embassy makes the final decision. Our fee is for our service and doesn't include the embassy's visa fee.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a class="btn btn-primary px-5 py-3" href="visa.php">Start a visa application</a>
        <a class="btn btn-ghost bg-white px-5 py-3" href="visa-track.php">Track an application</a>
      </div>
    </div>
  </div>
</section>

<!-- =============================== DESTINATIONS =============================== -->
<section class="container-page py-20 sm:py-28">
  <div class="reveal flex flex-wrap items-end justify-between gap-6">
    <div class="max-w-2xl">
      <p class="eyebrow text-sun-700">Flying from Port Harcourt</p>
      <h2 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Popular trips our clients take</h2>
    </div>
    <p class="max-w-sm text-navy-600">Return fares for one adult in economy, two weeks from today. Tap a city to see today's prices.</p>
  </div>

  <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($destinations as $n => $d): ?>
      <a href="<?= e(route_link('PHC', $d[2])) ?>" style="--delay: <?= $n * 70 ?>ms" class="reveal card group overflow-hidden <?= $n == 0 ? 'lg:col-span-2 lg:row-span-1' : '' ?>">
        <div class="relative overflow-hidden">
          <?= photo($d[3], $d[4], 'h-56 w-full object-cover transition duration-700 group-hover:scale-105 ' . ($n < 2 ? 'lg:h-72' : ''), true, [480, 800], $n == 0 ? '(min-width: 1024px) 60vw, 100vw' : '(min-width: 1024px) 30vw, 50vw') ?>
          <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-navy-900">PHC → <?= $d[2] ?></span>
        </div>
        <div class="flex items-end justify-between gap-4 p-6">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-700"><?= e($d[1]) ?></p>
            <h3 class="mt-1 text-2xl font-semibold"><?= e($d[0]) ?></h3>
            <p class="mt-1 text-sm text-navy-600"><?= e($d[5]) ?></p>
          </div>
          <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 transition group-hover:bg-sun-500 group-hover:text-navy-950"><?= icon('arrow-up-right', 'size-5') ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="reveal mt-8 flex flex-wrap items-center gap-2 rounded-2xl bg-cloud-50 p-4 ring-1 ring-cloud-200 sm:p-5">
    <span class="mr-2 flex items-center gap-2 text-sm font-semibold"><?= icon('plane', 'size-5 text-brand-600') ?> Within Nigeria from PHC:</span>
    <?php foreach ($local_routes as $r): ?>
      <a href="<?= e(route_link('PHC', $r[0])) ?>" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-navy-900/10 transition hover:bg-brand-600 hover:text-white hover:ring-brand-600"><?= e($r[1]) ?> <span class="font-normal opacity-60 num"><?= number_format(airport_km('PHC', $r[0])) ?> km</span></a>
    <?php endforeach; ?>
  </div>
</section>

<!-- =============================== TOURS =============================== -->
<section class="bg-navy-900 py-20 text-white sm:py-28">
  <div class="container-page">
    <div class="reveal flex flex-wrap items-end justify-between gap-6">
      <div class="max-w-2xl">
        <p class="eyebrow text-sun-300">Tours and holidays</p>
        <h2 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Holidays we've already planned</h2>
      </div>
      <a class="btn bg-white px-5 py-3 text-navy-900 hover:bg-sun-400" href="tours.php">See all holidays <?= icon('arrow-right', 'size-4') ?></a>
    </div>

    <?php if ($tours): ?>
    <div class="mt-12 grid gap-6 md:grid-cols-3">
      <?php foreach ($tours as $n => $tour): ?>
        <a href="tour.php?slug=<?= e($tour['slug']) ?>" style="--delay: <?= $n * 90 ?>ms" class="reveal group flex flex-col overflow-hidden rounded-3xl bg-white text-navy-900">
          <div class="relative overflow-hidden">
            <?php if ($tour['cover_image']): ?>
              <?= photo($tour['cover_image'], $tour['destination'], 'aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-105', true, [480, 800], '(min-width: 768px) 33vw, 100vw') ?>
            <?php else: ?>
              <div class="aspect-[4/3] w-full bg-cloud-200"></div>
            <?php endif; ?>
            <span class="absolute left-4 top-4 rounded-full bg-navy-900/80 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"><?= plural($tour['duration_nights'], 'night') ?></span>
          </div>
          <div class="flex flex-1 flex-col p-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-700"><?= e($tour['destination']) ?></p>
            <h3 class="mt-1 text-xl font-semibold leading-snug"><?= e($tour['title']) ?></h3>
            <?php if ($tour['next_date']): ?>
              <p class="mt-2 text-sm text-navy-600">Next departure <b class="num text-navy-900"><?= nice_date($tour['next_date']) ?></b></p>
            <?php endif; ?>
            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
              <p><span class="block font-display text-2xl font-bold num"><?= naira($tour['price_kobo']) ?></span><span class="text-xs text-navy-500"><?= e($tour['price_basis']) ?></span></p>
              <span class="flex size-11 items-center justify-center rounded-full bg-sun-500 text-navy-950 transition group-hover:translate-x-1"><?= icon('arrow-right', 'size-5') ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mt-14">
      <p class="text-sm font-semibold text-navy-200">Holiday ideas from our team</p>
      <div class="snap-row mt-4 flex gap-5 overflow-x-auto pb-2 [&_figcaption_span]:text-white [&_figcaption_a]:text-sun-300">
        <?php foreach ($holiday_posters as $p) echo poster_card($p, 'w-64 sm:w-80'); ?>
      </div>
    </div>
  </div>
</section>

<!-- =============================== WHY FANEL + TALK TO US =============================== -->
<section class="container-page pt-20 sm:pt-28">
  <div class="grid gap-12 lg:grid-cols-[1fr_1.1fr] lg:items-center">
    <div class="reveal">
      <p class="eyebrow text-sun-700">Why travel with Fanel</p>
      <h2 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Travel made simple, from start to finish</h2>
      <p class="mt-4 text-lg leading-relaxed text-navy-600">Our goal is to make domestic and international travel convenient, accessible and stress-free, with solutions shaped around you.</p>
      <ul class="mt-8 grid gap-4 sm:grid-cols-2">
        <?php foreach ([
            ['Professionalism', 'Trained staff who know the airlines, hotels and embassies.'],
            ['Integrity', 'Clear prices in Naira and honest advice, even when it means a smaller sale.'],
            ['Efficiency', 'Tickets, rooms and visa files handled quickly and checked twice.'],
            ['Customer care', 'Call, message or walk in. A real person answers.'],
        ] as $v): ?>
          <li class="flex gap-3">
            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white"><?= icon('check', 'size-4') ?></span>
            <span><b class="font-semibold"><?= $v[0] ?></b><br><span class="text-sm text-navy-600"><?= $v[1] ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="reveal relative overflow-hidden rounded-[2rem] bg-sun-500 p-8 text-navy-950 sm:p-12">
      <svg class="absolute -right-16 -top-16 size-72 text-white/20" viewBox="0 0 200 200" fill="none" aria-hidden="true"><circle cx="100" cy="100" r="80" stroke="currentColor" stroke-width="3"/><ellipse cx="100" cy="100" rx="34" ry="80" stroke="currentColor" stroke-width="3"/><path d="M20 100h160" stroke="currentColor" stroke-width="3"/></svg>
      <h2 class="relative text-3xl font-bold leading-tight sm:text-4xl">Rather talk it through?</h2>
      <p class="relative mt-3 max-w-md text-lg">Group bookings, special assistance, trips with several stops, corporate travel, or a second opinion. Call us, send a message, or visit our office.</p>
      <dl class="relative mt-6 space-y-2 text-sm">
        <div class="flex gap-2"><dt><?= icon('pin', 'size-5') ?></dt><dd class="font-medium"><?= e(contact('address')) ?></dd></div>
        <div class="flex gap-2"><dt><?= icon('clock', 'size-5') ?></dt><dd class="font-medium"><?= e(contact('hours')) ?></dd></div>
      </dl>
      <div class="relative mt-8 flex flex-wrap gap-3">
        <?php if ($phone_digits): ?>
          <a class="btn btn-dark px-6 py-3.5 text-base" href="tel:<?= $phone_digits ?>"><?= icon('phone') ?> <span class="num"><?= e(contact('phone')) ?></span></a>
        <?php endif; ?>
        <?php if ($whatsapp): ?>
          <a class="btn bg-white px-6 py-3.5 text-base text-navy-900 hover:bg-cloud-100" href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp</a>
        <?php endif; ?>
        <a class="btn bg-white px-6 py-3.5 text-base text-navy-900 hover:bg-cloud-100" href="contact.php">Send a message</a>
      </div>
    </div>
  </div>
</section>

<?= poster_viewer() ?>

<?php include 'includes/footer.php'; ?>
