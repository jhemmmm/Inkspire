---
phase: 04-artist-workflow-design-editor
reviewed: 2026-09-03T15:10:40Z
depth: standard
files_reviewed: 59
files_reviewed_list:
  - app/Actions/JobOrder/RecordDesignRevision.php
  - app/Actions/JobOrder/SetArtistSessionStatus.php
  - app/Enums/ArtistStatus.php
  - app/Enums/JobOrderStatus.php
  - app/Http/Controllers/Artist/DesignEditorController.php
  - app/Http/Controllers/Artist/JobOrderQueueController.php
  - app/Http/Controllers/Artist/JobOrderWorkspaceController.php
  - app/Http/Controllers/Artist/PerformanceReportController.php
  - app/Http/Controllers/Artist/SessionStatusController.php
  - app/Http/Controllers/Owner/DesignFileController.php
  - app/Http/Controllers/Owner/UserManagementController.php
  - app/Http/Controllers/Public/DesignReviewController.php
  - app/Http/Requests/Artist/PerformanceReportFilterRequest.php
  - app/Http/Requests/Artist/RecordDesignVerdictRequest.php
  - app/Http/Requests/Artist/SendForReviewRequest.php
  - app/Http/Requests/Artist/StartDesignRequest.php
  - app/Http/Requests/Artist/UpdateConsultationNotesRequest.php
  - app/Http/Requests/Artist/UpdateJobOrderQueuePositionRequest.php
  - app/Http/Requests/Artist/UpdateSessionStatusRequest.php
  - app/Http/Requests/Owner/UnlockDesignFileRequest.php
  - app/Mail/DesignReviewRequested.php
  - app/Models/DesignFile.php
  - app/Models/JobOrder.php
  - app/Models/RevisionLog.php
  - app/Models/User.php
  - app/Policies/DesignFilePolicy.php
  - bootstrap/app.php
  - database/factories/DesignFileFactory.php
  - database/factories/JobOrderFactory.php
  - database/factories/RevisionLogFactory.php
  - database/migrations/2026_09_02_082141_add_consultation_and_queue_columns_to_job_orders_table.php
  - database/migrations/2026_09_02_084148_create_design_files_table.php
  - database/migrations/2026_09_02_084149_create_revision_logs_table.php
  - database/migrations/2026_09_02_123917_add_artist_status_columns_to_users_table.php
  - resources/js/components/ToastImageEditor.vue
  - resources/js/config/nav/artist.ts
  - resources/js/config/nav/owner.ts
  - resources/js/pages/artist/Dashboard.vue
  - resources/js/pages/artist/JobOrderWorkspace.vue
  - resources/js/pages/artist/PerformanceReport.vue
  - resources/js/pages/owner/DesignOverrides.vue
  - resources/js/pages/owner/UserManagement.vue
  - resources/js/pages/public/DesignReview.vue
  - resources/js/types/tui-image-editor.d.ts
  - resources/views/mail/design-review-requested.blade.php
  - routes/owner.php
  - routes/portals.php
  - routes/web.php
  - tests/Feature/Artist/ConsultationNotesTest.php
  - tests/Feature/Artist/DesignEditorTest.php
  - tests/Feature/Artist/DesignLockTest.php
  - tests/Feature/Artist/DesignReviewTest.php
  - tests/Feature/Artist/PerformanceReportTest.php
  - tests/Feature/Artist/QueueControlsTest.php
  - tests/Feature/Artist/SendForReviewTest.php
  - tests/Feature/Artist/SessionStatusTest.php
  - tests/Feature/Owner/UnlockDesignFileTest.php
  - tests/Feature/Owner/UserManagementTest.php
  - tests/Feature/Public/DesignReviewTest.php
findings:
  critical: 1
  warning: 7
  info: 3
  total: 11
status: issues_found
---

# Phase 4: Code Review Report

**Reviewed:** 2026-09-03T15:10:40Z
**Depth:** standard
**Files Reviewed:** 59
**Status:** issues_found

## Summary

This is the first combined review of the full Phase 4 scope (all 12 plans, including the 2026-09-03 scope-expansion plans 04-11 "client remote design review" and 04-12 "client-side PSD import"). The status-machine work (`JobOrderStatus`/`ArtistStatus` extensions, queue controls, session status, performance report) is solid and consistent with established codebase conventions — ownership/ status guards are applied uniformly, the audit trail is inherited "for free" via `AuditObserver` with no hand-rolled logging, and the `design_files.locked_at`-as-sole-lock-authority design holds up under inspection (verified against the Owner unlock override, the artist edit gate, and the public remote-review "first verdict wins" guard).

