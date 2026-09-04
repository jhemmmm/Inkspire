# Phase 5: POS & Payments - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-04
**Phase:** 5-pos-payments
**Areas discussed:** Pricing/discounts/cancellation fee, On-Credit approval flow, GCash/Maya payment experience & reconciliation, Down payment/balance/release gating

---

## Pricing, discounts & cancellation fee

| Question | Option | Selected |
|---|---|---|
| How should the Cashier connect a job order to its price? | Catalog pick + manual adjust | ✓ |
| | Fully custom price | |
| | You decide | |

**User's choice:** Catalog pick + manual adjust

| Question | Option | Selected |
|---|---|---|
| What discount model should POS-01 support? | Cashier discretion + cap | ✓ |
| | Fixed categories (Senior/PWD, Bulk) | |
| | No discounts for now | |

**User's choice:** Cashier discretion + cap

| Question | Option | Selected |
|---|---|---|
| When does the rush fee apply? | Cashier toggles it at POS | ✓ |
| | Set at intake, applied automatically | |

**User's choice:** Cashier toggles it at POS

| Question | Option | Selected |
|---|---|---|
| What should gate the cancellation fee, and where does the amount come from? | Design started → flat fee from config | ✓ |
| | Any cancellation → flat fee from config | |
| | Cashier discretion | |

**User's choice:** Design started → flat fee from config

---

## On-Credit approval flow

| Question | Option | Selected |
|---|---|---|
| How does Owner approval actually happen? | Real-time in-person sign-off | |
| | Pending queue, approved later | ✓ |

**User's choice:** Pending queue, approved later

| Question | Option | Selected |
|---|---|---|
| If Owner rejects the request, what happens to the job order? | Bounces back to Cashier for payment | |
| | Stays flagged, Owner decides next step | ✓ |

**User's choice:** Stays flagged, Owner decides next step

| Question | Option | Selected |
|---|---|---|
| Is there any eligibility gate on which job orders/customers can go On-Credit? | Open — Cashier can request for anyone | ✓ |
| | Registered customer only | |

**User's choice:** Open — Cashier can request for anyone

| Question | Option | Selected |
|---|---|---|
| Does the credit get a due date/term at approval time? | Owner sets a due date on approval | |
| | No due date yet — just posts the balance | ✓ |

**User's choice:** No due date yet — just posts the balance

---

## GCash/Maya payment experience & reconciliation

| Question | Option | Selected |
|---|---|---|
| How does the customer actually pay via GCash/Maya at the counter? | QR/checkout shown on-screen, customer scans | ✓ |
| | Link sent to customer's phone/email | |

**User's choice:** QR/checkout shown on-screen, customer scans

| Question | Option | Selected |
|---|---|---|
| POS-04's manual reconciliation check — who sees it and how? | Per-transaction button, both roles | ✓ |
| | Accounting-only dashboard + Cashier per-transaction | |

**User's choice:** Per-transaction button, both roles

| Question | Option | Selected |
|---|---|---|
| Can the Cashier move on while pending, or does the register block? | Cashier moves on, checks back later | ✓ |
| | Register waits on this transaction | |

**User's choice:** Cashier moves on, checks back later

| Question | Option | Selected |
|---|---|---|
| If reconciliation shows still unpaid/expired, what can the Cashier do? | Switch to a different payment method | ✓ |
| | Retry the same method only | |

**User's choice:** Switch to a different payment method

---

## Down payment, balance & release gating

| Question | Option | Selected |
|---|---|---|
| Is there a minimum down payment required? | No minimum — any amount | ✓ |
| | Configurable minimum % | |
| | Cashier discretion, no system floor | |

**User's choice:** No minimum — any amount

| Question | Option | Selected |
|---|---|---|
| Does an unpaid balance block production, or only final pickup handover? | Only blocks final pickup/handover | ✓ |
| | Blocks production from starting | |

**User's choice:** Only blocks final pickup/handover

| Question | Option | Selected |
|---|---|---|
| How should "redirected to Cashier" for unpaid pickup be enforced? | Dedicated "Release/Hand over" action, payment-gated | ✓ |
| | You decide | |

**User's choice:** Dedicated "Release/Hand over" action, payment-gated

| Question | Option | Selected |
|---|---|---|
| If a job with a down payment gets cancelled (fee applies), what happens to the down payment? | Applied toward the cancellation fee | ✓ |
| | Forfeited entirely, no refund logic | |

**User's choice:** Applied toward the cancellation fee

---

## Claude's Discretion

- Exact schema/columns for `pricing_database`, `transactions`, and `accounts_receivable` (greenfield tables)
- Exact `job_orders.payment_status` enum values/naming
- Exact new `system_configurations` keys for the discount cap and cancellation fee amount
- UI layout of the Cashier's POS screen (rush toggle, discount input, pricing catalog picker)
- Exact mechanism for the release/hand-over action
- PayMongo API specifics (Payment Intent vs. Source, webhook payload shape, signature verification) — flagged as research territory
- Whether Accounting gets a dedicated filtered list view beyond the per-transaction reconciliation button

## Deferred Ideas

None — discussion stayed within Phase 5 scope (POS-01 through POS-09).
