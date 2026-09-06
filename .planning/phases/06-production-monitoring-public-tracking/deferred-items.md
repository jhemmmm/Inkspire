# Deferred Items - Phase 6

Pre-existing issues discovered during execution that are out of scope for the
task that surfaced them. Logged per the executor's scope-boundary rule, not
fixed.

## From 06-01 (Task 1: migrations + enum extension)

- **`app/Http/Controllers/FrontlineStaff/QueueEntryController.php:181`** —
  `jobOrderOutcomeToastMessage()`'s `match ($jobOrder->status)` expression was
  already non-exhaustive before this plan (missing `InConsultation`,
  `InDesign`, `PendingReview`, `DesignApproved` — confirmed by re-running
  `phpstan analyse` against the pre-Phase-6 enum). Adding this plan's four new
  `JobOrderStatus` cases (`ForProduction`, `Printing`, `QualityCheck`,
  `ReadyForPickup`) surfaces four more unhandled arms on the same
  already-broken match. Not fixed here — belongs to whichever later Phase 6
  plan owns Frontline-facing status copy (or a dedicated Phase 3/6 cleanup).
- **`app/Http/Requests/Cashier/CreateCreditRequestRequest.php:40`**,
  **`app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:42,64,68`**,
  **`app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20`** —
  pre-existing Larastan level 7 `property.notFound` / `method.notFound`
  errors on `(object|string)` typed values, all from Phase 5 (`4c09345`).
  Unrelated to any Phase 6 file; not touched by this plan.
