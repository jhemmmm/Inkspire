---
quick_id: 260910-lgn
status: complete
date: 2026-09-10
---

# Summary

## The defect

`NewVisit.vue` gated the register form on
`hasSearched && customers.length === 0`, so registration was reachable only
when a search returned **nothing at all**.

Search "Santos" for a new customer named Pedro Santos and Maria Santos comes
back. Results exist, the form stays hidden, and there is no other route to it
on the page — the only escape was searching deliberate nonsense to force a
zero-result state. The shop's most common registration case (a family, or a
company where the shop already knows a different contact) landed on the one
branch that hides the form.

## Fix

A `Register New Customer` button beside `Search Customers`, available for the
whole customer step. It sits in the search card, next to where the staff member
has just typed a name and not found who they wanted — not in the page header,
because this page's primary action is queueing a visit, not creating a
customer.

The zero-result auto-reveal is unchanged: a search that genuinely finds nobody
still opens the form by itself, and the empty state still points at it. The
button adds a second route in rather than replacing the first.

Per CLAUDE.md's UI checklist:

- **Rule 2 (reversible)** — Cancel closes the form again, but only when it was
  opened deliberately. When it opened because a search found nobody, a Cancel
  would leave the staff member holding results they already know are wrong and
  no way forward.
- **Rule 3 (not below the fold)** — clicking the button scrolls to the form and
  focuses Name, so it announces itself instead of appearing silently down a
  page already full of results.

## Verification

Browser-driven per rule 10 — headless Chrome over the CDP driver, real pointer
events, against a running `artisan serve`:

| #     | Check                                                                              | Result |
| ----- | ---------------------------------------------------------------------------------- | ------ |
| 1–2   | Search returns 81 results, form correctly hidden                                   | ✓      |
| 3     | Register button present anyway                                                     | ✓      |
| 4–5   | Click reveals the form, focus lands on Name                                        | ✓      |
| 6     | Cancel offered                                                                     | ✓      |
| 7–9   | Registering redirects with `?customer=102`, selects them, job order form reachable | ✓      |
| 10    | Cancel closes the form                                                             | ✓      |
| 11–12 | Zero-result search still auto-opens, and offers no Cancel                          | ✓      |

`php artisan test --compact` — 551 tests, 544 passed, 7 skipped, 0 failed.
`types:check`, `build` and `vp check` clean.

**No new Pest test.** The change is a client-side visibility gate with no
server behaviour to assert, and the project has no component-test harness
(no vitest). The registration endpoint it reveals is already covered by
`IntakeCatalogAndRoutingTest`. Adding a feature test here would assert Laravel
still renders a page, not that the button works — the browser run above is the
real coverage.

## Not done

Still uncommitted; six quick tasks now sit on top of the reskin.
