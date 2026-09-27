---
phase: 05
slug: pos-payments
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-04
---

# Phase 05 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property               | Value                                                             |
| ---------------------- | ----------------------------------------------------------------- |
| **Framework**          | Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 |
| **Config file**        | `tests/Pest.php` — `RefreshDatabase` bound globally to `Feature/` |
| **Quick run command**  | `php artisan test --compact --filter={TestName}`                  |
| **Full suite command** | `php artisan test --compact`                                      |
| **Estimated runtime**  | ~30-60 seconds (full suite)                                       |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={TouchedTestName}`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement       | Threat Ref | Secure Behavior                                           | Test Type    | Automated Command                                          | File Exists | Status     |
| ------- | ---- | ---- | ----------------- | ---------- | --------------------------------------------------------- | ------------ | ---------------------------------------------------------- | ----------- | ---------- |
| TBD     | TBD  | 0    | POS-01            | —          | Cashier computes price from catalog + rush + discount     | feature      | `php artisan test --compact --filter=PricingComputation`   | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-02            | —          | Cash/Bank Transfer payment links to exactly one job order | feature      | `php artisan test --compact --filter=RecordPayment`        | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-03            | T-05-01    | GCash/Maya → Pending Confirmation → webhook confirms      | feature      | `php artisan test --compact --filter=PaymongoWebhook`      | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-04            | —          | Manual reconciliation check, Cashier or Accounting        | feature      | `php artisan test --compact --filter=Reconciliation`       | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-05            | —          | Down payment + balance tracking                           | feature      | `php artisan test --compact --filter=DownPayment`          | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-06            | —          | Digital receipt generation                                | feature      | `php artisan test --compact --filter=Receipt`              | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-07            | —          | Cancellation fee, netted against down payment (D-05)      | feature      | `php artisan test --compact --filter=CancellationFee`      | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-08            | —          | On-Credit request → Owner approve/reject → posts to AR    | feature      | `php artisan test --compact --filter=OnCredit`             | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | POS-09            | —          | Release/hand-over blocked until paid/active-credit        | feature      | `php artisan test --compact --filter=ReleaseGate`          | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | Idempotency       | T-05-02    | Webhook + reconciliation racing never double-credits      | unit/feature | `php artisan test --compact --filter=ConfirmPaymentIntent` | ❌ Wave 0   | ⬜ pending |
| TBD     | TBD  | TBD  | Webhook signature | T-05-01    | Invalid/missing signature rejected; CSRF exclusion works  | feature      | `php artisan test --compact --filter=WebhookSignature`     | ❌ Wave 0   | ⬜ pending |

_Task IDs, plan IDs, and wave numbers to be filled in by the planner once PLAN.md files exist._

---

## Wave 0 Requirements

- [ ] `tests/Feature/Cashier/PricingComputationTest.php` — POS-01
- [ ] `tests/Feature/Cashier/RecordPaymentTest.php` — POS-02, POS-05
- [ ] `tests/Feature/Webhooks/PaymongoWebhookTest.php` — POS-03, signature verification, CSRF-exclusion check
- [ ] `tests/Feature/Cashier/ReconciliationTest.php` — POS-04
- [ ] `tests/Unit/Actions/ConfirmPaymentIntentTest.php` — idempotency guard, the highest-value test in this phase given the "highest pitfall density" flag in STATE.md
- [ ] `tests/Feature/Cashier/ReceiptTest.php` — POS-06
- [ ] `tests/Feature/Cashier/CancellationFeeTest.php` — POS-07, D-05 netting math
- [ ] `tests/Feature/Owner/CreditApprovalTest.php` — POS-08
- [ ] `tests/Feature/Cashier/ReleaseGateTest.php` — POS-09
- [ ] `database/factories/PricingEntryFactory.php`, `TransactionFactory.php`, `AccountsReceivableFactory.php` — new model factories, following `JobOrderFactory`'s existing pattern
- [ ] PayMongo test-mode fixture/mock strategy for feature tests (recommend `Http::fake()` around the package's Guzzle client) — decided during planning

---

## Manual-Only Verifications

| Behavior                                                                                              | Requirement | Why Manual                                                                                                            | Test Instructions                                                                                                                                        |
| ----------------------------------------------------------------------------------------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Real PayMongo sandbox webhook round-trip (QR scan → actual GCash/Maya sandbox app → webhook delivery) | POS-03      | Requires a live PayMongo sandbox account and an actual GCash/Maya sandbox app interaction — cannot be automated in CI | Create a test Payment Intent in PayMongo sandbox, scan the QR with the PayMongo test GCash/Maya app, confirm webhook arrives and job order flips to Paid |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