The full local Pest suite for this phase's files (65 tests) passes as-is, and `npm run types:check` is clean. However, the review surfaced one production-relevant defect in the newest work (04-11's mail dispatch) that can crash the core "Send for Review" action once the app's real mail transport (Resend) is configured, plus a cluster of quality/consistency issues — several introduced by the TOAST UI type-shim now being dead code (the installed `tui-image-editor` package ships its own bundled `index.d.ts`, contrary to the phase's research), magic-string duplication of revision outcomes, and a couple of UI/UX gaps between the in-person and remote review paths.

## Critical Issues

### CR-01: Synchronous, unhandled `Mail::send()` after commit can crash "Send for Review" once Resend is configured

**File:** `app/Actions/JobOrder/RecordDesignRevision.php:24-45`
**Issue:** `RecordDesignRevision::__invoke()` commits the `DesignFile` upsert, `RevisionLog` insert, and `JobOrder` status advance inside `DB::transaction()`, then — correctly, per D-18/the mail.md skill's "dispatch after commit" guidance — calls `Mail::to($jobOrder->queueEntry->customer->email)->send(new DesignReviewRequested($revisionLog));` **outside** the transaction, synchronously, with no `try`/`catch`. `DesignReviewRequested` does not implement `ShouldQueue`.

This means any failure in the mail transport — a misconfigured/expired `RESEND_API_KEY`, a Resend API outage, a network timeout, or a customer email address Resend's API rejects — throws an uncaught exception that propagates all the way up through `DesignEditorController::sendForReview()`, past the point where the success toast would have been flashed, and results in an unhandled 500 for the Artist. Critically, **the database mutation already committed** (design file saved, revision logged, status advanced to `pending_review`) before the exception is thrown. The Artist sees a raw error page even though their action actually succeeded, and `sendForReview()`'s own guard (`abort_if($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This design is already pending review.')`) then blocks any retry, leaving them stuck with no visible path forward short of contacting an Owner.

This is currently masked in every test in this phase (`tests/Feature/Artist/SendForReviewTest.php`, `tests/Feature/Public/DesignReviewTest.php`) because `phpunit.xml` sets `MAIL_MAILER=array`, which never touches the network and cannot fail — so there is no test coverage proving the app degrades gracefully when mail delivery fails. The `.env.example` change in this same plan (`MAIL_MAILER=resend`) is the intended production configuration, so this failure mode is not hypothetical — it is the default the app ships toward.

This directly threatens the project's stated core value ("a job order flows correctly end-to-end") for the single most central Artist action in this phase.
**Fix:** Either queue the mailable so a transport failure never blocks the HTTP response, or catch and log the failure without letting it bubble into the response:
```php
// Option A (preferred): decouple delivery from the request/response cycle
class DesignReviewRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    // ...
}
// RecordDesignRevision.php — dispatch after commit, queued, and after_commit-aware:
Mail::to($jobOrder->queueEntry->customer->email)
    ->send((new DesignReviewRequested($revisionLog))); // queued dispatch is not blocking

// Option B (if it must stay synchronous): don't let mail failure fail the write
try {
    Mail::to($jobOrder->queueEntry->customer->email)->send(new DesignReviewRequested($revisionLog));
} catch (\Throwable $e) {
    report($e); // log it — the design revision itself already succeeded
}
```
Add a test that fakes a mail transport failure (or `Mail::shouldReceive(...)->andThrow(...)`) and asserts `sendForReview()` still returns success to the Artist.

## Warnings

### WR-01: No concurrency guard on `sendForReview` — duplicate near-simultaneous submissions can orphan a `revision_logs` row and double-email the client

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:55-67`, `app/Actions/JobOrder/RecordDesignRevision.php:24-45`
**Issue:** The `sendForReview` guards (`abort_if($jobOrder->status === JobOrderStatus::PendingReview, ...)`, lock check) all read `$jobOrder`'s current state without any row lock (`lockForUpdate()`) or unique-constraint-backed idempotency key. Two near-simultaneous requests for the same job order (double-click before the button's `:disabled="processing"` takes effect, a flaky network causing a client-side retry, or two browser tabs) can both pass the guards while reading the same pre-commit state, then both execute `RecordDesignRevision`. The result: two `revision_logs` rows are created, `design_files` (protected by a real DB unique constraint on `job_order_id`) settles on whichever `updateOrCreate` wins last, and `DesignEditorController::latestUnreviewedRevisionLog()` (which picks the single newest-by-`submitted_at` row) will only ever resolve one of the two — the other stays permanently `outcome = null`, silently invisible in the UI, forever "pending" in the database. The client also receives two separate review emails with two different signed links for what looks like the same design.
**Fix:** Wrap the guard-and-mutate sequence in a row lock, e.g. `$jobOrder = JobOrder::whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();` inside the transaction before re-checking status, or add a short-lived cache/mutex keyed on the job order id around `sendForReview()`.

### WR-02: Revision outcome is a duplicated magic string, not the enum convention this codebase otherwise uses everywhere else

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:80,109`, `app/Http/Controllers/Public/DesignReviewController.php:41,68`, `app/Http/Controllers/Artist/PerformanceReportController.php:29`, `database/factories/RevisionLogFactory.php:37,50`
**Issue:** `revision_logs.outcome` is written and read as raw strings (`'approved'`, `'changes_requested'`) independently in five different files, with no shared constant or enum. Every other status-like column this phase and prior phases introduced (`JobOrderStatus`, `ArtistStatus`, `UserRole`) uses the project's established string-backed PHP enum convention specifically so these values are typo-proof and centrally documented. A typo in any one of these five call sites (e.g. `'change_requested'`) would silently produce an unmatched outcome that every other file's string comparison fails to recognize, with no static-analysis or runtime error to catch it.
**Fix:** Introduce `enum RevisionOutcome: string { case Approved = 'approved'; case ChangesRequested = 'changes_requested'; }`, cast `RevisionLog::outcome` to it, and replace every literal string with the enum case.

