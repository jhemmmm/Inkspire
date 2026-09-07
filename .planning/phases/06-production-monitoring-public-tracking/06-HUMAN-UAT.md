---
status: partial
phase: 06-production-monitoring-public-tracking
source: [06-VERIFICATION.md]
started: 2026-09-07T01:25:54Z
updated: 2026-09-07T01:25:54Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Job-order number generation holds under real MySQL concurrency (CR-01 / CR-02)
expected: With `DB_CONNECTION=mysql`, two or more concurrent job-order creations produce
distinct sequential numbers with no duplicate-key error. `nextNumberForYear()` runs
`SELECT ... LIKE 'JO-{year}-%' ORDER BY LENGTH(number) DESC, number DESC FOR UPDATE` inside
the SAME transaction as the insert, so the row-range lock is still held when the insert lands.
Also confirm sequences past 9999 continue correctly (JO-YYYY-10000 → JO-YYYY-10001).
why-human: SQLite makes `lockForUpdate()` a no-op, so the passing test suite cannot prove this.
The CR-02 test asserts `DB::transactionLevel()` as a proxy, not real lock behavior.
result: [pending]

### 2. Job-order number backfill migration is atomic and re-runnable on MySQL (WR-10)
expected: Running `2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php` against a
populated MySQL database backfills every existing row with a correct year-scoped number. If the
migration is interrupted partway and re-run, it resumes from the highest existing sequence per
year (via `highestSequencePerYear()`) rather than restarting at 0001 and colliding on the
unique index.
why-human: MySQL issues an implicit COMMIT on `ALTER TABLE`, so the migration's wrapping
transaction cannot roll back the schema change. This failure mode does not exist on SQLite.
result: [pending]

### 3. Receipt QR code scans from a physically printed receipt
expected: Print a digital receipt for a job order with an assigned number, scan the QR block
with a phone camera, and land on the public `/track` result showing that order's current stage.
why-human: URL construction and QR payload are verified in code and tests, but camera-
scannability of a printed page (contrast, size, print quality) is a physical-device test.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
