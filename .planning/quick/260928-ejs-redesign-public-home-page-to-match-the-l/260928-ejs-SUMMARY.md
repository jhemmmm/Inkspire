---
phase: quick-260928-ejs
plan: 01
status: complete
date: 2026-09-28
---

# Quick Task 260928-ejs: Summary

- `Welcome.vue` rebuilt from the approved mock as the login's sibling.
    - The page is `bg-muted` with one `rounded-[20px]` card that uses the login's shadow.
    - The white panel carries the CMYK bar, a primary rule, "Know where your print is." and copy.
      Below them, the real `OrderProgress` card sits over a banner-printer photo.
    - The royal-blue `#track` panel holds the tracker. It comes first until the hero splits at `lg`.
      The Squarefoot wordmark was dropped at the user's request.
    - "What we print" is four photo cards plus one facts card. "How to order" is a white card with a
      CMYK-chart photo.
    - The footer links only to sections on the page.
- The tracker behaviour comes from the reset shop-sign commit (1b2b97e): input normalisation,
  `preserveState: 'errors'`, an always-mounted alert, aria wiring, focus back on the field,
  "Checking…" and a year-aware placeholder. The panel reuses the login's `authInputClass`,
  `authSubmitClass` and `authErrorClass`.
- Photos: six Unsplash License placeholders served as WebP (22–77 KB). Their sources are listed
  in the page's docblock. `large-format-printing.jpg` and `printing-press.jpg` were deleted.
- `--brand-*` tokens and `squarefoot-*.webp` never reached `main`, so there was nothing to remove.

## Verification

- `WelcomePageTest`: a malformed number sent from `/` redirects back to `/` with the `number`
  error. Public and Appearance tests: 58 passed.
- `vue-tsc` and Pint pass. `npm run check` is clean apart from the STATE.md and
  260928-0gj-SUMMARY.md formatting that was already failing.
- Browser checks (headless Chrome over CDP against `localhost:8000`):
    - No sideways scroll at 375, 768, 1024, 1280 or 1440.
    - At 375×667 the submit button ends at 434px.
    - "jo 2026 0001" lands on `/track?number=JO-2026-0001`.
    - "2026-0001" stays on `/`, keeps the value, shows the error inside the alert, sets
      `aria-invalid` and puts focus back on the field.
    - The tab order runs header links, input, submit, then the footer links, with visible focus
      on each.
    - The page stays light with a saved `dark` appearance, and with `system` on a dark OS.
    - Reduced motion turns off the button's transition.
    - All seven images load.