### WR-03: `SendForReviewRequest` has no upper bound on upload size

**File:** `app/Http/Requests/Artist/SendForReviewRequest.php:20-25`
**Issue:** The validation rule is `['required', 'file', 'image', 'mimes:png']` — no `max:` constraint. The docblock's rationale ("this endpoint's only legitimate producer is the app's own canvas export") only holds for traffic that actually goes through the Vue editor; the route itself is a plain authenticated POST endpoint reachable directly (e.g. via a scripted request from a valid but malicious/careless Artist session) with an arbitrarily large PNG, since `mimes`/`image` validate content type, not size. There is no size ceiling anywhere in the request pipeline before the file is written to local disk storage.
**Fix:** Add a `max:` rule sized to a realistic maximum canvas export (e.g. `'file' => ['required', 'file', 'image', 'mimes:png', 'max:10240']` for 10MB), and confirm the number against the editor's actual `cssMaxWidth`/`cssMaxHeight` output.

### WR-04: Global `InvalidSignatureException` handler hard-codes the Design Review page for every signed route in the app

**File:** `bootstrap/app.php:44-46`
**Issue:** The new exception branch —
```php
if ($e instanceof InvalidSignatureException) {
    return Inertia::render('public/DesignReview', ['state' => 'expired'])->toResponse($request)->setStatusCode(403);
}
```
— is registered in the application-wide `respond()` closure, so it intercepts `InvalidSignatureException` from **any** signed route, not just `public.design-review.*`. This is currently harmless only because no other route in the app uses the `signed` middleware today (Fortify's email-verification feature, which also uses a signed route, is disabled in `config/fortify.php`). The moment a future phase enables email verification, adds a QR-tracking signed link, or any other signed route, an expired/tampered signature on that unrelated route will incorrectly render the Design Review page instead of a message relevant to that feature.
**Fix:** Scope the branch to the design-review routes specifically, e.g. check `$request->routeIs('public.design-review.*')` before rendering `public/DesignReview`, and fall through to a generic "link expired" response otherwise.

### WR-05: Public remote-review page lets a client irreversibly approve a design with a single click, no confirmation

**File:** `resources/js/pages/public/DesignReview.vue:50-63` (compare `resources/js/pages/artist/JobOrderWorkspace.vue:281-325`)
**Issue:** On the Artist's in-person workspace, "Client Approved" is deliberately wrapped in an `AlertDialog` ("Approve this design? ... Once approved, this design file becomes read-only. Only an Owner can unlock it for further edits.") specifically because the action is irreversible without an Owner override. The public remote-review page (D-17's second entry point to the *exact same* irreversible outcome) submits the "Client Approved" `Form` directly on click, with no confirmation step at all. A stray tap/click on a phone (this page is the one surface in the app most likely to be opened on a mobile device) permanently locks the design with no way back except contacting the shop for an Owner override.
**Fix:** Add the same confirm step used on the Artist page (a lightweight native `confirm()` or an inline two-step reveal is enough here, since this page intentionally carries no shared UI chrome/AlertDialog imports).

### WR-06: Dashboard shows a "Next" action on every `assigned` row, not just the actually-claimable one

