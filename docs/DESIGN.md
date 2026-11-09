# Design system — Fanel Travels LTD

Version 3 (2026-11-09). Built from the Fanel logo: a blue globe with an orange swoosh and the line "Connecting You to the World".

## Point of view

A bright, friendly Port Harcourt agency rather than an airline-terminal look. Royal navy and globe blue carry trust; the orange swoosh marks the one thing to do next. Shapes are soft (pill buttons, rounded cards), and real people and real flyers from the Fanel visa desk do the selling.

## Colour tokens

Defined in `includes/head-assets.php` (`@theme`).

| Token | Use |
|---|---|
| `navy-50…950` | Text (`navy-900`), muted text (`navy-500/600`), header (`navy-900`), footer (`navy-950`), dark sections |
| `brand-50…800` | Globe blue: primary buttons (`brand-600`), links (`brand-700`), active tabs, focus rings (`brand-500`) |
| `sun-50…800` | Logo orange: the main call to action (`btn-accent`, `sun-500` with `navy-950` text), the top contact bar, eyebrow dots, highlights. Orange text on white uses `sun-700` for contrast |
| `cloud-50…300` | Cool light backgrounds and borders |
| `ok` / `warn` / `bad` | Status only, always with text |

Never put white text on `sun-500` (contrast too low); use `navy-950`.

## Typography

- Headings: **Outfit** (variable, self-hosted). It has no ₦ glyph, so the font stack falls back to Plus Jakarta Sans for that one character.
- Body, prices and forms: **Plus Jakarta Sans** (variable, latin + latin-ext; latin-ext contains ₦).
- Booking references only: IBM Plex Mono.
- Eyebrow labels: `.eyebrow` (small, bold, uppercase, with an orange dot).
- `.swoosh` draws the logo's orange curve under a highlighted word (used once, in the homepage headline).

## Spacing and layout

- `.container-page`: `max-w-7xl`, gutters `px-4 / sm:px-6 / lg:px-8`.
- Homepage sections: `py-20 sm:py-28`. Inner pages: `py-10 sm:py-14`.
- Radius: `rounded-full` buttons and chips, `rounded-xl` fields, `rounded-2xl` cards, `rounded-3xl` feature panels.
- Mobile first. Check every page at 360px; touch targets at least 44px.

## Components

| Component | Classes / file | Notes |
|---|---|---|
| Button | `btn` + `btn-accent` (orange, main action) / `btn-primary` (blue) / `btn-dark` / `btn-ghost` | One orange action per view |
| Text input | `field-label` + `field` | Errors: `field-error` and `aria-invalid="true"` |
| Card | `card` | White, soft navy shadow, hairline ring |
| Header | `includes/header.php` | Orange contact bar (address, hours, phone, email) + navy bar with the logo and pill nav |
| Footer | `includes/footer.php` | Navy-950 with an orange swoosh edge, full logo, services, contact |
| Search box | `includes/search-box.php` + `assets/js/search.js` | White rounded card, pill tabs with icons, soft field boxes |
| Poster gallery | `includes/posters.php` (`visa_offers()`, `poster_card()`, `poster_viewer()`) | Sideways-scrolling row of the posts staff manage in Admin → Visa & holiday posts (table `visa_offers`); tapping opens a `<dialog>` viewer (`assets/js/site.js`) |
| Visa country card | `visa.php` | Picture (own upload in Visa destinations → else the country's first visa flyer, shown whole over a blurred copy → else the country code on the brand gradient), summary, processing time, and an "Apply: <type>" button per visa type |
| Floating contact | `includes/footer.php` | WhatsApp when the number is set in admin, otherwise "Call us" |
| Status badge | `badge` + `bg-ok-100 text-ok-700` etc. | Text always states the status |

## Icons

Inline SVG from `assets/icons/*.svg` via `icon('name')`, 24×24, `stroke="currentColor"`. Added in v3: `bed`, `palm`, `passport`, `shield`, `compass`, `close`.

## Images

- `assets/img/fanel/`: supplied by Fanel. `21.jpeg` is the logo; `logo-header.webp`, `logo-900.webp`, `logo-mark.png` and `favicon.ico` are cut out from it on a transparent background (they need a dark background behind them). The first visa and holiday posts use `-480/-800.webp` versions of these flyers; flyers staff upload later go to `assets/img/offers/` as `-480/-800.jpg`.
- **Do not use `03.jpeg` (Ethiopia) or `06.jpeg` (South Africa)**: they carry another agency's name ("The Flyzone Tourism") and its office addresses. `05.jpeg` duplicates `04.jpeg`.
- Other photos in `assets/img/` are Unsplash (see `IMAGE_CREDITS.md`). WebP, two widths, `loading="lazy"` below the fold.
