# Plan: artist design upload, payment-gated production, simpler production board

## Context

Three problems raised about the job order flow:

1. **Artists can only produce a design inside the built-in Photopea editor.** Artists who work in Photoshop have no way to hand in a finished file.
2. **An approved design goes straight to production with no payment check.** `DesignEditorController::approve()` calls `EnterProduction`, and the production board loads no payment data at all, so staff can print an order nobody has paid for.
3. **The production board is hard to use.** It has four stat cards, six tabs, a Rush-only switch, and per-row "Advance to X" / "Send Back to X" (with a mandatory reason dialog). Busy staff want one "Done" button.

What already works and needs no change: the cashier dashboard already lists an approved order for payment (`Cashier/DashboardController` includes `for_production`…`ready_for_pickup`, unpaid only), and the artist's approval toast already says "ready for pricing at the Cashier".

## Decisions (from the questions)

- Unpaid orders **stay visible on the production board, but locked**, with a payment badge and a filter.
- Board actions become **Start + Done**, plus **Undo**. Quality Check is no longer a step.
- **Assumption to confirm:** an order unlocks when `payment_status` is `partially_paid` (down payment), `paid`, or `on_credit` (Admin-approved). It is one constant; say so if a down payment should not be enough.
- **Assumption:** the board keeps its current scope (orders that reached production). Orders still in design are not added.

## Part 1 — Artist uploads a finished design

Reuse the existing send-for-review path; only the source of the file changes.

- [app/Http/Requests/Artist/SendForReviewRequest.php](app/Http/Requests/Artist/SendForReviewRequest.php): allow `mimes:png,jpg,jpeg` and add `max:` from the existing `SystemConfiguration::getInt('max_file_size_mb', 50)`. Update the PHPDoc (it says the canvas is the only producer).
- [resources/js/pages/artist/JobOrderWorkspace.vue](resources/js/pages/artist/JobOrderWorkspace.vue), Design card:
  - Add an "Upload a Design" file picker (`accept="image/png,image/jpeg"`) next to "Start from Blank Canvas", and also beside the editor once it is open.
  - A chosen file shows an instant preview (`URL.createObjectURL`), with "Send for Review" and "Choose a different file / use the editor instead" so the choice is reversible.
  - `sendForReview()` posts the chosen file if there is one, otherwise exports from Photopea as today. Show `sendForReviewForm.errors.file` with `InputError`.
  - While pending review, show the submitted design image above "Waiting on the client's verdict." (today the artist sees no image at that point). `design.initialImageUrl` already carries it.
- No change to `RecordDesignRevision`, `DesignEditorController::sendForReview`, the public review page, or Photopea reload: all already handle any browser image.

Not included: PSD/PDF/AI upload. They cannot be previewed by the artist or the customer review page. Artists export PNG/JPG from Photoshop.

## Part 2 — Payment gate on production

- [app/Models/JobOrder.php](app/Models/JobOrder.php): add `isClearedForProduction(): bool` (the three statuses above). Single source of truth for server guard and board.
- [app/Http/Controllers/ProductionStaff/ProductionStageController.php](app/Http/Controllers/ProductionStaff/ProductionStageController.php): `start` and `done` abort 422 "Awaiting payment — send the customer to the Cashier" unless cleared. Checked on the locked re-read, like the existing guards.
- [app/Http/Controllers/ProductionStaff/ProductionBoardController.php](app/Http/Controllers/ProductionStaff/ProductionBoardController.php): select `payment_status`, set `cleared_for_production` per row (same in-memory pattern as the `is_rush` widening). Amounts stay off the board. Update the "no payment hint" docblock.
- `EnterProduction` and its four call sites are untouched, and so is every payment writer.

Known trade-off: the due date still starts at approval, so an order that waits days for payment can show as overdue. Moving the clock to payment means hooking all three payment paths; add that only if it becomes a real complaint.

## Part 3 — Simpler production board

**Server** ([ProductionStageController.php](app/Http/Controllers/ProductionStaff/ProductionStageController.php), [routes/portals.php](routes/portals.php)): replace `advance` / `sendBack` with three body-less actions over one private helper that keeps the existing lock, cancelled/released guards and `ProductionLog` row:

| Action | From | To |
| --- | --- | --- |
| `start` | For Production | Printing |
| `done` | For Production, Printing, Quality Check | Ready for Pickup |
| `undo` | Ready for Pickup → Printing; Printing, Quality Check → For Production | |

Delete `AdvanceProductionStageRequest`, `SendBackProductionStageRequest` and `ProductionLogValidationRules` (no other users). Undo needs no typed reason; the log still records who and when. The `quality_check` enum case stays so existing rows, reports and the tracking page keep working.

**Page** ([resources/js/pages/production-staff/Dashboard.vue](resources/js/pages/production-staff/Dashboard.vue)):

- One tab row with counts replaces the stat cards, six tabs and Rush switch: **To Print** (default: cleared, not done) · **Awaiting Payment** · **Done** · **All**.
- Keep search (`TableFilterBar` + `useTableFilter`), the rush banner, the amber rush row, and the error alert.
- Columns: Job Order (with Rush badge) · Customer · Description · Payment · Stage · Due · Actions. Payment label from `paymentStatusLabel` in [resources/js/lib/jobOrders.ts](resources/js/lib/jobOrders.ts).
- Actions per row: locked → "Awaiting payment" (no buttons); For Production → **Start**, **Done**; Printing → **Done**, Undo; Ready for Pickup → "Awaiting release", Undo.
- Each tab's empty state says what the tab is for. Reuse `PageContainer`, `PageHeader`, `DataTableCard`, `EmptyState`.

Not included: a link to open the print file from the board. The board has never had one; say so if production needs it and it gets added as a signed link per row.

## Tests

- [tests/Feature/ProductionStaff/StageAdvancementTest.php](tests/Feature/ProductionStaff/StageAdvancementTest.php): rewrite for `start` / `done` / `undo` (transitions, log row, cancelled/released/locked-row guards, role check), plus "an unpaid order rejects start and done" and "a down payment, paid, or on-credit order is accepted".
- [tests/Feature/ProductionStaff/ProductionBoardTest.php](tests/Feature/ProductionStaff/ProductionBoardTest.php): replace "never leaks payment or pricing data" with "exposes `payment_status` and `cleared_for_production`, still not `total_amount`".
- [tests/Feature/Artist/SendForReviewTest.php](tests/Feature/Artist/SendForReviewTest.php): the "non-png fails" test becomes "a JPG is accepted" plus "a non-image (PDF) fails".

## Execution

Run through `/gsd-quick` (required by CLAUDE.md), one task and commit per part, no worktrees. Run `php artisan wayfinder:generate` after the route change and `vendor/bin/pint --dirty --format agent` after PHP edits.

## Verification

1. `php artisan test --compact tests/Feature/ProductionStaff tests/Feature/Artist tests/Feature/Cashier/ProductionCompatibilityTest.php tests/Feature/JobOrder/EnterProductionTest.php`
2. `npm run types:check` and `npm run check`
3. In the browser (seeded copy on :8011, per the saved setup), at desktop and 375px, both themes:
   - Artist: upload a JPG, see the preview, swap the file, send for review, see the image while pending, approve.
   - Production: the order shows under Awaiting Payment with no buttons.
   - Cashier: the order is listed; record a down payment.
   - Production: the order moves to To Print; Start, Done, then Undo each work; search and tabs filter correctly.
   - Frontline: the done order still raises the ready-for-pickup alert and release still demands full payment or credit.
