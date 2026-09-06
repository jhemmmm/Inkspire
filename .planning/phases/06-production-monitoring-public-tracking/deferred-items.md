# Deferred Items - Phase 6

Pre-existing issues discovered during execution that are out of scope for the
task that surfaced them. Logged per the executor's scope-boundary rule, not
fixed.

## From 06-01 (Task 1: migrations + enum extension)

- ~~**`app/Http/Controllers/FrontlineStaff/QueueEntryController.php:181`** —
  `jobOrderOutcomeToastMessage()`'s `match ($jobOrder->status)` expression was
  already non-exhaustive before this plan~~ — **Resolved in 06-04.** 06-04
  explicitly owns this call site (it wires `EnterProduction` into
  `applyIntakeOutcome()`, which is what widened the gap in the first place).
  Fixed by adding a `JobOrderStatus::ForProduction` arm (real behavior — the
  toast this call site can now legitimately reach) plus a `default` arm
  covering the remaining, structurally unreachable-here cases
  (`DesignApproved` and every later design/production stage a freshly
  created job order can never be at). `composer types:check` confirms this
  file no longer reports a `match.unhandled` error.
- **`app/Http/Requests/Cashier/CreateCreditRequestRequest.php:40`**,
  **`app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:42,64,68`**,
  **`app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20`** —
  pre-existing Larastan level 7 `property.notFound` / `method.notFound`
  errors on `(object|string)` typed values, all from Phase 5 (`4c09345`).
  Unrelated to any Phase 6 file; not touched by this plan.