**File:** `resources/js/pages/artist/Dashboard.vue:241-258`
**Issue:** `JobOrderQueueController::next()` only permits claiming the single oldest eligible `assigned` job order (`oldestEligibleId()`, `app/Http/Controllers/Artist/JobOrderQueueController.php:109-117`); every other `assigned` row returns a 422 ("Another job order is next in your queue."). The Dashboard template renders the "Next" button on **every** row where `jobOrder.status === 'assigned'` (line 242), with no client-side indication of which row is actually next. An Artist with more than one queued consultation (a normal scenario — several customers can be Assigned to the same Artist before any of them is called) will see a clickable "Next" button on rows that are guaranteed to fail, producing a confusing error toast for a button that looked identical to the working one. `QueueControlsTest.php`'s own "next on a non-oldest eligible job order returns a 422" test confirms the server-side behavior this UI doesn't visually distinguish.
**Fix:** Only render "Next" on the row matching the server-computed oldest-eligible id (expose it as a prop from `JobOrderQueueController::index()`), and render a disabled/inert state (or no button) for the rest.

### WR-07: Hand-written `tui-image-editor` type shim is dead code — the installed package ships its own, different bundled types

**File:** `resources/js/types/tui-image-editor.d.ts:1-26`
**Issue:** This ambient module declaration was written (per 04-RESEARCH.md Pitfall 2 and the 04-04 plan/summary) on the premise that `tui-image-editor` ships no TypeScript types. That premise does not hold for the actually-installed version: `node_modules/tui-image-editor/index.d.ts` (334 lines, `export = tuiImageEditor.ImageEditor`) is bundled directly in the package and is auto-discovered by TypeScript's classic Node resolution (a package needs no explicit `"types"` field in `package.json` for a root-level `index.d.ts` to be picked up). Verified directly: introducing a deliberately-invalid property into `ToastImageEditor.vue`'s `includeUI` object and running `vue-tsc --noEmit` reports the error against `IIncludeUIOptions` — a type name that only exists in the real package's `index.d.ts`, not in this project's own shim (which uses inline anonymous object types). `npm run types:check` currently passes cleanly with or without this file's content being accurate, because it is never actually consulted by the compiler.

Beyond being unused, the shim is also **incomplete relative to the real API it pretends to describe** — it's missing `uiSize` (a key `ToastImageEditor.vue` now sets, per the in-flight blank-canvas-sizing fix) and other real methods (`loadImageFromFile`, etc.), and it declares the constructor's `options` parameter optional when the real signature requires it. A future contributor trusting this file as the source of truth for the library's API surface will be misled.
**Fix:** Delete `resources/js/types/tui-image-editor.d.ts` (confirm `npm run types:check` still passes, which it will — the real bundled types take over), or if a narrower/stricter surface is genuinely wanted, name it differently and consciously shadow the package types with a `paths` remap rather than an ambient `declare module` that silently loses the conflict.

## Info

### IN-01: `forward()`/`notAppear()` mix enum-case comparison with a raw string-value comparison for the same status check

**File:** `app/Http/Controllers/Artist/JobOrderQueueController.php:71,91`
**Issue:** Both guards read `$jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design'`. The first half compares the enum instance directly (idiomatic, matches every other guard in this phase); the second half unwraps `->value` and compares to a raw string literal, for no apparent reason — `JobOrderStatus::InDesign` exists and was added in the same phase (Plan 04-03), just after this code was originally written in Plan 04-01. The inconsistency is cosmetic today but is exactly the kind of drift that (per WR-02) becomes a real bug if the enum's underlying string values ever change.
**Fix:** `$jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status === JobOrderStatus::InDesign`.

### IN-02: `PerformanceReportFilterRequest` doesn't validate `from <= to`

**File:** `app/Http/Requests/Artist/PerformanceReportFilterRequest.php:15-21`
**Issue:** `from`/`to` are independently validated as nullable dates with no cross-field check. An inverted range (`to` earlier than `from`) isn't rejected — it just silently produces an empty/zero report rather than a validation error explaining why. Low impact (no crash, no data risk), but a confusing dead end for the Artist using the date filter.
**Fix:** Add an `after()` hook rejecting `to < from` when both are present, per the validation.md rule's guidance on cross-field checks.

### IN-03: Remote review image link expires in 10 minutes, shorter than a realistic customer review session

**File:** `app/Http/Controllers/Public/DesignReviewController.php:106`
**Issue:** `Storage::disk('local')->temporaryUrl($jobOrder->designFile->file_path, now()->addMinutes(10))` mirrors the Artist workspace's own 10-minute image URL (reasonable there, since the Artist can just reload). On the public remote-review page, the *whole page* (not just the image) is meant to sit open in a customer's inbox/browser for however long it takes them to look at a design and decide — the 7-day link itself acknowledges this. If a customer opens the link, steps away, and comes back more than 10 minutes later, the design `<img>` silently breaks (no error state, no retry) while the Approve/Request Changes buttons remain fully live and clickable.
**Fix:** Either lengthen the image URL's expiry to something closer to a realistic review session (e.g. 1 hour), or add a lightweight client-side "image failed to load — refresh the page" fallback state.

---

_Reviewed: 2026-09-03T15:10:40Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
