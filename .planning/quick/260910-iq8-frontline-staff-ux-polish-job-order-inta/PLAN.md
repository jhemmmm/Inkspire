---
quick_id: 260910-iq8
slug: frontline-staff-ux-polish-job-order-inta
date: 2026-09-10
---

# Quick Task 260910-iq8: Frontline intake redesign + owner-managed spec catalog

Three related changes, driven by a reference design the user supplied.

## Part 1 — Form controls read as controls

`Input`, `Textarea` and `SelectTrigger` all render `bg-transparent`. The light
theme's page background is a near-white lavender, so a bare input on a page
(Frontline "New Visit" search box) is only distinguishable by its 1px border.

Fix: give the three controls an explicit `bg-card` surface (white in light,
the dark card slate in dark) so they sit _on_ the page rather than in it.

## Part 2 — Job order intake matches the reference

`resources/js/pages/frontline-staff/NewVisit.vue`:

- Type A / Type B become two selectable cards (icon, title, blurb, corner
  badge), not a bare vertical radio list. Still a real `RadioGroup` underneath
  so keyboard and screen-reader behaviour is unchanged.
- New "Print Specifications" section: Print Size, Quantity, Material,
  Deadline, plus the file input restyled as a dropzone-looking label.
- Print Size / Material are `<Select>`s fed by the new catalog (Part 3).

Backend: `job_orders` gains `print_size`, `material`, `quantity`, `deadline`.
All four are **nullable** — the existing intake tests post without them, and a
required field here would be a breaking change dressed up as a UI tweak.
Deadline is its own column, _not_ `due_at`: `EnterProduction` overwrites
`due_at` from the SLA config, which would silently erase a customer's date.

## Part 3 — Owner/Admin spec catalog

One `specification_options` table (`category` + `label` + `is_active` +
`sort_order`), not one table per spec kind. Adding a third spec kind later is
a seeder line, not a migration.

- `App\Enums\SpecificationCategory` (PrintSize, Material)
- `Owner\SpecificationOptionController` (index/store/update/destroy)
- `resources/js/pages/owner/Specifications.vue` + nav entry
- Routes under the existing `role:owner,admin` group

Job orders store the **label** as a snapshot string, not an FK: deleting a
retired print size must not blank out historical job orders.

## Tests

- Intake persists the new spec fields; omitting them still succeeds.
- Owner can create/rename/deactivate/delete options; non-owner roles 403.
