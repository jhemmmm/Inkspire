---
phase: 04-artist-workflow-design-editor
reviewed: 2026-09-02T00:00:00Z
depth: standard
files_reviewed: 52
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
  - app/Http/Requests/Artist/PerformanceReportFilterRequest.php
  - app/Http/Requests/Artist/RecordDesignVerdictRequest.php
  - app/Http/Requests/Artist/SendForReviewRequest.php
  - app/Http/Requests/Artist/StartDesignRequest.php
  - app/Http/Requests/Artist/UpdateConsultationNotesRequest.php
  - app/Http/Requests/Artist/UpdateJobOrderQueuePositionRequest.php
  - app/Http/Requests/Artist/UpdateSessionStatusRequest.php
  - app/Http/Requests/Owner/UnlockDesignFileRequest.php
  - app/Models/DesignFile.php
  - app/Models/JobOrder.php
  - app/Models/RevisionLog.php
  - app/Models/User.php
  - app/Policies/DesignFilePolicy.php
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
  - resources/js/types/tui-image-editor.d.ts
  - routes/owner.php
  - routes/portals.php
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
findings:
  critical: 0
  warning: 6
  info: 3
  total: 9
status: issues_found
---

# Phase 04: Code Review Report

**Reviewed:** 2026-09-02T00:00:00Z
**Depth:** standard
**Files Reviewed:** 52
**Status:** issues_found

## Summary

Reviewed the Artist consultation/queue workflow, the TOAST UI design editor integration, the design review/lock/override flow, artist session status, and the artist performance report, plus the Owner-side surfaces that consume this phase's data (Design Overrides, User Management's artist-status additions). The core state machine (Assigned → In Consultation → In Design → Pending Review → Design Approved, plus the Not-Appeared/Forward detours and the Owner unlock override) is implemented consistently, every mutating endpoint re-checks `assigned_artist_id` ownership server-side, and the controller↔Vue prop shapes line up correctly across all five controller/page pairs I traced (no repeat of the two integration bugs the build gate already caught).

No blocker-severity issues were found. The issues below are all robustness/consistency gaps: a couple of concurrency races on the design-verdict endpoints that lack the row-locking pattern already established elsewhere in this codebase (`AssignArtistToJobOrder`), an unbounded storage leak on every design re-submission, a backend `canEdit` flag whose correctness during `pending_review` is masked only by frontend `v-if` ordering, and an Owner-only unlock control that Admins can reach but never successfully use. None of these are exploitable by an unauthorized party — ownership and role checks are correctly enforced server-side throughout — but several should be fixed before this ships to avoid confusing dead-ends and rare-but-real data inconsistency.

## Warnings

