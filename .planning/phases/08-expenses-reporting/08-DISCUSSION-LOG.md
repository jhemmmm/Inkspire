# Phase 8: Expenses & Reporting - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-10
**Phase:** 8-expenses-reporting
**Areas discussed:** Export mechanism (RPT-05), Report surface shape, Profit report definition (RPT-01), Expense record lifecycle (EXP-01)

---

## Area selection

| Option | Description | Selected |
|--------|-------------|----------|
| Export mechanism (RPT-05) | No library installed; PROJECT.md requires approval to add one | ✓ |
| Report surface shape | Shared engine vs per-role pages; Admin entitlement | ✓ |
| Profit report definition (RPT-01) | What counts as revenue and cost | ✓ |
| Expense record lifecycle (EXP-01) | Mutability, access, fields | ✓ |

**User's choice:** All four areas.

---

## Export mechanism (RPT-05)

### Q1 — How should PDF and Excel export be produced?

| Option | Description | Selected |
|--------|-------------|----------|
| Zero-dependency: CSV + browser print | Streamed CSV for "Excel", print-styled Inertia page for "PDF", reusing the ReceiptController/CollectionLetterController pattern. No new packages. Trade-off: a .csv is not an .xlsx, PDF depends on Ctrl+P, collection letter stays unattachable | |
| Two server-side libs: real .pdf + real .xlsx | `barryvdh/laravel-dompdf` + `openspout/openspout` (lighter than maatwebsite/excel). Genuine downloadable files, no headless browser, works on Laravel Cloud. Trade-off: two new dependencies, dompdf needs Blade views in an Inertia-only app | ✓ |
| Split: real .xlsx, print-to-PDF | `openspout` only; PDF stays browser print. One dependency. Trade-off: collection-letter-by-email still impossible | |

**User's choice:** Two server-side libs.
**Notes:** This is an explicit dependency-change approval under PROJECT.md §Constraints, scoped to exactly these two packages.

### Q2 — Should generating or exporting a report write an audit_trail entry?

| Option | Description | Selected |
|--------|-------------|----------|
| Yes — log exports only | Screen views write nothing; export writes one entry (who/report/range/format). Matches the demo's `rpExport` behavior. Needs a deliberate write path since AuditObserver only sees model mutations | ✓ |
| No — reports are reads, audit stays for mutations | AUDIT-01 scopes the trail to mutations and auth events; cheapest option. Trade-off: no record of who pulled financial data | |
| Yes — log every report view and export | Maximum traceability. Trade-off: fills the Owner's audit viewer with routine reads | |

**User's choice:** Log exports only.

### Q3 — Retrofit existing print-only documents to PDF?

| Option | Description | Selected |
|--------|-------------|----------|
| No — leave both as-is | dompdf serves reports only; receipt and collection letter untouched. Smallest diff | |
| Add a PDF download to the collection letter only | One extra route reusing the new renderer; the letter is the document that genuinely wants to be a file. Trade-off: touches Phase 7 code | ✓ |
| Retrofit both receipt and letter | Most consistent end state. Trade-off: re-opens two completed phases' verified surfaces for something neither requirement asked for | |

**User's choice:** Collection letter only.
**Notes:** Bounded touch — add a route and Blade view; do not modify the existing Inertia print path. Phase 7 D-11 had explicitly parked this here.

---

## Report surface shape

### Q1 — How are reports surfaced across the 7 portals?

