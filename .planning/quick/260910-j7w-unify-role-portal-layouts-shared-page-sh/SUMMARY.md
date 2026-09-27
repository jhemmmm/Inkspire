---
quick_id: 260910-j7w
status: complete
date: 2026-09-10
---

# Summary

## Diagnosis

28 of 40 pages opened with a byte-identical wrapper and 26 repeated the same
`<h1>`. The layout problems were not 28 problems — they were five problems
copied 28 times. So the fix was five components, applied everywhere, not 28
bespoke rewrites.

## The five components

| Component        | Pages | Replaces                                                    |
| ---------------- | ----- | ----------------------------------------------------------- |
| `PageContainer`  | 28    | the identical `flex h-full … overflow-x-auto … p-9` wrapper |
| `PageHeader`     | 25    | the `text-4xl` `<h1>` block                                 |
| `SectionHeading` | 4     | ad-hoc `text-[20px]` / `text-xl` section `<h2>`s            |
| `DataTableCard`  | 14    | the bare `bg-card … rounded-xl border shadow-sm` table card |
| `EmptyState`     | 8     | the hand-rolled centred empty-state block                   |

## What actually changed for the user

1. **Content no longer spans the monitor.** `max-w-[100rem]` and a responsive
   inset (`p-4 sm:p-6 lg:p-8`) replace an unconstrained `p-9`. Table rows on a
   27" display no longer make the eye track 2000px to pair a job order with
   its status, and phones get a 16px inset instead of 40px.
2. **~60px of vertical space back on every page.** The `text-4xl` heading (40px
   at the 18px root) restated both the sidebar's active item and the breadcrumb
   directly above it. Now `text-2xl sm:text-3xl`, with a one-line description
   that says something the sidebar doesn't.
3. **Wide tables scroll inside their card.** `overflow-x-auto` moved from the
   page wrapper to `DataTableCard`. Scrolling right to reach an Actions column
   no longer drags the page heading and filter row off-screen.
4. **Every page says what it is for.** All 25 `PageHeader`s carry orientation
   copy; the cashier, accounting and production dashboards previously jumped
   straight from a title to an unlabelled table.

## New Visit (the page called out)

Beyond the shared pass:

- **A step rail** — Customer → Job Orders → Queue Number, as an `<ol>` with
  `aria-current="step"`. The three phases were one undifferentiated column.
- **An escape route off a wrong customer.** `clearCustomer()` plus a "Change"
  button. Previously nothing ever cleared `selected` short of a page reload —
  picking the wrong customer meant starting over.
- **The customer becomes a one-line bar**, not a full card, keeping the job
  order form above the fold.
- **Sticky action bar.** With three job orders open, "Add to Queue" sat a full
  screen below the last field.
- **Search in a card**, with a full-width responsive input replacing a fixed
  `w-80`, and the empty-query message promoted to `role="alert"`.
- **Results rows are keyboard-reachable** (`tabindex`, `@keyup.enter`) and have
  a hover state — they were click-only.
- **Register form goes two-column** on `md`, halving its height.
- **The queue number reads as the payoff** it is: a large tabular-nums tile
  with "Give this number to {name}".

## Verification

- `php artisan test --compact` — 538 tests, 531 passed, 7 skipped, 0 failed
- `npm run types:check` clean, `npm run build` clean, `vp check` clean
- Every `data-test` hook diffed against HEAD: none dropped by this refactor
- Constraint audit clean: no inline `style=`, no `<style>` blocks, no CSS
  modules, no additions to `app.css` — Tailwind utilities only
- Dark mode: every new surface uses semantic tokens (`bg-card`, `border-border`,
  `text-muted-foreground`), no raw colours
- Height chain re-checked: `SidebarProvider` is `min-h-svh` with no
  `overflow-y-auto` in the chain, so the body scrolls and the new
  `sticky bottom-0` action bar anchors to the viewport

## Not done

- **Not committed** — the tree still carries the in-flight `260910-fup` reskin
  plus quick task `260910-iq8`, with no clean boundary between them.
- `public/Tracking.vue` keeps the old `text-4xl` header. It is the
  unauthenticated customer QR page, not a role portal, and never used the
  portal wrapper — a different context that should not inherit the staff shell.
- 5 of 12 hand-rolled empty states still stand: their copy is interpolated or
  contains quotes, so it does not fit `EmptyState`'s string props.
