---
status: partial
phase: 05-pos-payments
source: [05-VERIFICATION.md]
started: 2026-09-05T01:38:35Z
updated: 2026-09-05T01:38:35Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Live PayMongo Sandbox QR Payment

expected: Provision `PAYMONGO_SECRET_KEY`/`PAYMONGO_PUBLIC_KEY`/`PAYMONGO_WEBHOOK_SIG` in `.env`, generate a GCash or Maya QR from the Cashier's Job Order Payment page, and complete a real test-mode payment. A scannable QR should render from a genuine PayMongo redirect URL; the job order shows Pending Confirmation; PayMongo's webhook (or a manual "Check Payment Status") flips it to Paid/Partially Paid.
result: [pending]

### 2. Webhook Payload Shape Against a Real Delivery

expected: With sandbox keys provisioned, trigger a real `payment.paid`/`payment.failed` webhook delivery from PayMongo's dashboard test-delivery feature. `PaymongoWebhookController` should correctly extract the `payment_intent_id`/outcome and resolve the correct `Transaction`, calling `ConfirmPaymentIntent` with the right outcome.
result: [pending]

### 3. Concurrent Credit Approval / Duplicate Credit Request Under Real Load

expected: Two simultaneous approve (or reject) requests for the same `AccountsReceivable` row, and two simultaneous On-Credit requests for the same job order. `lockForUpdate()` should serialize the requests so exactly one succeeds; the unique DB index on `accounts_receivable.job_order_id` should prevent a duplicate row even in a worst-case race.
result: [pending]

### 4. PayMongo Payment Intent Status Vocabulary

expected: Confirm PayMongo's real API only exposes `succeeded`/`cancelled` as terminal Payment Intent statuses relevant to reconciliation, with no other status (e.g. a distinct "expired") that should also be treated as terminal. `ReconciliationController::store()`'s three-way branch should cover every real outcome PayMongo can report.
result: [pending]

## Summary

total: 4
passed: 0
issues: 0
pending: 4
skipped: 0
blocked: 0

## Gaps
