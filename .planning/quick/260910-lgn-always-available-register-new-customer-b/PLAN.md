---
quick_id: 260910-lgn
slug: always-available-register-new-customer-b
date: 2026-09-10
---

# Quick Task 260910-lgn: registering a customer can't depend on an empty search

## The defect

`NewVisit.vue:484` gates the register form on
`hasSearched && customers.length === 0`. Registration is therefore only
reachable when a search returns **nothing at all**.

Search "Santos" for a new customer named Pedro Santos, and Maria Santos comes
back. Results exist, so the form stays hidden — and there is no other route to
it on the page. The staff member's only escape is to search deliberate nonsense
to force a zero-result state. That is the shop's most common registration case
(a family or a company where the shop already knows someone else) hitting the
one branch that hides the form.

## Fix

A `Register New Customer` button beside `Search Customers`, visible for the
whole customer step rather than only after a failed search. It sits in the
search card, next to where the staff member has just typed a name and not
found who they wanted — not in the page header, because the primary action of
this page is queueing a visit, not creating a customer.

The zero-result auto-reveal stays: when a search genuinely finds nobody, the
form still opens by itself and the empty state still points at it. The button
adds a second route in, it does not replace the first.

Per CLAUDE.md's UI checklist:

- **Rule 2 (reversible)** — opening the form must be undoable, so it gets a
  Cancel that closes it again. Cancel is only offered when the form was opened
  deliberately; when it opened because a search found nobody, closing it would
  leave a dead end.
- **Rule 3 (not below the fold)** — clicking the button moves focus to the Name
  field, so the form announces itself instead of appearing silently further
  down a long page.

## Verification

Existing intake tests must stay green. Browser-driven, per rule 10: search a
term that returns results, click the button, confirm the form appears and takes
focus, register, and confirm the new customer is selected for the visit.