| Option | Description | Selected |
|--------|-------------|----------|
| One shared Reports page, role-filtered | Single registry + one date control + one preview + one export pipeline, mounted per portal (the demo's shape). Adding a report later is one entry. Trade-off: entitlement becomes the single security boundary | ✓ |
| Bespoke report page per role portal | Follows the existing `Artist/PerformanceReportController` precedent and the per-role controller convention. Trade-off: 4+ near-duplicate controllers, export logic duplicated or needs a shared service anyway | |
| Shared engine, per-role entry pages | One backend registry, N thin portal pages. Trade-off: extra indirection | |

**User's choice:** One shared role-filtered Reports page.

### Q2 — What do Owner and Admin see?

| Option | Description | Selected |
|--------|-------------|----------|
| Owner sees every report; Admin sees none | Owner's entitlement is the union of all report types; Admin gets no Reports surface. RPT-01–04 never mention Admin; the demo putting Reports under Admin is a UI reference, not a business-rules source | ✓ |
| Owner sees financial/profit only | Strictest literal reading. Trade-off: the Owner can't see their own Cashier's sales report | |
| Owner sees everything; Admin sees non-financial | Invents a fifth entitlement tier no requirement asks for | |

**User's choice:** Owner all, Admin none.

### Q3 — Are Daily and Monthly separate report types?

| Option | Description | Selected |
|--------|-------------|----------|
| One report per subject + a date-range control | "Daily"/"Monthly" are just ranges over the same query. Range presets: Today / week / month / quarter / custom. Trade-off: deviates from RPT-04's literal wording | ✓ |
| Separate Daily and Monthly report types | Literal 1:1 with RPT-04; monthly rolls up per-day subtotals. Trade-off: four registry entries where two do, and a custom range fits neither | |
| One report + a granularity toggle | Range plus a Daily/Monthly grouping control. Trade-off: two controls, and grouping must carry through every export | |

**User's choice:** One report per subject + date-range control.
**Notes:** Verification must read "Daily Sales" as "the Sales report, ranged to a day". Documented deviation, same spirit as the `status`/`payment_status` split.

---

## Profit report definition (RPT-01)

### Q1 — What counts as revenue?

| Option | Description | Selected |
|--------|-------------|----------|
| Cash basis — completed transactions only | Sum of `TransactionStatus::Completed` in range. Same basis as the receipt and Daily Sales, so Sales and Profit can never disagree. Trade-off: a heavy on-credit month looks poor until collections land | ✓ |
| Accrual — job order totals when earned | Reflects work done regardless of payment. Trade-off: two reports would show two different revenue figures for the same month | |
| Both, shown side by side | Collected vs earned with the AR gap explicit. Trade-off: profit still has to pick one, and two revenue numbers read as a bug | |

**User's choice:** Cash basis.

### Q2 — How do write-offs appear?

| Option | Description | Selected |
|--------|-------------|----------|
| Disclosed line, excluded from profit | Reported visibly as bad debt but not deducted, because under cash basis it was never counted as revenue — deducting books the loss twice. Satisfies Phase 7 D-14's "a loss to report" | ✓ |
| Subtract write-offs from profit | Intuitive, but arithmetically wrong under cash basis — understates profit by the full amount and can show a loss that didn't happen | |
| Omit write-offs entirely | Safest arithmetic. Trade-off: the Owner who approved each write-off has no financial view of them | |

**User's choice:** Disclosed, not deducted.
**Notes:** Needs unambiguous labeling or an Owner will expect the subtraction.

### Q3 — Should cancellation fees be broken out?

| Option | Description | Selected |
|--------|-------------|----------|
| Break out as its own revenue line | Job sales vs cancellation fees as separate lines summing to total. Rising fee income is a problem, not a success; the `transactions.type` column makes the split free | ✓ |
| Single revenue figure, no split | Simplest. Trade-off: blends delivered-work income with abandoned-work fees | |
| Exclude cancellation fees from revenue | Trade-off: the cash genuinely entered the business, and retained down payments would need the same treatment | |

**User's choice:** Break out as its own line.
**Notes:** Verified against `CancellationController` — a `CancellationFee` transaction is recorded only for the shortfall, nothing is recorded when prior payments cover the fee, and no refund/negative transactions exist anywhere in the system.

---

## Expense record lifecycle (EXP-01)

### Q1 — Can an expense be changed after recording?

| Option | Description | Selected |
|--------|-------------|----------|
| Editable and voidable, fully audited | Edit amount/category/date/note; void with a reason; every change audited via AuditObserver. Trade-off: an edit silently changes a previously-printed report — the audit trail explains it | ✓ |
| Append-only — no edit, no delete | Immutable like audit_trail; corrections via negative entries. Trade-off: negative expenses exist nowhere else in the system, and PROJECT.md reserves structural append-only for audit_trail | |
| Void-only — no edit | Void and re-enter. Trade-off: two rows and a reason to fix one digit | |

**User's choice:** Editable and voidable, audited.

### Q2 — Who can record, who can see?

| Option | Description | Selected |
|--------|-------------|----------|
| Accounting records; Owner reads | Accounting is the only writer; Owner reads the itemized list and the report figures. Matches EXP-01's wording and the entitlement locked in the surface area | ✓ |
| Accounting only — Owner sees totals only | Trade-off: "Expenses: ₱48,200" with no breakdown is the first thing an owner asks about | |
| Accounting and Owner can both record | No single point of failure. Trade-off: two write paths, blurs the Phase 1 role separation | |

**User's choice:** Accounting writes, Owner reads.

### Q3 — What fields does an expense carry?

| Option | Description | Selected |
|--------|-------------|----------|
| Add a description/note only | category + amount + expense date + optional description + recorded_by + timestamps. No attachment | ✓ |
| Exactly the three named fields | Literal minimum. Trade-off: unreadable a month later; staff will abuse the category field as a note field | |
| Note plus receipt attachment | Verifiable expenses. Trade-off: upload validation, storage, retention and a viewer — its own capability | |

**User's choice:** Description/note only, no attachment.

---

## Claude's Discretion

- Report registry mechanism (config array vs enum vs invokable classes vs service map).
- Whether RPT-04's "Summary of Sales & Expenses" and RPT-01's profit report are one registry entry or two.
- Exact `expenses` table/column/enum naming; whether void is `voided_at` + reason or a status enum.
- Behavior when a category is removed from `expense_categories` config (invariant locked: history never rewritten).
- PDF letterhead/branding; whether the collection-letter PDF shares the report layout.
- Export filename convention; synchronous vs queued generation (synchronous strongly preferred).
- Excel export content shape (raw rows preferred).
- Reports page layout, default date range, on-screen pagination — `/gsd-ui-phase 8` can settle these.

## Deferred Ideas

- Emailing the collection letter to the debtor (mechanically possible after D-03; still customer-facing dunning, rejected in Phase 7 D-06).
- A PDF download for the digital receipt (POS-06) — considered under Q3, left out.
- Report scheduling / emailed periodic reports.
- Receipt attachments on expense records.
- The demo's `quality` and `fileval` reports — data exists, no requirement names them.
- AR aging summary on the Owner's dashboard (Phase 7 D-16 pointed it here; the report is delivered, the dashboard widget was not discussed).
- Charts and visualizations — every report here is a table plus totals; a charting library would be another dependency decision.
