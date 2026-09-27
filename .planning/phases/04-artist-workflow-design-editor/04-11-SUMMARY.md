---
phase: 04-artist-workflow-design-editor
plan: 11
subsystem: api
tags: [inertia, resend, mail, signed-routes, laravel, vue]

# Dependency graph
requires:
    - phase: 04-artist-workflow-design-editor (04-03, 04-05)
      provides: DesignEditorController's approve()/requestChanges() in-person review flow and RecordDesignRevision action, which this plan extends without modifying
provides:
    - 'A second, unauthenticated way for a client to record a design verdict: an emailed, signed link (D-17 through D-21)'
    - "App\\Http\\Controllers\\Public\\DesignReviewController with show/approve/requestChanges reaching the same two outcomes as DesignEditorController, through entirely independent code"
    - "App\\Mail\\DesignReviewRequested, dispatched after RecordDesignRevision commits its transaction"
    - 'resources/js/pages/public/DesignReview.vue rendering active/stale/closed/expired states'
    - 'InvalidSignatureException handled in bootstrap/app.php with a friendly expired-link page'
affects: [phase-05-pos-payments]

# Tech tracking
tech-stack:
    added: [resend/resend-php v1.12.0]
    patterns:
        - "Public, unauthenticated controller mirrors an authenticated controller's outcomes via a shared status/outcome guard (isActionable), never by importing or calling the authenticated controller"
        - 'Mail dispatch strictly after DB::transaction() commits, never inside it, so a rollback can never be followed by an email pointing at a phantom record'
        - 'Inertia Form component with plain :action string + method prop (not Wayfinder .form()) for routes carrying a runtime HMAC query string'

key-files:
    created:
        - app/Http/Controllers/Public/DesignReviewController.php
        - resources/js/pages/public/DesignReview.vue
        - tests/Feature/Public/DesignReviewTest.php
        - app/Mail/DesignReviewRequested.php
        - resources/views/mail/design-review-requested.blade.php
    modified:
        - routes/web.php
        - bootstrap/app.php
        - app/Actions/JobOrder/RecordDesignRevision.php
        - composer.json
        - composer.lock
        - .env.example

key-decisions:
    - "Route names written as full literal strings (->name('public.design-review.show') etc.) per route, instead of a group-level ->name('public.design-review.') prefix, to satisfy the plan's own acceptance-criteria grep count while producing identical route names"
    - 'resend/resend-php approved via a manual legitimacy checkpoint (Packagist downloads, official repo, slopcheck scan) since 04-RESEARCH.md predates this feature'

patterns-established:
    - "Public/unauthenticated review-link controllers implement their own first-verdict-wins guard rather than reusing an authenticated controller's guard code, keeping the authenticated path provably untouched"

requirements-completed: [JOB-06]

# Metrics
duration: 30min
completed: 2026-09-03
---

# Phase 4 Plan 11: Client Remote Design Review Summary

**Emailed, signed-link design review (Resend) letting a client Approve/Request Changes without logging in, reaching the identical outcomes DesignEditorController already produces, via entirely independent code.**

## Performance

- **Duration:** ~30 min (includes worktree environment setup: composer install, npm install, frontend build)
- **Tasks:** 3 (1 checkpoint, 2 auto) — checkpoint resolved by human approval prior to this execution
- **Files modified/created:** 11

## Accomplishments

- A client can now approve or request changes on a pending design entirely remotely via an emailed 7-day signed link, reaching the exact same two outcomes (`DesignFile.locked_at` + `JobOrderStatus::DesignApproved`, or `JobOrderStatus::InDesign` + `revision_logs.outcome=changes_requested`) as the in-person Artist path
- `DesignEditorController` and its `assigned_artist_id`/`PendingReview` guards remain byte-for-byte untouched — confirmed via `git diff --stat` showing an empty diff for that file
- Stale (superseded revision), closed (already reviewed), and expired (bad/tampered signature) links all render distinct, graceful Inertia states instead of a raw 422/500
- `RecordDesignRevision` now emails the client via Resend strictly after its DB transaction commits

## Task Commits

1. **Task 1: Package legitimacy checkpoint — resend/resend-php** - resolved by human approval (no commit; zero files changed by this task, per plan design)
2. **Task 2: Public review page — controller, routes, Vue page, and graceful expired/stale/closed states** - `4a58b4e` (feat)
3. **Task 3: Mint and email the signed remote-review link when a design enters pending_review** - `0a95e8a` (feat)

## Files Created/Modified

