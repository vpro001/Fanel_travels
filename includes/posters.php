<?php
// Visa and holiday posts: the flyers staff post in Admin → Visa & holiday posts (table visa_offers).
// Every visa post leads to a real application (visa-apply.php), never the contact form.
// Images are saved as <image>-480 and <image>-800 (.webp for the first flyers, .jpg for uploads).

// Posts on show: active, not expired, in the order staff set. $kind = 'visa', 'holiday' or '' for both.
// Visa posts for a country that's hidden on the site are left out (nobody could apply).
function visa_offers($kind = '', $limit = 50)
{
    $sql = "SELECT o.*, d.slug AS destination_slug, d.country_name
            FROM visa_offers o LEFT JOIN visa_destinations d ON d.id = o.destination_id
            WHERE o.is_active = 1 AND (o.expires_on IS NULL OR o.expires_on >= CURDATE())
              AND (o.destination_id IS NULL OR d.is_active = 1)";
    $params = [];
    if ($kind != '') {
        $sql .= " AND o.kind = ?";
        $params[] = $kind;
    }
    return db_all($sql . " ORDER BY o.sort_order, o.id DESC LIMIT " . (int) $limit, $params);
}

// Where a post's button goes:
//  - visa post for one country: straight to that country's application, with the visa type chosen
//  - visa post covering several countries: the list of countries, to pick one and apply
//  - holiday post: the holidays page
function offer_link($o)
{
    if ($o['kind'] == 'visa' && $o['destination_id']) {
        return ['visa-apply.php?' . http_build_query(array_filter(['country' => $o['destination_slug'], 'type' => $o['visa_type'], 'offer' => $o['id']])), 'Apply now'];
    }
    if ($o['kind'] == 'visa') {
        return ['visa.php#countries', 'Choose a country'];
    }
    return ['tours.php', 'See holidays'];
}

// URL of one size of a post's image
function offer_image_url($image, $size = 800)
{
    $base = "assets/img/$image-$size";
    return file_exists(__DIR__ . "/../$base.webp") ? "$base.webp" : "$base.jpg";
}

// One poster card. Opens the large version in a viewer (assets/js/site.js); without
// JavaScript the link simply opens the image.
function poster_card($o, $class = 'w-64 sm:w-72')
{
    list($link, $link_text) = offer_link($o);
    return '<figure class="group shrink-0 ' . $class . '">'
        . '<a href="' . e(offer_image_url($o['image'])) . '" data-poster data-caption="' . e($o['title']) . '" data-summary="' . e((string) $o['summary']) . '"'
        . ' data-link="' . e($link) . '" data-link-text="' . e($link_text) . '"'
        . ' class="block overflow-hidden rounded-2xl bg-cloud-100 shadow-[0_14px_30px_-14px_rgba(10,31,68,0.4)] ring-1 ring-navy-900/5">'
        . photo($o['image'], $o['title'], 'aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.04]', true, [480, 800], '(min-width: 640px) 18rem, 16rem')
        . '</a>'
        . '<figcaption class="mt-3 flex items-start justify-between gap-3 px-1">'
        . '<span class="text-sm font-semibold text-navy-900">' . e($o['title'])
        . ($o['expires_on'] ? '<span class="block text-xs font-normal opacity-70">Until ' . e(nice_date($o['expires_on'])) . '</span>' : '') . '</span>'
        . '<a class="shrink-0 text-sm font-semibold text-brand-700 hover:underline" href="' . e($link) . '">' . e($link_text) . '</a>'
        . '</figcaption></figure>';
}

// The pop-up poster viewer. Include once per page that shows posters.
function poster_viewer()
{
    return '<dialog id="poster-viewer" class="m-auto max-h-[92dvh] w-[min(92vw,40rem)] overflow-visible bg-transparent p-0 backdrop:bg-navy-950/80 backdrop:backdrop-blur-sm">'
        . '<div class="relative overflow-hidden rounded-3xl bg-white shadow-2xl">'
        . '<img alt="" class="block max-h-[70dvh] w-full object-contain bg-cloud-100">'
        . '<div class="p-4 sm:p-5"><div class="flex items-center justify-between gap-4"><p class="font-display text-lg font-semibold" data-caption></p>'
        . '<a class="btn btn-accent shrink-0" data-link href="visa.php">Apply now</a></div>'
        . '<p class="mt-2 text-sm text-navy-600" data-summary hidden></p></div>'
        . '<form method="dialog"><button class="absolute right-3 top-3 flex size-10 items-center justify-center rounded-full bg-white/90 text-navy-900 shadow-md hover:bg-white" aria-label="Close">' . icon('close', 'size-5') . '</button></form>'
        . '</div></dialog>';
}
