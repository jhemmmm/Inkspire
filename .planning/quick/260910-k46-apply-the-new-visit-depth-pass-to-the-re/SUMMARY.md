---
quick_id: 260910-k46
status: complete
date: 2026-09-10
---

# Summary

Task `260910-j7w` gave all 28 pages the shared _shell_; only New Visit got the
per-page depth pass. This did the rest.

## Wave 1 — one more component, then sweeps

**`StatCard.vue`** (4 pages, 12 tiles) — replaces the hand-rolled
`text-3xl font-bold` + label tiles. Carries `tabular-nums` so a row of tiles
keeps its digits on the same rails, and a `tone="attention"` used by the 90+
day ageing bracket. The three print/public tiles (Receipt, CollectionLetter,
Tracking) keep their own markup: a receipt is a document, not a dashboard.

Sweeps:

- **Filter forms** (AuditTrail, PerformanceReport) — were bare uncarded rows of
  fixed-width `w-40`/`w-48` controls that collided on a laptop. Now carded,
  `sm:grid-cols-2 lg:grid-cols-4`, full-width controls, actions on their own row.
- **Raw `<label class="text-sm font-semibold">` → the `Label` component**, so
  the `for`/`id` pairing is guaranteed rather than hand-maintained.
- **8 more fixed-width controls → `w-full`** in Expenses, AR Show and
  Specifications dialogs, which overflowed on narrow screens.
- **`tabular-nums` on the money paths.** On the payment breakdown one class on
  the wrapper does it — `font-variant-numeric` inherits — so every figure below
  it aligns. The Total row also gained a rule above it and dropped from
  `text-3xl` to `text-2xl`.
- **`ReportsWorkspace`** (shared by 4 Reports pages) — hand-rolled `h2` →
  `SectionHeading`.

Deliberately **not** extracted: a `FilterBar` component. Two consumers is under
the bar for a new abstraction; the same classes are applied twice instead.

## Wave 2 — per-page depth

- **Queue List** — the queue number is what staff read out loud, so it is now a
  filled tile rather than bare text. The empty state offers "New Visit" instead
  of dead-ending on "No queue entries yet today."
- **Job Order Payment** — Pricing and Payment were two full-width cards stacked
  in a 100rem column, so taking a payment meant scrolling past the whole
  pricing form. Now side by side on `xl`.
- **Expenses** — the "Record Expense" dialog moved into `PageHeader`'s actions
  slot; the total became a `StatCard` with the voided-entry tally folded into
  its hint.
- **Accounts Receivable (detail)** — the ageing and collection-status badges
  moved into the header's actions slot, and the customer/description line
  became the header's own description.
- **Artist Workspace** — added a status badge to the header (an artist opening
  the page had no cue whether they were in consultation, design or review) and
  dropped a wrapper div that re-declared `PageContainer`'s own `gap-6`.

## A correction worth recording

The five `PageHeader` descriptions written in `260910-j7w` for the Reports and
AR Index pages sat above the pages' _existing_ orientation copy, which the
mechanical pass had left in place. Reading them side by side, the existing copy
was more accurate — the Accounting Reports page shows "sales, expenses, and the
summary of both", not the "receivables, ageing and collection figures" that had
been written for it. All five now use the page's own wording.

## Verification

- `php artisan test --compact` — 538 tests, 531 passed, 7 skipped, 0 failed
- `npm run types:check` clean (it caught a wrong ageing-bracket key,
  `over_90` for `ninety_plus`, before it shipped), `npm run build` clean,
  `vp check` clean across 40 pages
- `data-test` hooks diffed against the pre-refactor set: none lost
- Constraint audit clean: no inline `style=`, no `<style>` blocks, no CSS
  modules, no `app.css` additions

## Audits that came back clean (no work needed)

- Keyboard access: only New Visit's rows were click-only, already fixed
- Submit feedback: 44 `:disabled="processing"` against 43 submit buttons
- Loading skeletons: no deferred props in use, so nothing to skeleton

## Not done

- **Not committed** — the tree still carries the in-flight reskin plus quick
  tasks `260910-iq8`, `260910-j7w` and this one, with no clean boundary.
- 5 of 12 hand-rolled empty states remain: interpolated or quote-containing
  copy that does not fit `EmptyState`'s string props.
