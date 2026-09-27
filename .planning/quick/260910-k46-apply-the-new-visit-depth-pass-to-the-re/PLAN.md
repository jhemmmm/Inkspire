---
quick_id: 260910-k46
slug: apply-the-new-visit-depth-pass-to-the-re
date: 2026-09-10
---

# Quick Task 260910-k46: the depth pass, on the remaining pages

Task `260910-j7w` gave all 28 pages the shared _shell_. Only New Visit got the
per-page depth pass. This does the rest.

## Audit findings

| Finding                                                                                                  | Where                                 | Rule                                 |
| -------------------------------------------------------------------------------------------------------- | ------------------------------------- | ------------------------------------ |
| Stat tile (`text-3xl font-bold` + label) hand-rolled 13x                                                 | 9 files                               | `consistency`                        |
| Filter forms are bare, uncarded, fixed-width (`w-40`/`w-48`) — they don't reflow and collide on a laptop | AuditTrail, PerformanceReport         | `mobile-first`, `whitespace-balance` |
| Raw `<label class="text-sm font-semibold">` instead of the `Label` component — no `for`/`id` guarantee   | AuditTrail, PerformanceReport         | `form-labels`                        |
| Money columns without `tabular-nums`, so digits jitter between rows                                      | accounting Dashboard, JobOrderPayment | `number-tabular`                     |
| Fixed-width selects inside dialogs overflow on narrow screens                                            | Expenses, AR Show, Specifications     | `horizontal-scroll`                  |
| `ReportsWorkspace` hand-rolls a `text-[20px]` h2                                                         | shared by 4 Reports pages             | `heading-hierarchy`                  |

Keyboard access and submit-feedback audits both came back clean (44
`:disabled="processing"` against 43 submit buttons), so neither needs work.

## Wave 1 — one more shared component, then sweeps

- **`StatCard.vue`** — number, label, optional icon and tone. Replaces the 9
  in-app hand-rolled tiles. The three print/public ones (Receipt,
  CollectionLetter, Tracking) keep their own markup: different context, and a
  receipt is a document, not a dashboard.
- Filter forms: responsive grid (`sm:grid-cols-2 lg:grid-cols-4`), full-width
  controls, real `Label`s, actions on their own row.
- `tabular-nums` on every money cell.
- Fixed widths → `w-full` inside a constrained parent.

**Not** extracting a `FilterBar` component: two consumers is under the bar for
a new abstraction. Same classes applied twice, extract on the third.

## Wave 2 — per-page depth

Ordered by daily use: QueueList, cashier JobOrderPayment, Expenses, AR
Index/Show, artist Dashboard and JobOrderWorkspace.

## Constraints (unchanged)

Tailwind utilities only — no inline styles, no `<style>` blocks, no CSS
modules, no `app.css` additions. Dark mode via semantic tokens. Every
`data-test` hook preserved.
