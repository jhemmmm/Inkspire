---
quick_id: 260910-j7w
slug: unify-role-portal-layouts-shared-page-sh
date: 2026-09-10
---

# Quick Task 260910-j7w: Unify the role portal layouts

## What is actually wrong

28 of the 40 pages open with a byte-identical wrapper and 26 repeat the same
`<h1>` block. So the layout problems are not 28 separate problems — they are
five problems copied 28 times.

| #   | Problem                                                                                                                                   | Rule (ui-ux-pro-max)                     |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| 1   | `p-9` with no `max-width` — content spans a 27" monitor edge to edge, so table rows are unscannable                                       | `container-width`, `line-length-control` |
| 2   | `p-9` on phones too — no responsive inset                                                                                                 | `mobile-first`, `adaptive-gutters`       |
| 3   | `text-4xl` h1 (40px at the 18px root) restating the sidebar's active item and the breadcrumb, burning ~100px above the fold on every page | `content-priority`, `whitespace-balance` |
| 4   | `overflow-x-auto` on the page wrapper — a wide table drags the heading and filters sideways with it                                       | `horizontal-scroll`, `scroll-behavior`   |
| 5   | Card + empty-state + section-heading markup hand-rolled per page, drifting                                                                | `consistency`                            |

## Approach

Fix each once, apply 28 times. Five new components in `resources/js/components/`:

- **`PageContainer.vue`** — responsive inset (`p-4 sm:p-6 lg:p-8`), `max-w-[100rem]`,
  vertical rhythm. No `overflow-x-auto`.
- **`PageHeader.vue`** — `title`, optional `description`, `#actions` slot. Title
  drops to `text-2xl sm:text-3xl`. Actions row is where the per-page
  hand-rolled flex headers (UserManagement, Reports) converge.
- **`SectionHeading.vue`** — `h2` + description, replacing the repeated
  `text-[20px]` block. Keeps `h1 → h2` hierarchy intact.
- **`DataTableCard.vue`** — the card chrome plus `overflow-x-auto` **inside**
  the card, so a wide table scrolls in its own box (fixes #4).
- **`EmptyState.vue`** — the repeated centred title + muted description +
  optional action, for `TableEmpty` slots.

Then apply across all 28 pages, and give `frontline-staff/NewVisit.vue` a
bespoke pass on top — it is the page the user named, and its three-phase flow
(search → register → intake) currently reads as one undifferentiated column.

## Constraints

- Tailwind utility classes only. No inline styles, no CSS modules, no `<style>`
  blocks, no additions to `app.css`.
- Dark mode parity — every new surface uses existing semantic tokens
  (`bg-card`, `text-muted-foreground`, `border-border`), never raw colours.
- The royal-blue reskin's identity stays. This changes density and rhythm, not
  palette, radius or font.

## Verification

Layout-only, so the guard is the existing suite plus types/build: no test
asserts on DOM (0 `assertSee` in `tests/`), and every `data-test` hook must
survive. `npm run types:check`, `npm run build`, `php artisan test --compact`.
