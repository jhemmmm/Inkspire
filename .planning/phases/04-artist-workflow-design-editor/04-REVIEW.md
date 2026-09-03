---
phase: 04-artist-workflow-design-editor
reviewed: 2026-09-03T16:59:55Z
depth: standard
files_reviewed: 58
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
  critical: 0
  warning: 10
  info: 4
  total: 14
status: issues_found
---

# Phase 4: Code Review Report

**Reviewed:** 2026-09-03T16:59:55Z
**Depth:** standard
**Files Reviewed:** 58
**Status:** issues_found

## Narrative Findings (AI reviewer)

### Summary

This is a re-review of the full Phase 4 scope after plan 04-13 closed the previous pass's sole Critical finding (CR-01: unguarded `Mail::send()` in `RecordDesignRevision`). That fix was independently re-verified this pass — see "Resolved Since Last Review" below — and is sound; it is not re-flagged.

Beyond that closure, none of the previous review's Warning/Info findings have been addressed in the current source (re-verified line-by-line against the current file contents, not assumed from the prior report): the `sendForReview` concurrency gap, the `revision_logs.outcome` magic-string duplication, the missing upload size cap, the global `InvalidSignatureException` handler, the unconfirmed public "Client Approved" click, the undifferentiated "Next" button, and the dead `tui-image-editor.d.ts` shim are all still present and are carried forward with re-verified file/line references. This pass also surfaces three findings not raised previously: a first-verdict-wins race between the in-person and remote review endpoints, a design-file upload that is stored on disk *inside* the DB transaction that can persist it (risking an orphaned file on rollback), and missing error handling around the canvas-export/PSD-import async flows on the Job Order Workspace page.

The full local Pest suite for this phase's files (61 tests across the 11 listed test files) passes, and scoped `phpstan`/Larastan analysis on the reviewed `app/` files reports zero errors.

### Resolved Since Last Review

**CR-01 (previously Critical) — unguarded `Mail::send()` in `RecordDesignRevision` — now closed.** `app/Actions/JobOrder/RecordDesignRevision.php:46-50` wraps the post-commit `Mail::to(...)->send(...)` call in `try { ... } catch (\Throwable $e) { report($e); }`. This is verified sound:
- The transactional core (file store, `DesignFile` upsert, `RevisionLog` insert, status advance) still commits before the mail call, so the write is never rolled back by a mail failure.
- `\Throwable` (not just `\Exception`) is caught, so a `TypeError`/`Error` from a misconfigured mailer or a null `queueEntry->customer->email` chain is also swallowed rather than surfacing a 500.
- `report($e)` routes the failure to the configured exception handler/log, so a silent mail failure is still observable operationally instead of being lost entirely.
- `tests/Feature/Artist/SendForReviewTest.php`'s `'sending for review still succeeds and commits the revision even when the mail transport throws'` test exercises this exact path with `Mail::shouldReceive('send')->andThrow(...)` + `Exceptions::fake()`/`assertReported()`, and passes.

No further action needed here.

### Warnings

