---
status: partial
phase: 03-job-order-intake-auto-assignment
source: [03-VERIFICATION.md]
started: 2026-09-02T02:45:12Z
updated: 2026-09-02T02:45:12Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Replace File dialog from New Visit confirmation card
expected: Open a Type A job order that reached validation_failed (e.g. upload an unsupported file extension) via New Visit, click 'Replace File', upload a valid PDF, and submit. The dialog closes, a success toast reading 'File replaced. Job order is ready for production.' appears, and the job order's badge in the confirmation card updates in place from 'Validation Failed' (red) to 'Ready for Production' (green) without a full page navigation.
result: [pending]

### 2. Replace File dialog from Queue table
expected: From the Queue page, find a queue entry with a validation_failed job order, click the icon-only Replace File (refresh) button in the Job Orders column, submit a valid file. The dialog closes, the row's status badge updates from 'Validation Failed' to 'Ready for Production' in the same table without navigating away, and a toast confirms the outcome.
result: [pending]

### 3. Visual badge color/variant check
expected: Visually inspect the four job-order status badges (Awaiting Assignment / Ready for Production / Assigned / Validation Failed) in both the New Visit confirmation card and the Queue table. Colors/variants match 03-UI-SPEC.md exactly: outline (neutral) for Awaiting Assignment, green text for Ready for Production, primary/filled for Assigned, red/destructive for Validation Failed — and queue-entry status (Waiting/Serving/Done) remains visually distinct from job-order status in the same table row.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
