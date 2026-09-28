---
quick_id: 260928-ejs
mode: quick
status: approved
---

# Quick Task 260928-ejs: Redesign public home page to match the login page

Mock approved by the user on 2026-09-28.

The yellow "shop sign" page (1b2b97e) was reset off `main`, so `main` still carries the original
gradient/stock-photo page. Facts and tracking behaviour are taken from 1b2b97e; the presentation
is the approved mock.

## Task 1: Rebuild `resources/js/pages/Welcome.vue` from the approved mock

- bg-muted page, quiet header (Inkspire logo, in-page links, `welcome-track-link` pill).
- Hero: one `rounded-[20px]` card with the login's shadow.
    - White panel: CMYK bar, 40px primary rule, h1 "Know where your print is.", copy, aria-hidden
      status card (stages/blurbs from OrderProgress.vue, header as Tracking.vue) over a printer photo.
    - Royal-blue `#track` panel (first on mobile): pill, "Is my print ready?", tracking form. No second logo.
- Tracking form from 1b2b97e: normalise (trim, upper-case, spaces/en/em dashes to '-'), GET
  `trackingShow.url()` with `{ number }`, `preserveState: 'errors'`, focus field on error,
  always-mounted `role="alert"` wrapper around `<InputError id="welcome-tracking-error">`,
  aria-invalid/aria-describedby, "Checking…" while busy, year-aware placeholder, required.
- "What we print": SERVICES/PRICE_BOARD/TARP_SIZES as four photo cards plus a facts card.
- "How to order": ORDER_RULES as a label/body list in a white card with a CMYK-chart photo.
- Footer: shop name, Inkspire line, in-page links. Nothing staff-facing.
- Reuse Button, Input, Label, InputError. Tailwind utilities and tokens only; motion-reduce respected.

## Task 2: Assets and clean-up

- Add six Unsplash-License photos as WebP under `public/images/` (placeholders for shop photos).
- Delete `public/images/large-format-printing.jpg` and `public/images/printing-press.jpg` (unused).
- `--brand-*` tokens and `squarefoot-*.webp` never reached `main`: nothing to remove.

## Task 3: Test and verify

- WelcomePageTest: a malformed number sent from `/` redirects back to `/` with the `number` error.
- `npm run types:check`, `npm run check`, `php artisan test --compact tests/Feature/Public tests/Feature/AppearanceTest.php`.
- Browser at 375/768/1024/1280/1440: tracker above the fold at 375×667, no sideways scroll,
  "jo 2026 0001" → /track?number=JO-2026-0001, "2026-0001" shows the error and keeps the value,
  visible focus through the tab order, stays light with a saved dark appearance.
