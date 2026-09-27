---
quick_id: 260910-iq8
status: complete
date: 2026-09-10
---

# Summary

## Part 1 — Form controls read as controls

`Input`, `Textarea` and `SelectTrigger` rendered `bg-transparent`. Against the
light theme's near-white lavender page background a bare input was legible only
by its 1px border. All three now carry `bg-card`; the existing
`dark:bg-input/30` still wins in dark mode, so dark is unchanged.

## Part 2 — Job order intake redesigned

`resources/js/pages/frontline-staff/NewVisit.vue`:

- Type A / Type B are two selectable cards (icon tile, title, corner badge,
  blurb). Built on native `<input type="radio">` + `has-[:checked]:` /
  `peer-checked:` rather than a styled `RadioGroupItem` — keeps arrow-key group
  navigation and screen-reader semantics for free. Verified both variants are
  emitted into the built CSS.
- New "Print Specifications" panel: Print Size, Quantity, Material, Deadline.
  Print Size and Material are selects fed by the Part 3 catalog. Deadline is a
  native `<input type="date">` floored at today, computed from local date parts
  (`toISOString()` would have rendered the UTC day and rejected a Manila-evening
  walk-in's "today").
- The Type A file input became a real dropzone — dashed target, drag-and-drop,
  filename + size readback once a file is attached.

Backend: `job_orders` gained `print_size`, `material`, `quantity`, `deadline`,
all nullable, wired through both intake paths (`store` and `addJobOrder`).

Two decisions worth keeping:

- `deadline` is its own column, **not** `due_at`. `EnterProduction` overwrites
  `due_at` from the SLA config the moment a Type A file passes validation, which
  would have erased the date the customer was actually promised. Covered by a
  test.
- Every specification is optional. Requiring them would reject the
  consultation-first Type B walk-in the queue exists to handle, and would have
  been a breaking API change dressed up as a UI tweak.

## Part 3 — Owner/Admin print specification catalog

One `specification_options` table (`category` + `label` + `is_active` +
`sort_order`) rather than a table per spec kind — a third kind is a seeder line,
not a migration.

- `App\Enums\SpecificationCategory`, `SpecificationOption` model + factory
- `SpecificationOptionSeeder` (8 print sizes, 7 materials), wired into
  `DatabaseSeeder`
- `Owner\SpecificationOptionController` (index/store/update/destroy) under the
  existing `role:owner,admin` group
- `resources/js/pages/owner/Specifications.vue` — per-category table with
  inline rename, an offered/retired switch, delete-with-confirmation, and an
  add form per category
- Nav entry "Print Specifications"

Job orders snapshot the **label**, not an FK. Deleting a retired print size
therefore cannot blank out historical orders — asserted in a test.

## Verification

- `php artisan test --compact` — 538 tests, 531 passed, 7 skipped, 0 failed
- 19 new tests across `Owner/SpecificationCatalogTest.php` and
  `FrontlineStaff/JobOrderPrintSpecificationsTest.php`
- `npm run types:check` clean, `npm run build` clean, `vendor/bin/pint` clean

## Not done

- **Not committed.** The working tree already carried 97 modified files from
  the in-flight `260910-fup` reskin, with no clean boundary between that work
  and this. Staging selectively would have produced a misleading commit.
- Larastan could not be run: `vendor/bin/phpstan` fails in this environment
  with `Undefined constant Larastan\Larastan\LARAVEL_VERSION`, pre-existing and
  unrelated to this change.
- No browser screenshot: the chrome-devtools MCP server targets Chrome on the
  Windows host, unreachable from WSL. Verified via built-CSS inspection instead.
- The reference design's "Via USB" / "Via Email" buttons were skipped — nothing
  backs them server-side.
