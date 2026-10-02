# Quick Task 261003-1cb Summary

Artist PNG/JPG upload, payment-gated production start/done/undo, and a rewritten production board.

## Commits
- c5b1197 Task 1: artist uploads a finished PNG/JPG design
- 824d210 Task 2: payment gate and start/done/undo (server + tests)
- 56da31f Task 3: rewrite the production board page

## What changed
- `SendForReviewRequest`: `image`, `mimes:png,jpg,jpeg`, `max` = `max_file_size_mb` x 1024 KB.
- `JobOrderWorkspace.vue`: Upload a Design picker (also beside the open editor), object-URL preview (revoked on change/unmount), Send for Review, Choose a different file, Use the editor instead, `InputError` for `file`, submitted image shown while pending review.
- `JobOrder::isClearedForProduction()` backed by one private constant (PartiallyPaid, Paid, OnCredit).
- `ProductionStageController`: `start`, `done`, `undo` over one private locked transition helper; start/done 422 "Awaiting payment" on the locked row; undo ungated, no reason.
- Board rows carry `payment_status` and `cleared_for_production`; `total_amount` never selected.
- Routes `job-orders.start|done|undo` replace advance/send-back. Deleted `AdvanceProductionStageRequest`, `SendBackProductionStageRequest`, `ProductionLogValidationRules` (no references remain).
- `Dashboard.vue` rewritten: tabs To Print / Awaiting Payment / Done / All with counts, Payment column, per-row Start/Done/Undo, search kept, rush banner and amber row kept, failed-move alert kept (shows "Awaiting payment").

## Verification results
- `php artisan test --compact tests/Feature/ProductionStaff tests/Feature/Artist tests/Feature/Cashier/ProductionCompatibilityTest.php tests/Feature/JobOrder/EnterProductionTest.php`: 150 passed, 0 failed (788 assertions).
- Task 1 run (Artist): 78 passed. Task 2 run (ProductionStaff + compat + EnterProduction): 72 passed.
- `vendor/bin/pint --dirty --format agent`: passed.
- `npm run types:check` (vue-tsc): clean.
- `npx vp check --fix <edited files>`: clean for the three edited Vue files.
- `npm run check` (verify only): reports formatting issues in 28 files, none of them files I edited (pre-existing: e.g. AGENTS.md, boost.json, compose.yaml). Not touched.
- `composer types:check` (Larastan) not run.

## NOT browser-verified
I did not run any browser or server (per instructions). Interactions most needing a check:
1. Artist: pick a JPG via Upload a Design, see the preview, "Choose a different file" re-opens the picker and swaps, "Use the editor instead" clears; Send for Review; submitted image visible while pending review; a PDF/oversized file shows the `file` error. Hidden `sr-only` file input is triggered by buttons (keyboard-reachable via the buttons).
2. Artist: the Upload a Design button next to an already-open Photopea editor, and that choosing a file there swaps the editor view for the preview.
3. Production board: unpaid order appears under Awaiting Payment with no buttons; after a down payment it moves to To Print; Start, Done, Undo each work and update counts; search filters; tabs wrap at 375px; both themes; the 5-second poll does not reset the active tab.
4. Quality Check legacy rows show Done and Undo.

## Deviations
- None from the plan's intent. Notes: the `Urgency` column was dropped (Rush badge now sits in the Job Order cell, per the plan's column list); a ready_for_pickup row that is somehow not cleared shows "Awaiting payment" with no Undo, as the plan specifies.
- Wayfinder output is gitignored, so nothing generated was committed.
- The old "Rush only" switch and stat cards were removed as specified.

## Known Stubs
None.

## Threat Flags
None beyond the plan's threat model (T-1cb-01..04 mitigated and tested; T-1cb-02/03 covered by locked-row cancellation and payment-reversal tests).

## Orchestrator review and browser verification (a7e1d8e)

The executor's run was reviewed and then driven in headless Chrome against a seeded SQLite copy on :8011. Four defects were found that the tests did not catch, and fixed in a7e1d8e:

- The board was wider than its card below 1536px, so Start, Done and Undo scrolled out of view. It is now five columns (Job Order with customer beneath, Description wrapping, Status holding stage and payment badges, Due on two lines, Actions). This deviates from the plan's seven-column list.
- Picking an upload while Photopea was open unmounted the editor and lost unsaved work. The editor is now hidden with `v-show`, not unmounted.
- A Ready for Pickup order that is unpaid read "Awaiting payment" with no Undo and was counted in both Awaiting Payment and Done. It now sits in Done only, with Undo. The "Awaiting release" text was dropped; the stage badge already says Ready for Pickup.
- A tall upload preview pushed Send for Review below the fold; the preview is capped at 60vh.

Observed working in the browser:

- Artist: pick a JPG, preview loads, swap to a PNG, back out to the editor, a PDF is refused with "The file field must be an image.", a JPG is sent and shown while pending review, approval locks it. With the editor open, an upload preview keeps the same Photopea iframe and returns to it.
- Production: an approved unpaid order sits in Awaiting Payment with no buttons; a direct PATCH to `done` returns 422. After a 400 down payment recorded through the Cashier route it moves to To Print. Start, Undo, Done, Undo, and Done by keyboard Enter all work; tab counts update; the active tab survives the 5-second poll; search and Clear search work.
- Cashier dashboard lists the approved order. Frontline dashboard lists it once Done.
- Table width: no hidden action buttons at 1280, 1366, 1440 and 1920px with the sidebar open, or at 1024px with it collapsed. No page-level horizontal scroll at any width from 375px. Both themes checked.

Known limits:

- Below 1280px with the sidebar open (and on phones) the table scrolls inside its card and the Actions column is off-screen until scrolled.
- Job order 25 in the demo seed is `in_design` with no design file, so "Start from Blank Canvas" flashes "not ready to start a design" there. That is seed data, not this change.

Final test run: 175 passed (ProductionStaff, Artist, ProductionCompatibility, EnterProduction, ReadyForPickupAlert, ReleaseGate). `npm run types:check` clean; `npx vp check` clean on both edited pages. The full suite has not been run.
