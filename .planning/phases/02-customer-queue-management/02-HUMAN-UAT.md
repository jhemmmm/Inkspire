---
status: partial
phase: 02-customer-queue-management
source: [02-VERIFICATION.md]
started: 2026-09-02T01:45:00Z
updated: 2026-09-02T01:45:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. "Start New Visit" resets the page to the search screen
expected: After completing one visit (queue confirmation shown), clicking "Start New Visit" clears the Customer summary card, Job Orders form, and confirmation card, returning to a blank search bar — not a stale re-render of the previous customer.
result: [pending]

### 2. Job order file input visually clears on Type A -> Type B -> Type A toggle
expected: Selecting a file on a Type A row, switching to Type B (hides the input) and back to Type A shows an empty file input, and the stale File object is not silently resubmitted.
result: [pending]

### 3. "Add Job Order" dialog works on a Done queue entry
expected: Clicking the Add Job Order icon on a row with status Done opens a dialog; submitting a Type A row with a file, or a Type B row without one, succeeds and the entry's job order count increases without a page-level status change.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
