# Quick Task 261003-vg0: Shared intake actions Summary

Counter intake extracted into `CreateJobOrder` and `OpenVisit` actions, an `O` (online) queue lane hidden from both queue listings, and the per-row intake form extracted into `JobOrderRowFields.vue`. This is a pure refactor with no behaviour change.

## Commits
- 78691b1: refactor: CreateJobOrder/OpenVisit actions, `QueueEntry::ONLINE_PREFIX`, lane exclusion, 3 new tests
- 70420f1: refactor: JobOrderRowFields.vue, `showPrices` on JobOrderPriceFields, NewVisit uses the component
- c653fde: fix (orchestrator review): `store()` passes the validated rows straight to `OpenVisit`; customer-audience placeholder on a design order

## What changed
- `CreateJobOrder` accepts either `file` (stored and validated) or a trusted `file_path` + `file_check` (Passed -> for_production, NeedsArtist -> intake with reason). Its docblock carries the enclosing-transaction requirement.
- `OpenVisit` wraps the queue entry, job orders and status sync in one transaction, and picks the lane unless a prefix is passed.
- `QueueEntryController` no longer holds quote/intake logic. `store()` passes the validated rows (which already carry each row's `UploadedFile`) to `OpenVisit`. `addJobOrder()` calls `CreateJobOrder` inside its transaction.
- The `O` lane is excluded in `QueueEntryController::index` and `Public\QueueDisplayController::index`.
- `JobOrderRowFields.vue` exports `JobOrderRow` and `emptyJobOrderRow`. It has `audience` (`staff` | `customer`) and `removable` props, and all data-test hooks are unchanged.
- `JobOrderPriceFields` has `showPrices` (default true), and `base_price` is optional.

## Verification
- `php artisan test --compact tests/Feature/FrontlineStaff tests/Feature/JobOrder tests/Feature/Public`: 246 passed. Existing tests were not edited; 4 tests were added (2 for CreateJobOrder, 1 each for the two queue listings).
- Larastan: 19 errors on the orchestrator's re-run, all pre-existing and none in a file this task touched.
- `npm run types:check` is clean. `npx vp check --fix` was run on the touched files only. Pint was run.

## Browser verification (orchestrator)
Driven in headless Chrome against a seeded SQLite copy, as Frontline Staff on New Visit, at 1366px and 375px:
- Type A / Type B switch in both directions (dropzone hides and returns).
- Service picked, then changed; the price recomputed for the new service (Blackout 20 x 3 x 5 x 2 = 600.00).
- Rush switch, file chosen in the dropzone, row added, removed and added again; the remove button only shows with more than one row.
- Submit created the visit: Type A with a passing file went to `for_production` with its quote and file, Type B stayed at `intake`.
- No console errors, no horizontal page overflow at either width, two-column container-query layout intact.

Not rendered: `audience="customer"`. Nothing uses it until the website order form (Part 3), where it gets its own browser check.

## Deviations
- Page remove helper is `removeRow` (not `removeJobOrderRow`).
- The component takes `specificationOptions` and builds the print-size options itself, so no new server prop was added.
- The executor's `store()` re-mapped each row's file with an `int $index` closure; removed in c653fde because it would throw on a non-numeric row key and the validated rows already carry the file.
- The `audience=customer` wording is the author's own copy, not rendered anywhere yet.