### WR-01: Concurrent verdict actions on the same job order can leave an inconsistent locked/status combination

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:73-119`
**Issue:** `approve()` and `requestChanges()` both (a) read `$jobOrder->status` outside any transaction/lock, then (b) inside a `DB::transaction()`, re-select the "latest unreviewed" `RevisionLog` via `whereNull('outcome')->latest('submitted_at')->firstOrFail()` with no `lockForUpdate()`. If both actions are triggered for the same `pending_review` job order in close succession (e.g. a user opens the "Client Approved" confirmation dialog and also clicks "Client Requested Changes" before confirming, or a network retry double-fires one of the two `<Form>` submissions), both requests can pass the initial `abort_unless` status check before either commits. Whichever transaction's writes land last wins independently per table: it's possible to end up with `design_files.locked_at` set (from `approve()`) while `job_orders.status` is `in_design` (from `requestChanges()` committing after), or a `revision_logs.outcome` that doesn't match what actually happened. This leaves the job order in a state that isn't reachable through normal use and isn't self-healing except through an Owner unlock override.

This is the same class of problem `App\Actions\JobOrder\AssignArtistToJobOrder` already solves with `lockForUpdate()` for round-robin assignment (see its own docblock reference to "T-03-02" concurrency safety) — that established pattern wasn't carried over to the design-verdict endpoints.
**Fix:** Lock the job order (or its revision log) for the duration of the transaction, and re-verify status after acquiring the lock:
```php
DB::transaction(function () use ($jobOrder) {
    $jobOrder->refresh(); // or lockForUpdate() on a fresh query
    JobOrder::whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

    abort_unless($jobOrder->fresh()->status === JobOrderStatus::PendingReview, 422, 'This job order is not pending review.');

    $this->latestUnreviewedRevisionLog($jobOrder)
        ->lockForUpdate() // or select the row inside the same locked query
        ->forceFill([...])->save();
    // ...
});
```

### WR-02: Design file storage leaks a copy on every re-submission

**File:** `app/Actions/JobOrder/RecordDesignRevision.php:20-37`
**Issue:** `DesignFile::updateOrCreate(['job_order_id' => $jobOrder->id], ['file_path' => $path])` overwrites the `file_path` column with the newly stored export, but the *previous* file at the old `file_path` is never deleted from disk. `SendForReviewTest`'s "sending for review twice ... design_files stays a single row with the newest file_path" test confirms the DB row is correctly overwritten, but the orphaned first upload remains on the `local` disk forever. Every consultation/change-requested cycle leaves another orphaned PNG behind with no cleanup path anywhere in this phase.
**Fix:** Capture and delete the old path inside the same transaction:
```php
DB::transaction(function () use ($jobOrder, $file): void {
    $oldPath = $jobOrder->designFile?->file_path;
    $path = $file->store('design-files', 'local');

    DesignFile::updateOrCreate(
        ['job_order_id' => $jobOrder->id],
        ['file_path' => $path],
    );

    if ($oldPath !== null) {
        Storage::disk('local')->delete($oldPath);
    }
    // ...
});
```

### WR-03: `design.canEdit` is computed incorrectly while `pending_review`, masked only by frontend branch ordering

**File:** `app/Http/Controllers/Artist/JobOrderWorkspaceController.php:39`, `resources/js/pages/artist/JobOrderWorkspace.vue:184-195`
**Issue:** `'canEdit' => $jobOrder->status !== JobOrderStatus::Assigned && optional($jobOrder->designFile)->locked_at === null` evaluates to `true` while the job order is `pending_review` (design file exists, isn't locked yet). The only reason the UI doesn't currently show an editable canvas during review is that `JobOrderWorkspace.vue`'s template checks `v-if="isPendingReview"` *before* `v-else-if="!design.canEdit"` (lines 184-195), so the correctly-computed `review.canRecordVerdict` flag happens to shadow the incorrectly-computed `design.canEdit` flag. `design.canEdit`'s stated contract ("can the artist edit this design right now") is false during `pending_review`, and nothing prevents a future change to the template's branch order (or a new consumer of this prop) from exposing the bug.
**Fix:** Make the backend flag correct on its own terms:
```php
'canEdit' => ! in_array($jobOrder->status, [JobOrderStatus::Assigned, JobOrderStatus::PendingReview], true)
    && optional($jobOrder->designFile)->locked_at === null,
