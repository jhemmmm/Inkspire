# Deferred Items — Quick Task 260910-fup

## Pre-existing repo-wide formatting drift (out of scope)

`npm run check` (via `vp check`) reports formatting issues in 228 files that are
unrelated to this task's scope: `.planning/**/*.md`, `.claude/skills/**/*.md`,
`CLAUDE.md`, `README.md`, `boost.json`, and several pre-existing Vue pages
(`resources/js/pages/accounting-staff/AccountsReceivable/Index.vue`,
`resources/js/pages/accounting-staff/AccountsReceivable/Show.vue`,
`resources/js/pages/accounting-staff/CollectionLetter.vue`,
`resources/js/pages/errors/Forbidden.vue`,
`resources/js/pages/owner/AuditTrail.vue`,
`resources/js/pages/owner/WriteOffRequests.vue`).

None of these files were touched by this plan. Per the executor's scope
boundary rules ("Only auto-fix issues DIRECTLY caused by the current task's
changes"), these are logged here rather than fixed.

**Verification performed instead:**

- `npx vp check --fix` was run scoped only to this plan's touched files
  (`resources/css/app.css`, `vite.config.ts`, `resources/views/app.blade.php`,
  `resources/js/layouts/AuthLayout.vue`,
  `resources/js/layouts/auth/AuthBrandLayout.vue`,
  `resources/js/pages/auth/Login.vue`) — all now pass formatting cleanly.
- `npx vp check --no-fmt` (lint + type-check only, full repo) passes with
  zero warnings/errors across 95 files, confirming this task introduces no
  lint or type regressions.
- `npm run build` completes successfully.

**Recommendation:** A separate repo-wide formatting cleanup task should run
`npm run check:fix` across the full tree when convenient, ideally isolated
from feature work so the diff is reviewable on its own.

## Blocking issue: demo/index.html breaks `vp check` fmt pass entirely

Before any fix, `npm run check` failed outright (not just with formatting
warnings) because `vp check`'s formatter attempted to parse
`demo/index.html` (an untracked, large reference-only HTML file dropped in
the repo for this task's design reference — see plan context) and hit a
hard HTML syntax error, aborting the entire format pass before analysis of
any other file could start.

**Fix applied (Rule 3 — auto-fix blocking issue):** Added `'demo/**'` to
both `lint.ignorePatterns` and `fmt.ignorePatterns` in `vite.config.ts`,
matching the existing precedent of excluding non-source directories
(`vendor/**`, `node_modules/**`, `public/**`, `bootstrap/ssr/**`). This
unblocked `npm run check` for the rest of the repo. Committed as part of
Task 3 (`resources/js/pages/auth/Login.vue` task) since it was required for
that task's verification step to complete.
