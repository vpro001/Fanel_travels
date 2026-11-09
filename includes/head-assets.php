<?php
// Fonts, Tailwind theme and shared styles. Included in <head> by header.php and admin-header.php.
// $base is '' for pages in the main folder and '../' for pages in admin/.
if (!isset($base)) $base = '';
?>
<link rel="preload" href="<?= $base ?>assets/fonts/plus-jakarta-sans-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/outfit-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $base ?>assets/css/fonts.css">
<link rel="icon" href="<?= $base ?>favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="<?= $base ?>assets/img/fanel/apple-touch-icon.png">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.5/dist/index.global.js"></script>
<style type="text/tailwindcss">
  /* Colours and fonts, taken from the Fanel logo (blue globe, orange swoosh). Explained in docs/DESIGN.md */
  @theme {
    --font-display: "Outfit", "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
    --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    --font-mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace;

    /* navy: text, dark sections, header */
    --color-navy-950: #06142e; --color-navy-900: #0a1f44; --color-navy-800: #0f2c5c; --color-navy-700: #1a3d78;
    --color-navy-600: #2c5291; --color-navy-500: #4c6ea8; --color-navy-400: #7891c2; --color-navy-300: #a9bbdc;
    --color-navy-200: #d0dbef; --color-navy-100: #e8eef8;

    /* brand: the bright globe blue, for buttons, links and active states */
    --color-brand-50: #eef4ff; --color-brand-100: #dbe7ff; --color-brand-300: #8fb2ff; --color-brand-400: #5b8cf7;
    --color-brand-500: #2f6bea; --color-brand-600: #1d55d0; --color-brand-700: #1844a8; --color-brand-800: #163a87;

    /* sun: the orange swoosh, for highlights and the main call to action */
    --color-sun-50: #fff6eb; --color-sun-100: #ffe9cc; --color-sun-300: #ffc27a; --color-sun-400: #ffa53d;
    --color-sun-500: #f7891a; --color-sun-600: #dc6e05; --color-sun-700: #a9540a; --color-sun-800: #7a3d08;

    /* cloud: cool light backgrounds */
    --color-cloud-50: #f7f9fd; --color-cloud-100: #eef3fa; --color-cloud-200: #dfe7f3; --color-cloud-300: #c8d4e6;

    --color-ok-700: #1f6b47; --color-ok-100: #dcefe4;
    --color-warn-700: #8c5b0c; --color-warn-100: #fbecd0;
    --color-bad-700: #b3261e; --color-bad-100: #fde3e0;

    --ease-out-soft: cubic-bezier(0.22, 1, 0.36, 1);
  }
  @layer base {
    body { @apply bg-white text-navy-900 font-sans antialiased; }
    h1, h2, h3 { font-family: var(--font-display); letter-spacing: -0.015em; text-wrap: balance; }
    :focus-visible { outline: 3px solid var(--color-brand-500); outline-offset: 2px; }
    a, button, summary, label[for], select { cursor: pointer; }
    .num { font-variant-numeric: tabular-nums; }
  }
  @layer components {
    .btn { @apply inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold transition duration-200 active:scale-[0.98]; }
    .btn-primary { @apply bg-brand-600 text-white shadow-sm shadow-brand-600/30 hover:bg-brand-700; }
    .btn-accent { @apply bg-sun-500 text-navy-950 shadow-[0_10px_24px_-10px_rgba(247,137,26,0.9)] hover:bg-sun-400; }
    .btn-dark { @apply bg-navy-900 text-white hover:bg-navy-800; }
    .btn-ghost { @apply text-navy-800 ring-1 ring-inset ring-navy-200 hover:bg-cloud-100; }
    .field-label { @apply block text-sm font-semibold text-navy-700; }
    .field { @apply mt-1.5 block w-full rounded-xl border border-cloud-300 bg-white px-3.5 py-2.5 text-base text-navy-900 placeholder:text-navy-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15; }
    .field-error { @apply mt-1 text-sm text-bad-700; }
    .card { @apply rounded-2xl bg-white shadow-[0_8px_30px_-12px_rgba(10,31,68,0.18)] ring-1 ring-navy-900/5; }
    .badge { @apply inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold; }
    .link { @apply font-semibold text-brand-700 underline decoration-brand-300 decoration-2 underline-offset-4 hover:decoration-brand-600; }
    .container-page { @apply mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8; }
    /* Small label above a heading: orange dot + text */
    .eyebrow { @apply inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] before:size-2 before:rounded-full before:bg-sun-500 before:content-['']; }
  }
</style>
<style>
  /* ---- Things Tailwind classes can't express neatly ---- */

  /* Orange swoosh under highlighted words, echoing the logo */
  .swoosh { position: relative; white-space: nowrap; }
  .swoosh::after { content: ""; position: absolute; left: -2%; right: -2%; bottom: -0.12em; height: 0.32em;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 20' preserveAspectRatio='none'%3E%3Cpath d='M2 15C60 4 140 2 198 8' fill='none' stroke='%23f7891a' stroke-width='6' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center / 100% 100%; }

  /* Poster gallery: scrolls sideways, snapping to each poster */
  .snap-row { scroll-snap-type: x mandatory; scrollbar-width: none; }
  .snap-row::-webkit-scrollbar { display: none; }
  .snap-row > * { scroll-snap-align: start; }
  /* A row that runs edge to edge but starts in line with the page content (same gutters as .container-page) */
  .snap-row-page { --gutter: 1rem; --edge: max(var(--gutter), calc((100vw - 80rem) / 2 + var(--gutter))); padding-inline: var(--edge); scroll-padding-inline: var(--edge); }
  @media (min-width: 640px) { .snap-row-page { --gutter: 1.5rem; } }
  @media (min-width: 1024px) { .snap-row-page { --gutter: 2rem; } }

  /* Gentle reveal as sections scroll into view (only when JS has run) */
  .js .reveal { opacity: 0; transform: translateY(24px); transition: opacity 700ms cubic-bezier(0.22, 1, 0.36, 1), transform 700ms cubic-bezier(0.22, 1, 0.36, 1); transition-delay: var(--delay, 0ms); }
  .js .reveal.is-visible { opacity: 1; transform: none; }

  /* Anyone who asked their device for less motion gets none */
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
    .js .reveal { opacity: 1; transform: none; }
  }
</style>
<script>document.documentElement.classList.add('js');</script>