```

### WR-04: Design Overrides page exposes a non-functional "Unlock Design" control to Admins

**File:** `resources/js/pages/owner/DesignOverrides.vue:107-156`, `routes/owner.php:17-18`, `app/Policies/DesignFilePolicy.php:18-21`
**Issue:** `routes/owner.php` gates both `design-overrides.index` and `design-files.unlock` behind `role:owner,admin`, so an Admin can load the Design Overrides page. But `DesignFilePolicy::unlock()` deliberately restricts the actual unlock to Owner only, and `UnlockDesignFileRequest::authorize()` enforces that policy. `DesignOverrides.vue` doesn't branch on the current user's role at all — every visitor sees a fully-enabled "Unlock Design" button and confirmation dialog for every row. When an Admin submits it, the backend correctly 403s, but since this app renders a full branded 403 page for forbidden Inertia requests (see `errors/Forbidden.vue`, wired in the same phase range), the Admin is yanked off the Design Overrides list entirely rather than shown an inline error. There's also no test covering "Admin can view the index but cannot unlock" — `UnlockDesignFileTest.php` only asserts the endpoint itself 403s for Admin, not what happens in the list UI.
**Fix:** Hide (or disable with an explanatory tooltip) the unlock control for non-Owner roles, using the already-shared `auth.user` prop:
```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
const isOwner = computed(() => usePage().props.auth.user.role === 'owner');
</script>
...
<AlertDialog v-if="isOwner"> ... </AlertDialog>
<Badge v-else variant="outline">Owner-only</Badge>
```
Add a companion feature test asserting an Admin sees the locked-design list but the page/controls reflect that they can't act on it (or simply assert the button is absent/disabled in an Inertia test if the project has that pattern established elsewhere).

### WR-05: Inconsistent enum comparison — magic string bypasses `JobOrderStatus::InDesign`

**File:** `app/Http/Controllers/Artist/JobOrderQueueController.php:71`, `:91`
**Issue:** `forward()` and `notAppear()` both write:
```php
abort_unless($jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design', ...);
```
`JobOrderStatus::InDesign` exists and is used as a first-class enum case everywhere else in this phase (`DesignEditorController`, `JobOrderStatus` itself). Comparing `->value` against a raw string literal instead of comparing the enum directly is inconsistent with the rest of the codebase, discards the compiler/PHPStan's ability to catch a typo'd status string, and would silently stop matching if `JobOrderStatus::InDesign`'s backing value were ever renamed without updating this literal.
**Fix:**
```php
abort_unless($jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status === JobOrderStatus::InDesign, ...);
```

### WR-06: `next()` skips the Assigned-status guard entirely for `not_appeared` job orders, relying on an unenforced invariant

**File:** `app/Http/Controllers/Artist/JobOrderQueueController.php:43-61`
**Issue:**
```php
if (! $jobOrder->not_appeared) {
    abort_unless($jobOrder->status === JobOrderStatus::Assigned, 422, ...);
    abort_unless($this->oldestEligibleId($request->user()) === $jobOrder->id, 422, ...);
}

$jobOrder->forceFill(['status' => JobOrderStatus::InConsultation, ...])->save();
```
When `not_appeared` is `true`, the method skips *both* the status check and the ordering check and unconditionally forces the job order into `in_consultation`. This is currently safe only because every write path that sets `not_appeared = true` (`notAppear()`) also always sets `status = Assigned` in the same write, and no other write path changes `status` away from `Assigned` while `not_appeared` stays `true`. That invariant is not enforced anywhere (no DB constraint, no model-level guard) — it's an implicit contract between two controller methods. Any future endpoint that touches `not_appeared` or a job order's `status` independently (a very plausible addition as this workflow grows) could silently let `next()` force a job order at an unexpected status (e.g. `pending_review`, `design_approved`) into `in_consultation`.
**Fix:** Defensively re-assert the status this branch actually depends on:
```php
if (! $jobOrder->not_appeared) {
    abort_unless($jobOrder->status === JobOrderStatus::Assigned, 422, 'This job order is not waiting to be called.');
    abort_unless($this->oldestEligibleId($request->user()) === $jobOrder->id, 422, 'Another job order is next in your queue.');
} else {
    abort_unless($jobOrder->status === JobOrderStatus::Assigned, 422, 'This job order cannot be resumed in its current status.');
}
```

## Info

### IN-01: `SendForReviewRequest` has no upper bound on upload size

**File:** `app/Http/Requests/Artist/SendForReviewRequest.php:20-25`
**Issue:** `'file' => ['required', 'file', 'image', 'mimes:png']` restricts type but not size. A flattened canvas export from `ToastImageEditor` can be large for complex designs, and nothing in this request caps it (falls back entirely to `php.ini`'s `upload_max_filesize`/`post_max_size`, which aren't part of this codebase's own guarantees).
**Fix:** Add an explicit `max:` rule sized to a sane export ceiling, e.g. `'mimes:png', 'max:10240'` (10MB), and surface a friendly validation error rather than relying on a hard server-level cutoff.

### IN-02: `SetArtistSessionStatus` resolves a dependency via the service locator instead of constructor injection

**File:** `app/Actions/JobOrder/SetArtistSessionStatus.php:31`
**Issue:** `app(AssignArtistToJobOrder::class)->claimOldestUnassigned($artist)` uses the `app()` helper inside `__invoke()`, whereas sibling classes in this same phase (`DesignEditorController`, `SessionStatusController`) consistently use PHP 8 constructor property promotion for their dependencies, per this project's own PHP conventions. This is a minor inconsistency that also makes the dependency harder to mock/substitute in isolation tests.
**Fix:**
```php
public function __construct(private readonly AssignArtistToJobOrder $assignArtistToJobOrder) {}

public function __invoke(User $artist, ArtistStatus $status): void
{
    // ...
    if ($status === ArtistStatus::Available) {
        $this->assignArtistToJobOrder->claimOldestUnassigned($artist);
    }
}
```

### IN-03: Double-submitted verdict surfaces a raw 404 instead of a domain error

**File:** `app/Http/Controllers/Artist/DesignEditorController.php:27-30`
**Issue:** `latestUnreviewedRevisionLog()`'s `firstOrFail()` throws `ModelNotFoundException` (→ generic 404) if called when no unresolved revision log exists for the job order (e.g. a retried/duplicate `approve`/`requestChanges` request after the first one already resolved the log — see WR-01). A 404 is a confusing response for what is really "this verdict was already recorded."
**Fix:** Replace `firstOrFail()` with an explicit check that produces a `422` consistent with this controller's other guards:
```php
private function latestUnreviewedRevisionLog(JobOrder $jobOrder): RevisionLog
{
    return $jobOrder->revisionLogs()->whereNull('outcome')->latest('submitted_at')->first()
        ?? abort(422, 'This design has already been reviewed.');
}
```

---

_Reviewed: 2026-09-02T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
