</main>

<?php $phone_digits = preg_replace('/\D/', '', setting('contact_phone')); ?>
<footer class="print:hidden relative mt-24 bg-navy-950 text-navy-200">
  <!-- Orange swoosh along the top edge, like the one in the logo -->
  <svg class="absolute inset-x-0 -top-px h-6 w-full text-sun-500" viewBox="0 0 1440 24" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0h1440v4C1000 22 420 22 0 4z" fill="#fff"/><path d="M0 4C420 22 1000 22 1440 4" fill="none" stroke="currentColor" stroke-width="3"/></svg>
  <div class="container-page pt-16 pb-10">
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1.4fr]">
      <div>
        <img src="assets/img/fanel/logo-900.webp" alt="<?= e(SITE_NAME) ?>" width="900" height="393" loading="lazy" class="h-auto w-56">
        <p class="mt-4 max-w-xs text-sm leading-relaxed">Flights, hotels, visas, travel insurance and holidays for individuals, families, executives and companies.</p>
      </div>
      <div>
        <p class="font-display text-base font-semibold text-white">Services</p>
        <ul class="mt-4 space-y-2.5 text-sm">
          <li><a class="hover:text-sun-300" href="flights.php">Flight tickets</a></li>
          <li><a class="hover:text-sun-300" href="hotels.php">Hotel reservations</a></li>
          <li><a class="hover:text-sun-300" href="visa.php">Visa assistance</a></li>
          <li><a class="hover:text-sun-300" href="contact.php?topic=insurance">Travel insurance</a></li>
          <li><a class="hover:text-sun-300" href="tours.php">Holiday packages</a></li>
          <li><a class="hover:text-sun-300" href="contact.php?topic=consultancy">Travel consultancy</a></li>
        </ul>
      </div>
      <div>
        <p class="font-display text-base font-semibold text-white">Help</p>
        <ul class="mt-4 space-y-2.5 text-sm">
          <li><a class="hover:text-sun-300" href="manage-booking.php">Manage booking</a></li>
          <li><a class="hover:text-sun-300" href="visa-track.php">Track visa application</a></li>
          <li><a class="hover:text-sun-300" href="about.php">About us</a></li>
          <li><a class="hover:text-sun-300" href="contact.php">Contact us</a></li>
        </ul>
      </div>
      <div class="space-y-3 text-sm">
        <p class="font-display text-base font-semibold text-white">Visit or call us</p>
        <p class="flex gap-3"><?= icon('pin', 'size-5 shrink-0 text-sun-400') ?> <span><?= e(contact('address')) ?></span></p>
        <p class="flex gap-3"><?= icon('clock', 'size-5 shrink-0 text-sun-400') ?> <span><?= e(contact('hours')) ?></span></p>
        <p class="flex gap-3"><?= icon('phone', 'size-5 shrink-0 text-sun-400') ?> <a class="num text-white hover:text-sun-300" href="<?= $phone_digits ? 'tel:' . $phone_digits : 'contact.php' ?>"><?= e(contact('phone')) ?></a></p>
        <p class="flex gap-3"><?= icon('mail', 'size-5 shrink-0 text-sun-400') ?> <a class="break-all text-white hover:text-sun-300" href="mailto:<?= e(setting('contact_email')) ?>"><?= e(contact('email')) ?></a></p>
      </div>
    </div>

    <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-navy-300 md:flex-row md:items-center md:justify-between">
      <p>© <?= date('Y') ?> Fanel Travels LTD. Connecting you to the world. Prices are shown in Naira (₦).</p>
      <p class="flex flex-wrap items-center gap-x-4 gap-y-1"><span>Licences: <?= e(contact('licences')) ?></span><span class="inline-flex items-center gap-1.5"><?= icon('card', 'size-4 text-sun-400') ?> Payments secured by Paystack</span></p>
    </div>
  </div>
</footer>

<?php
// Floating contact button: WhatsApp once the number is set in the admin, otherwise a phone call.
$whatsapp = preg_replace('/\D/', '', setting('contact_whatsapp'));
?>
<?php if ($whatsapp): ?>
<a href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener"
   class="print:hidden fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 rounded-full bg-[#12813f] py-3 pl-3.5 pr-4 text-sm font-bold text-white shadow-xl shadow-[#12813f]/30 ring-4 ring-white transition hover:-translate-y-0.5 hover:bg-[#0e6b34]">
  <?= icon('chat') ?> <span>Chat on WhatsApp</span>
</a>
<?php elseif ($phone_digits): ?>
<a href="tel:<?= $phone_digits ?>"
   class="print:hidden fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 rounded-full bg-brand-600 py-3 pl-3.5 pr-4 text-sm font-bold text-white shadow-xl shadow-brand-600/30 ring-4 ring-white transition hover:-translate-y-0.5 hover:bg-brand-700">
  <?= icon('phone') ?> <span>Call us</span>
</a>
<?php endif; ?>
<script src="assets/js/site.js" defer></script>
</body>
</html>
