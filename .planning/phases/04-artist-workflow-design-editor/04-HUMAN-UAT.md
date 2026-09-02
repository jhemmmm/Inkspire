---
status: partial
phase: 04-artist-workflow-design-editor
source: [04-VERIFICATION.md]
started: 2026-09-02T22:15:00Z
updated: 2026-09-02T22:15:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. TOAST UI Image Editor renders and functions in a real browser
expected: Open an Artist's Job Order Workspace for a job order in `in_consultation` or `in_design` status. Click "Start from Blank Canvas" or "Import Reference Image". Menu bar renders (crop/flip/rotate/draw/shape/icon/text/filter), each tool is usable, the color picker is styled (not an unstyled `<div>`), and no NHN telemetry request appears in the browser's network tab.
result: [pending]

### 2. Exported design PNG visually matches the canvas
expected: After editing a design and clicking "Send for Review", retrieve the stored `design_files` PNG (via its signed URL) and compare it to what was drawn in the editor. The stored PNG is a faithful flattened export of the canvas content.
result: [pending]

## Summary

total: 2
passed: 0
issues: 0
pending: 2
skipped: 0
blocked: 0

## Gaps
