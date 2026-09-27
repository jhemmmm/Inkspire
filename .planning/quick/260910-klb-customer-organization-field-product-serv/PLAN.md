---
quick_id: 260910-klb
slug: customer-organization-field-product-serv
date: 2026-09-10
---

# Quick Task 260910-klb: four intake corrections from the owner

## 1. Customers can be organizations

`customers` gains a nullable `organization`. Nullable, not a
`customer_type` enum: a walk-in is a person who may or may not be buying on
behalf of a company, and one optional field covers both readings without
making every existing row pick a side. Search matches it alongside name and
contact number, since staff will look up "Ateneo" not the contact's name.

## 2. Product / Service becomes the real catalog

The owner's walk-in price list (photo supplied) replaces the eight invented
seed rows in `PricingDatabaseSeeder`: 37 walk-in media plus 12 plain-only
lines.

Compound prices in the source list (`100 / 150 Installation`,
`150 / 250 b2b`, `400/sqft + 50/pc CB`) are stored as `base_price` = the
print-only figure, with the qualifier carried in `unit` so the cashier reads
it at the point of pricing. Modelling installed-vs-supplied as its own column
is a pricing-engine change, not an intake change, and is out of scope here.

Intake's free-text "Product / Service" input becomes a `Select` over the
active catalog. Picking an entry writes **both** `description` (so every
existing screen keeps working) and `pricing_entry_id` (so the Cashier's
pricing step arrives pre-filled instead of re-picking from scratch).

## 3. Type A routing depends on the _kind_ of failure

Today `ValidateJobOrderFile` checks absolute EXIF DPI against a flat 300. That
is the wrong test for large format twice over: most files report 72 DPI
regardless of their real pixel count, and 300 DPI is meaningless without
knowing how big it will be printed. A 2000px file is superb on a business
card and unusable on a 3x6ft tarpaulin.

Replace it with **effective DPI** — pixel width ÷ printed width in inches —
which is what actually determines whether a print looks sharp. The action
already calls `getimagesize()` and throws the result away; this uses it.

`specification_options` gains nullable `width_inches` / `height_inches`, seeded
for the print sizes and editable by the Owner. A print size with no dimensions
recorded skips the effective-DPI test rather than guessing.

Then split the outcome, which is the substance of the request:

| Failure                            | Route                                               | Why                                                                                                    |
| ---------------------------------- | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Wrong format, or over the size cap | `ValidationFailed` — replace at the counter         | Nobody can turn a `.docx` into artwork; only the customer has the real file                            |
| Effective DPI below threshold      | Artist pool (`Intake`), reason recorded             | This is exactly "quality control, improvement and such" — an artist can upscale, redraw or clean it up |
| Passes                             | Unchanged: `ReadyForProduction` → `EnterProduction` | Already lands on the Cashier dashboard for payment and on the production board                         |

## 4. Type B carries the client's own words

`job_orders.client_notes` (nullable text), captured at intake and shown to the
artist. Deliberately **not** `consultation_notes`: that column is the artist's
own record written in their workspace, and overloading it would let an artist's
edit overwrite what the customer actually asked for.

## Out of scope (flagged, not built)

Owner CRUD over `pricing_database`. Closing the Product/Service field to a
catalog makes that the natural next step, but it is its own screen and the
owner did not ask for it here.

## Verification

`php artisan test --compact`, `npm run types:check`, `npm run build`,
`vendor/bin/pint`. New tests for each of the four behaviours, including both
Type A failure routes.