#### WR-01: No concurrency guard on `sendForReview` — duplicate near-simultaneous submissions can create an orphaned `revision_logs` row

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:55-67`, `app/Actions/JobOrder/RecordDesignRevision.php:26-51`
**Issue:** `sendForReview()`'s guards (`abort_if($jobOrder->status === JobOrderStatus::Assigned, ...)`, the `PendingReview` check, the lock check) all read `$jobOrder`'s state with a plain, unlocked `SELECT` from route-model binding. There is no `lockForUpdate()` and no unique-constraint-backed idempotency key on `revision_logs`. Two near-simultaneous requests for the same job order (double submit before the button's `:disabled="processing"` state propagates, a client-side retry after a slow/dropped response, or two tabs) can both read the same pre-commit state, both pass the guards, and both execute `RecordDesignRevision`. `design_files` is protected by a real unique index on `job_order_id` so it just settles on whichever `updateOrCreate` commits last, but `revision_logs` has no such protection: two rows get created, `DesignEditorController::latestUnreviewedRevisionLog()` (which resolves the single newest-by-`submitted_at` row via `firstOrFail()`) only ever surfaces one of them, and the other is permanently stuck with `outcome = null` — invisible in the UI, and inflating `PerformanceReportController`'s `revision_logs_count`-based `avgRevisions` stat for that artist. Note this codebase already has the pattern for fixing this: `AssignArtistToJobOrder::__invoke()`/`claimOldestUnassigned()` (`app/Actions/JobOrder/AssignArtistToJobOrder.php`) both wrap their read-check-mutate sequence in `DB::transaction()` with `lockForUpdate()` specifically to prevent this class of race — `RecordDesignRevision` does not follow that established precedent.
**Fix:** Re-fetch and lock the job order inside the transaction before checking/advancing status, e.g.:
```php
$revisionLog = DB::transaction(function () use ($jobOrder, $file): RevisionLog {
    $jobOrder = JobOrder::whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();
    abort_if($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This design is already pending review.');
    // ... existing body ...
});
```

#### WR-02: Race between the in-person and remote review endpoints can violate "first verdict wins"

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:73-119`, `app/Http/Controllers/Public/DesignReviewController.php:33-77,127-130`
**Issue:** Both the artist's in-person `approve()`/`requestChanges()` and the public remote `approve()`/`requestChanges()` read `$jobOrder->status`/`$revisionLog->outcome` (via `isActionable()` on the public side, direct `abort_unless` on the artist side) *before* opening a `DB::transaction()`, with no row lock. These two entry points act on the exact same `RevisionLog`/`JobOrder` row and are designed to race against each other in the real workflow this app models: an artist confirming "Client Approved" in person at the counter at the same moment the client (who was also emailed the remote link) taps "Client Approved" or "Client Requested Changes" on their phone. If both requests read the pre-commit state before either commits, both guards pass and both mutations apply — e.g. the in-person `approve()` sets `designFile.locked_at` and `status = design_approved`, then the remote `requestChanges()` commits after it and resets `status = in_design` (while never touching `locked_at`, per its own docblock), leaving the job order `in_design` with a permanently-locked design file — a stuck state that isn't reachable through either single-actor code path and requires an Owner unlock override to recover from. This directly undermines the D-20 "first verdict wins" guarantee both docblocks claim.
**Fix:** Lock the `RevisionLog` row for update inside the transaction and re-check `isActionable`/status after acquiring the lock, in both controllers, before mutating:
```php
DB::transaction(function () use ($revisionLog, $jobOrder): void {
    $revisionLog = RevisionLog::whereKey($revisionLog->id)->lockForUpdate()->first();
    if (! $this->isActionable($revisionLog, $jobOrder->fresh())) {
        return;
    }
    // ... existing mutation ...
});
```

#### WR-03: `SendForReviewRequest` has no upper bound on upload size

**File:** `app/Http/Requests/Artist/SendForReviewRequest.php:23`
**Issue:** The validation rule is `['required', 'file', 'image', 'mimes:png']` — no `max:` constraint. `image`/`mimes` validate content type, not size, so any authenticated Artist session can POST an arbitrarily large PNG (bounded only by PHP's `upload_max_filesize`/`post_max_size` ini settings, which are typically far larger than any realistic canvas export) directly to this endpoint, which then gets written to local disk storage with no application-level ceiling.
**Fix:** Add a `max:` rule sized to a realistic canvas export, e.g. `'file' => ['required', 'file', 'image', 'mimes:png', 'max:10240']` (10MB), and validate the number against `ToastImageEditor.vue`'s actual `cssMaxWidth`/`cssMaxHeight` output.

#### WR-04: Global `InvalidSignatureException` handler hard-codes the Design Review page for every signed route in the app

**File:** `bootstrap/app.php:43-46`
**Issue:**
```php
if ($e instanceof InvalidSignatureException) {
    return Inertia::render('public/DesignReview', ['state' => 'expired'])->toResponse($request)->setStatusCode(403);
}
```
is registered in the application-wide `respond()` closure with no route scoping, so it intercepts `InvalidSignatureException` from *any* signed route, not just `public.design-review.*`. Currently harmless only because `config/fortify.php` doesn't enable `Features::verifyEmail()` (confirmed: no other `signed`-middleware route exists in the app today). The moment a future phase enables email verification, adds a QR-tracking signed link, or any other signed route, an expired/tampered signature on that unrelated route will incorrectly render the Design Review "expired" page instead of a message relevant to that feature.
**Fix:** Scope the branch, e.g. `if ($e instanceof InvalidSignatureException && $request->routeIs('public.design-review.*')) { ... }`, and fall through to a generic response otherwise.

#### WR-05: Public remote-review page lets a client irreversibly approve a design with a single click, no confirmation

**File:** `resources/js/pages/public/DesignReview.vue:50-63` (compare `resources/js/pages/artist/JobOrderWorkspace.vue:281-325`)
**Issue:** The Artist's in-person workspace wraps "Client Approved" in an `AlertDialog` specifically because the action is irreversible without an Owner override ("Once approved, this design file becomes read-only. Only an Owner can unlock it for further edits."). The public remote-review page — D-17's second entry point to the exact same irreversible outcome — submits the `Form` directly on click with no confirmation step. This is the one page in the app most likely to be opened on a mobile device (it's reached via an emailed link), so a stray tap permanently locks the design with no recovery path short of contacting the shop for an Owner override.
**Fix:** Add an equivalent confirm step (a native `confirm()` call before submitting is sufficient here, since this page intentionally carries no shared dialog chrome).

#### WR-06: Dashboard shows a "Next" action on every `assigned` row, not just the actually-claimable one

**File:** `resources/js/pages/artist/Dashboard.vue:241-258`
**Issue:** `JobOrderQueueController::next()` only permits claiming the single oldest eligible `assigned` job order (`oldestEligibleId()`, `app/Http/Controllers/Artist/JobOrderQueueController.php:109-117`); every other `assigned` row 422s with "Another job order is next in your queue." The Dashboard template renders "Next" on every row where `jobOrder.status === 'assigned'`, with no indication of which row is actually next. `JobOrderQueueController::index()` doesn't expose which id is oldest-eligible to the frontend, so an Artist with more than one queued consultation (a normal scenario) sees an identical, clickable "Next" button on rows guaranteed to fail — confirmed by `QueueControlsTest.php`'s own "next on a non-oldest eligible job order returns a 422" test, which demonstrates the server-side behavior the UI gives no visual cue about.
**Fix:** Expose the oldest-eligible id from `index()` and only render "Next" (or render it enabled) for that row; disable/hide it for the rest.

#### WR-07: Hand-written `tui-image-editor` type shim is dead code — the installed package ships its own, different bundled types

**File:** `resources/js/types/tui-image-editor.d.ts:1-26`
**Issue:** Re-verified this pass: `node_modules/tui-image-editor/index.d.ts` (334 lines) ships with the installed package and declares `interface IIncludeUIOptions`, `uiSize`, and `export = tuiImageEditor.ImageEditor` — none of which appear in this project's own shim, which uses inline anonymous object types and `export default class ImageEditor`. `tsconfig.json` has no `paths` remap or `typeRoots` override for `tui-image-editor`, and `moduleResolution: "bundler"` resolves the real package's bundled `index.d.ts` through ordinary Node-style resolution. The project's own ambient `declare module 'tui-image-editor'` in this file is, per the prior review's direct reproduction (introducing a deliberately-invalid `includeUI` property and observing `vue-tsc --noEmit` report the error against the real package's `IIncludeUIOptions`, a type name that exists only in the real bundled types), never actually consulted by the compiler. Beyond being unused, it's also incomplete relative to the real API (missing `uiSize`, `loadImageFromFile`, etc.) and declares the constructor's `options` parameter as optional when the real signature requires it — a future contributor trusting this file as documentation will be misled.
**Fix:** Delete `resources/js/types/tui-image-editor.d.ts` and confirm `npm run types:check` still passes (it will, since the real bundled types take over).

#### WR-08: Design file is written to disk *inside* the DB transaction that persists it — an orphaned file on rollback

**File:** `app/Actions/JobOrder/RecordDesignRevision.php:26-44`
**Issue:** `$file->store('design-files', 'local')` runs as the first statement inside `DB::transaction(...)`. Local filesystem writes are not transactional — if any subsequent statement in the same closure throws (e.g. a DB constraint violation on `RevisionLog::create()` or `JobOrder::save()`, a connection drop mid-transaction), Laravel rolls back the `DesignFile`/`RevisionLog`/`JobOrder` writes, but the PNG file that was already written to `storage/app/private/design-files/` is never cleaned up. Over time this leaves orphaned files with no referencing database row and no cleanup path.
**Fix:** Either store the file after the transaction commits (requires restructuring since `DesignFile::updateOrCreate` needs the path), or wrap the whole operation and explicitly delete the file on failure:
```php
$path = $file->store('design-files', 'local');

try {
    $revisionLog = DB::transaction(function () use ($jobOrder, $path) { /* ... */ });
} catch (\Throwable $e) {
    Storage::disk('local')->delete($path);
    throw $e;
}
```

#### WR-09: Missing error handling around canvas export / PSD import / `start` transition on the Job Order Workspace page

**File:** `resources/js/pages/artist/JobOrderWorkspace.vue:89-92,111-142,145-156`
**Issue:** Three related gaps, all in the same file:
- `sendForReview()` (145-156) calls `editorRef.value!.exportPng()` and `await (await fetch(dataUrl)).blob()` with no `try`/`catch`. If canvas export throws (e.g. the editor failed to initialize) or the `fetch()` of the in-memory `data:` URL rejects, the `async` function's promise rejects silently — no toast, no visible error state — and `sendForReviewForm.processing` never even becomes `true` (since `.post()` is never reached), so the button just appears to do nothing and the Artist has no feedback to act on.
- `onStartBlankCanvas()` (89-92) and `onReferenceFileChosen()` (111-142) both call `router.patch(start.url(...), {}, { preserveScroll: true })` with no `onError` callback. `started.value = true` is set synchronously before the request resolves, so if the PATCH fails (network error, or a 422 if the job order's status already advanced), the client-side "started" state silently diverges from the server's actual status with no feedback to the Artist.
**Fix:** Wrap `sendForReview()`'s export/fetch in `try`/`catch` and surface a `toast.error(...)` on failure (the file already imports `toast` from `vue-sonner` for the PSD-import failure path, so the pattern is established); add `onError` handlers to the two `router.patch()` calls that at minimum toast an error so the Artist knows to retry.

### Info

#### IN-01: `forward()`/`notAppear()` mix enum-case comparison with a raw string-value comparison for the same status check

**File:** `app/Http/Controllers/Artist/JobOrderQueueController.php:71,91`
**Issue:** Both guards read `$jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design'`. The first half compares the enum instance directly (idiomatic, matches every other guard in this file and phase); the second half unwraps `->value` and compares to a raw string literal, even though `JobOrderStatus::InDesign` exists in the same enum (`app/Enums/JobOrderStatus.php:12`) and is used correctly elsewhere in this exact file (e.g. implicitly via other status checks). Functionally correct today since the string matches, but it's an inconsistency that bypasses the enum's type safety and would silently stop matching if the enum's backing value ever changed without this literal being updated in lockstep.
**Fix:** `$jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status === JobOrderStatus::InDesign` in both `forward()` and `notAppear()`.

#### IN-02: `PerformanceReportFilterRequest` doesn't validate `from <= to`

**File:** `app/Http/Requests/Artist/PerformanceReportFilterRequest.php:15-21`
**Issue:** `from`/`to` are independently validated as nullable dates with no cross-field check. An inverted range (`to` earlier than `from`) isn't rejected — `PerformanceReportController`'s filter logic just silently produces a zeroed-out report instead of a validation error explaining why, which is a confusing dead end for the Artist using the date filter.
**Fix:** Add a rule (e.g. `'to' => ['nullable', 'date', 'after_or_equal:from']`) rejecting an inverted range when both are present.

#### IN-03: Remote review image link expires in 10 minutes, shorter than a realistic customer review session

**File:** `app/Http/Controllers/Public/DesignReviewController.php:106`
**Issue:** `Storage::disk('local')->temporaryUrl($jobOrder->designFile->file_path, now()->addMinutes(10))` mirrors the Artist workspace's own 10-minute image URL (reasonable there, since the Artist can just reload the page). On the public remote-review page, the page itself is designed to sit open in a customer's inbox/browser for as long as it takes them to decide — the 7-day page-level signed link acknowledges this. If a customer opens the link, steps away, and returns more than 10 minutes later, the design `<img>` silently breaks (no error state, no retry affordance) while the Approve/Request Changes buttons remain fully live.
**Fix:** Lengthen the image URL's expiry to something closer to a realistic review session (e.g. 1 hour), or add a lightweight client-side "image failed to load — refresh the page" fallback.

#### IN-04: Leftover commented-out import in `User.php`

**File:** `app/Models/User.php:5`
**Issue:** `// use Illuminate\Contracts\Auth\MustVerifyEmail;` is dead, commented-out code left over from the Laravel starter kit scaffold. It doesn't affect behavior but is noise that a future reader has to mentally discard.
**Fix:** Remove the line (or, if email verification is genuinely planned, implement `MustVerifyEmail` for real rather than leaving a commented placeholder).

---

_Reviewed: 2026-09-03T16:59:55Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
