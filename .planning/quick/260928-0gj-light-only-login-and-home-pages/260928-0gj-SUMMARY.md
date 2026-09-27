---
phase: quick-260928-0gj
plan: 01
status: complete
date: 2026-09-28
---

# Quick Task 260928-0gj — Summary

- `app.blade.php` computes `$lightOnly` from the Inertia component
  (Welcome, auth/*, errors/*). It skips the `dark` class and the
  system-preference script for the first paint.
- `useAppearance.ts`: `isLightOnlyPage()` is the client twin, and
  `updateTheme` never adds `dark` on such a page. `initializeTheme` now
  re-applies the saved preference on every Inertia `navigate` (the initial
  load included) instead of once at boot, so logging in brings the dark
  theme back and logging out drops it. The saved preference is never changed.
- Welcome's logo lost its dead `dark:` classes.

## Verification

- New `tests/Feature/AppearanceTest.php` (5 tests). When run against the
  old Blade shell, 4 of them fail.
- A browser flow on a throwaway SQLite server: 16/16 checks for saved
  'dark' and for 'system' with a dark OS.
- vue-tsc and Pint pass. `npm run check` is clean apart from the STATE.md
  formatting that was already failing. Related Auth/Dashboard/RoleBoundary
  tests: 22 passed.
