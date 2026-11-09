<?php
require 'includes/init.php';

$title = 'About us';
$description = 'FANEL Travels LTD is a Port Harcourt travel and tourism company: flights, hotels, visa assistance, travel insurance, holiday packages and travel consultancy.';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-900 text-paper-50">
  <?= photo('plane-wing', 'View of an aircraft wing from the window', 'absolute inset-0 -z-20 h-full w-full object-cover object-[50%_35%]', false, [800, 1600]) ?>
  <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950/95 via-ink-950/80 to-ink-950/30"></div>
  <div class="container-page py-14 sm:py-24">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-saffron-300">About Fanel Travels LTD</p>
    <h1 class="mt-3 max-w-2xl text-3xl sm:text-5xl font-bold leading-tight">Connecting you to the world.</h1>
    <p class="mt-4 max-w-xl text-lg text-ink-100 leading-relaxed">A Port Harcourt travel and tourism company for individuals, families, business executives and corporate organisations.</p>
  </div>
</section>

<section class="container-page py-10 sm:py-14 max-w-3xl">

  <h2 class="text-xl font-bold">Who we are</h2>
  <p class="mt-3 text-ink-700 leading-relaxed">FANEL Travels LTD is a travel and tourism company dedicated to delivering comprehensive, reliable, and customer-focused travel solutions. We provide seamless travel services to individuals, families, business executives, and corporate organisations, with a strong commitment to professionalism, efficiency, integrity, and customer satisfaction.</p>
  <p class="mt-3 text-ink-700 leading-relaxed">Our goal is to simplify the travel experience by providing tailored solutions that make both domestic and international travel convenient, accessible, and stress-free.</p>

  <div class="mt-8 grid gap-5 sm:grid-cols-2">
    <div class="rounded-lg bg-paper-100 p-5">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-[0.18em] text-saffron-700">Our mission</h2>
      <p class="mt-2 text-ink-800 leading-relaxed">To deliver exceptional, reliable, and cost-effective travel solutions through professionalism, integrity, innovation, and outstanding customer service, ensuring that every client’s travel experience is seamless and rewarding.</p>
    </div>
    <div class="rounded-lg bg-paper-100 p-5">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-[0.18em] text-saffron-700">Our vision</h2>
      <p class="mt-2 text-ink-800 leading-relaxed">To become a leading and highly trusted travel and tourism company, distinguished by service excellence, innovation, and customer satisfaction, while building lasting relationships and connecting people and businesses to opportunities around the world.</p>
    </div>
  </div>

  <h2 class="mt-10 text-xl font-bold">What we do</h2>
  <dl class="mt-4 grid gap-5 sm:grid-cols-2">
    <div>
      <dt class="font-semibold"><a class="link" href="flights.php">Flight reservations and ticketing</a></dt>
      <dd class="mt-1 text-ink-700">Domestic and international airline bookings and ticket issuance.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="hotels.php">Hotel reservations</a></dt>
      <dd class="mt-1 text-ink-700">Accommodation bookings and hotel arrangements, locally and internationally.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="visa.php">Visa assistance</a></dt>
      <dd class="mt-1 text-ink-700">Guidance and support with visa applications and travel documentation.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="contact.php?topic=insurance">Travel insurance</a></dt>
      <dd class="mt-1 text-ink-700">Travel protection solutions for individuals and groups.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="tours.php">Tourism and holiday packages</a></dt>
      <dd class="mt-1 text-ink-700">Tailored holiday packages, sightseeing and leisure travel arrangements.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="contact.php?topic=consultancy">Travel consultancy</a></dt>
      <dd class="mt-1 text-ink-700">Professional travel advice, itinerary planning and travel management services.</dd>
    </div>
  </dl>

  <h2 class="mt-10 text-xl font-bold">Licences and registration</h2>
  <p class="mt-3 text-ink-700"><?= e(contact('licences')) ?></p>

  <h2 class="mt-10 text-xl font-bold">Find us</h2>
  <p class="mt-3 text-ink-700"><?= e(contact('address')) ?></p>
  <p class="mt-1 text-ink-700"><?= e(contact('hours')) ?></p>
  <p class="mt-1 text-ink-700"><?= e(contact('phone')) ?> · <?= e(contact('email')) ?></p>
  <p class="mt-6"><a class="btn btn-dark" href="contact.php">Contact us</a></p>
</section>

<?php include 'includes/footer.php'; ?>
