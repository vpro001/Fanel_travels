<?php
// Visa and holiday flyers supplied by Fanel (assets/img/fanel/<name>-480.webp and -800.webp).
// Each one: [image name, caption, link]. Visa flyers link to the visa page when we have that
// country online, otherwise to the contact form with "Visa" chosen.

$visa_posters = [
    ['dubai-multiple-entry', 'Dubai 5-year multiple-entry visa', 'visa.php?country=united-arab-emirates'],
    ['thailand-visa', 'Thailand visa', 'contact.php?topic=visa'],
    ['malaysia-visa', 'Malaysia visa', 'contact.php?topic=visa'],
    ['qatar-visa', 'Qatar visa', 'contact.php?topic=visa'],
    ['kenya-visa', 'Kenya visa', 'contact.php?topic=visa'],
    ['indonesia-visa', 'Indonesia visa', 'contact.php?topic=visa'],
    ['mexico-visa', 'Mexico tourist visa', 'contact.php?topic=visa'],
    ['uganda-visa', 'Uganda visa', 'contact.php?topic=visa'],
    ['east-africa-visa', 'East Africa tourist visa', 'contact.php?topic=visa'],
    ['kuwait-oman-work-visa', 'Kuwait and Oman work visas', 'contact.php?topic=visa'],
    ['qatar-work-visa', 'Qatar work visa', 'contact.php?topic=visa'],
    ['indonesia-residence', 'Indonesia 2-year residence permit', 'contact.php?topic=visa'],
    ['dubai-evisa', 'Dubai e-visa (non-Nigerians)', 'visa.php?country=united-arab-emirates'],
    ['visa-countries', 'More countries we process', 'contact.php?topic=visa'],
];

$holiday_posters = [
    ['seychelles', 'Seychelles holiday', 'contact.php?topic=tour'],
    ['qatar-family', 'Family escape to Qatar', 'contact.php?topic=tour'],
    ['seychelles-ethiopia', 'Seychelles and Ethiopia', 'contact.php?topic=tour'],
];

// One poster card. Opens the large version in a viewer (assets/js/site.js); without
// JavaScript the link simply opens the image.
function poster_card($p, $class = 'w-64 sm:w-72')
{
    return '<figure class="group shrink-0 ' . $class . '">'
        . '<a href="assets/img/fanel/' . $p[0] . '-800.webp" data-poster data-caption="' . e($p[1]) . '" data-link="' . e($p[2]) . '"'
        . ' class="block overflow-hidden rounded-2xl bg-cloud-100 shadow-[0_14px_30px_-14px_rgba(10,31,68,0.4)] ring-1 ring-navy-900/5">'
        . photo('fanel/' . $p[0], $p[1], 'aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.04]', true, [480, 800], '(min-width: 640px) 18rem, 16rem')
        . '</a>'
        . '<figcaption class="mt-3 flex items-center justify-between gap-3 px-1">'
        . '<span class="text-sm font-semibold text-navy-900">' . e($p[1]) . '</span>'
        . '<a class="shrink-0 text-sm font-semibold text-brand-700 hover:underline" href="' . e($p[2]) . '">Ask us</a>'
        . '</figcaption></figure>';
}

// The pop-up poster viewer. Include once per page that shows posters.
function poster_viewer()
{
    return '<dialog id="poster-viewer" class="m-auto max-h-[92dvh] w-[min(92vw,40rem)] overflow-visible bg-transparent p-0 backdrop:bg-navy-950/80 backdrop:backdrop-blur-sm">'
        . '<div class="relative overflow-hidden rounded-3xl bg-white shadow-2xl">'
        . '<img alt="" class="block max-h-[75dvh] w-full object-contain bg-cloud-100">'
        . '<div class="flex items-center justify-between gap-4 p-4 sm:p-5"><p class="font-display text-lg font-semibold" data-caption></p>'
        . '<a class="btn btn-accent shrink-0" data-link href="contact.php?topic=visa">Ask about this</a></div>'
        . '<form method="dialog"><button class="absolute right-3 top-3 flex size-10 items-center justify-center rounded-full bg-white/90 text-navy-900 shadow-md hover:bg-white" aria-label="Close">' . icon('close', 'size-5') . '</button></form>'
        . '</div></dialog>';
}