- `app/Http/Controllers/Public/DesignReviewController.php` - Unauthenticated show/approve/requestChanges actions; private render()/isCurrentRevision()/isActionable() helpers implement D-19/D-20's stale/closed/expired state logic and first-verdict-wins guard
- `resources/js/pages/public/DesignReview.vue` - Chrome-less public page (no AppLayout) rendering active/stale/closed/expired states; active state uses two Inertia `Form` components with plain `:action` string props (signed URLs) instead of Wayfinder's `.form()` shorthand
- `routes/web.php` - Three `public.design-review.*` signed routes outside every `role:*` group, each with a literal full route name
- `bootstrap/app.php` - New first branch in the exception `respond()` closure rendering the expired state on `InvalidSignatureException`, ahead of the existing 403/422 branches (left unmodified)
- `tests/Feature/Public/DesignReviewTest.php` - 7 feature tests: active, expired (bad signature), approve, request-changes, closed (already-resolved), stale (superseded revision), and mail dispatch via the authenticated Send for Review endpoint
- `app/Mail/DesignReviewRequested.php` - Markdown mailable carrying a `URL::temporarySignedRoute()` link, subject interpolating the job order description
- `resources/views/mail/design-review-requested.blade.php` - Markdown mail template using `<x-mail::button>`
- `app/Actions/JobOrder/RecordDesignRevision.php` - Captures the transaction's `RevisionLog` return value, dispatches `Mail::to(...)->send(new DesignReviewRequested(...))` strictly after commit
- `.env.example` - `MAIL_MAILER=resend` default and new `RESEND_API_KEY=` line (real, gitignored `.env` untouched)
- `composer.json` / `composer.lock` - `resend/resend-php` v1.12.0 added

## Decisions Made

- Wrote each `public.design-review.*` route name as a full literal string per route (`->name('public.design-review.show')`) rather than a group-level `->name('public.design-review.')` prefix. Functionally identical route names/behavior; done specifically so the plan's own acceptance criterion (`grep -c "public.design-review." routes/web.php` returns 3) passes, since the group-prefix form the plan's action text described would only match once.
- No other deviations from the plan's described implementation shape.

## Deviations from Plan

None functionally — plan executed as written. One cosmetic adjustment (see Decisions Made above) to satisfy the plan's own stated acceptance criterion.

### Environment setup (not a plan deviation, but notable)

The worktree had no `vendor/`, `node_modules/`, or `public/build/` on disk (all gitignored, not present at worktree creation). Ran `composer install`, `npm install`, `npm run build`, and `php artisan key:generate` before any task work, since the plan's own verification (`php artisan test`) and the pre-existing `tests/Feature/Artist/SendForReviewTest.php` baseline both require a working Vite manifest (the app's exception-handling paths render full Inertia pages via `resources/views/app.blade.php`'s `@vite()` call even in non-Inertia test requests). No repo files were affected by this setup beyond the intended `composer.lock`/`composer.json` changes from Task 3's `resend/resend-php` install; an incidental `package-lock.json` "name" field change (worktree directory name leaking into the lockfile) was reverted with `git checkout -- package-lock.json` before committing.

## Issues Encountered

None beyond the environment setup above.

## User Setup Required

**External service requires manual configuration.** Per this plan's `user_setup` frontmatter:

- Sign up for Resend (https://resend.com) if not already done
- Add `RESEND_API_KEY` to the real, gitignored `.env` (source: Resend Dashboard -> API Keys)
- Set `MAIL_MAILER=resend` in the real `.env` once the key is configured (already defaulted in `.env.example`)
- Verify a sending domain, or use Resend's shared onboarding domain for initial testing, in Resend Dashboard -> Domains, so `MAIL_FROM_ADDRESS` delivers successfully

Until this is configured, the developer's local `.env` should stay on `MAIL_MAILER=log` (a safe no-op) — Claude never edits the real `.env`.

## Next Phase Readiness

- JOB-06's remote-review extension is complete; the in-person (04-03/04-05) and remote (this plan) review paths both converge on identical job-order/design-file state, verified by the full test suite (188 tests, 185 passed, 3 pre-existing skips, zero failures) and a clean `migrate:fresh`.
- Manual end-to-end email verification (real Resend delivery, clicking the link in an actual inbox) is deferred to end-of-phase per `human_verify_mode: end-of-phase` — not performed in this worktree since it requires a configured `RESEND_API_KEY` outside Claude's control.
- No blockers for subsequent Phase 4 plans.

---

_Phase: 04-artist-workflow-design-editor_
_Completed: 2026-09-03_

## Self-Check: PASSED

All created files verified present on disk:

- FOUND: app/Http/Controllers/Public/DesignReviewController.php
- FOUND: resources/js/pages/public/DesignReview.vue
- FOUND: tests/Feature/Public/DesignReviewTest.php
- FOUND: app/Mail/DesignReviewRequested.php
- FOUND: resources/views/mail/design-review-requested.blade.php
- FOUND: .planning/phases/04-artist-workflow-design-editor/04-11-SUMMARY.md

All task commits verified present in `git log`:

- FOUND: 4a58b4e (Task 2)
- FOUND: 0a95e8a (Task 3)
- FOUND: e052a6e (this summary)
