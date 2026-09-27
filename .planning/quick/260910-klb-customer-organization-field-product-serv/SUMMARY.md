---
quick_id: 260910-klb
status: complete
date: 2026-09-10
---

# Summary

## 1. Customers can be organizations

`customers.organization`, nullable. Nullable rather than a `customer_type`
enum: a walk-in is a person who may or may not be buying for a company, and one
optional field covers both without forcing every existing row to pick a side.

Search matches it alongside name and contact number — staff look up "Ateneo",
not the contact's personal name — and the placeholder now says so. It shows as
a column in the results table and beside the name in the selected-customer bar.

## 2. Product / Service is the owner's real catalog

`PricingDatabaseSeeder` now carries the supplied price list: 37 walk-in media
and 12 plain-only lines, replacing eight invented placeholder rows.

Compound prices in the source (`100 / 150 Installation`, `150 / 250 b2b`,
`400/sqft + 50/pc CB`) store the plain print figure in `base_price` with the
qualifier in `unit`, so the Cashier reads the real terms at pricing time.
Modelling installed-vs-supplied as its own priced option is a pricing-engine
change, not an intake one.

Intake's free-text input is now a `Select` showing each service with its price
and unit. Picking one writes **both** `description` (so the queue list,
production board and receipt keep working unchanged) and `pricing_entry_id` (so
the Cashier's pricing step arrives pre-filled instead of re-picking).

## 3. Type A routing now depends on the _kind_ of failure

The old check compared the DPI in the file's own metadata against a flat 300.
That is the wrong test for a large-format shop twice over: most exported files
report 72 DPI whatever their real pixel count, and a DPI number means nothing
without a print size.

It now measures **effective DPI** — pixels along the long edge ÷ printed inches
along the long edge — against a configurable floor (`large_format_minimum_dpi`,
default 100). A test pins the point: the same 1000px file passes on a business
card and fails on a 3x6ft tarpaulin. The action already called `getimagesize()`
and discarded the result; this uses it.

`specification_options` gained `width_inches` / `height_inches`, seeded for
every print size and editable by the Owner, with a "Printed size" column that
says outright when a size has none and therefore skips the check.

The outcome is now three-way, which is the substance of the request:

| Result                       | Goes to                                                  | Reasoning                                                                             |
| ---------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Passes                       | Production, and the Cashier dashboard for payment        | Unchanged                                                                             |
| Below the DPI floor          | **Artist pool** (`Intake`), reason attached as the brief | Exactly "quality control, improvement and such" — an artist can upscale or rebuild it |
| Wrong format / over size cap | `ValidationFailed`, replace at the counter               | No artist can turn a `.xyz` into artwork; only the customer has the real file         |

Splitting the two failure modes was a judgement call beyond the literal
request: routing a `.docx` to an artist would waste their time on something
only the customer can fix. `Intake` was already a replaceable status, so the
counter can still swap in a better file for a low-res job order.

## 4. Type B carries the client's own words

`job_orders.client_notes`, captured at intake and shown to the artist as a Job
Brief card alongside print size, material and quantity — plus the validation
reason when there is one.

Deliberately **not** reusing `consultation_notes`: that column is the artist's
own working record, written from their workspace, and sharing one column would
let an artist's edit silently overwrite what the customer actually asked for.

## Catalog hygiene

Both seeders now retire, by name, the placeholder rows an earlier version of
themselves created — 8 pricing entries, 6 materials, 3 print sizes. Retired by
explicit name rather than "anything not in my list" so an Owner's own additions
survive a re-seed, and deactivated rather than deleted because job orders carry
`pricing_entry_id`.

This mattered more than housekeeping: the three stale print sizes
("A4 (210x297mm)" and friends) had no dimensions, so leaving them offered would
have silently opted their job orders out of the new file check — and each
near-duplicated a real entry, an easy mis-pick at the counter.

Active catalog is now 49 services, 17 print sizes (only "Custom Size" without
dimensions, by design) and 14 materials.

## Verification

- `php artisan test --compact` — 551 tests, 544 passed, 7 skipped, 0 failed
- 19 new tests: 10 rewritten in `ValidateJobOrderFileTest` for the new
  three-way contract, 9 new in `IntakeCatalogAndRoutingTest` covering all four
  behaviours including both Type A failure routes
- `npm run types:check`, `npm run build`, `vendor/bin/pint` all clean

`ValidateJobOrderFileTest` was rewritten rather than extended — its old cases
asserted `$outcome['passed']` and a `resolveDpi()` helper that the new contract
removes. Every behaviour it covered that still exists (format rejection, size
cap, vector pass-through) is still covered.

## Flagged, not built

**Owner CRUD over `pricing_database`.** Closing Product/Service to a catalog
makes this the natural next step — the Owner can currently edit print sizes and
materials but not the price list itself, so a new service or a price change
needs a developer. It is its own screen and was not part of this request.
