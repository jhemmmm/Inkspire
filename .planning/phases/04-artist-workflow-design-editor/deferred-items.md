# Deferred Items — Phase 4

Items discovered during execution that are out of scope for the current task/plan.

## 04-01: Larastan (`composer types:check`) fails to bootstrap in this worktree

**Discovered during:** Plan 04-01, Task 3 verification pass.

**Symptom:**

```
PHP Warning: PHP Startup: Unable to load dynamic library '.../vendor/phpstan/phpstan/turbo-ext/linux-gnu-x86_64/phpstan_turbo-8.4.so'
(/lib/x86_64-linux-gnu/libc.so.6: version `GLIBC_2.33' not found)
...
In LarastanStubFilesExtension.php line 25:
Undefined constant "Larastan\Larastan\LARAVEL_VERSION"
```

**Analysis:** The `phpstan/phpstan` turbo-ext compiled binary requires a newer glibc than this
sandboxed worktree environment provides. Loading `bootstrap/app.php` and bootstrapping the
Kernel in isolation (`php -r '...'`) works correctly and defines `LARAVEL_VERSION` as expected —
this rules out an application-code or route-registration problem. The failure appears to be a
toolchain/environment incompatibility between the installed `phpstan/phpstan` binary and this
container's glibc version, not something introduced by this plan's changes.

**Scope:** Pre-existing environment issue, not caused by Plan 04-01's code changes. The plan's own
`<verification>` block only requires Pest tests + `migrate:fresh`, both of which pass. Not
auto-fixed per the Scope Boundary rule (out-of-scope, environment-level).

**Suggested follow-up:** Verify this same command on a real dev machine / CI runner (outside this
sandboxed worktree) before treating it as a real blocker. If it reproduces there too, investigate
downgrading/reinstalling `phpstan/phpstan` without the turbo-ext, or pin a compatible version.

## 04-07: Pre-existing Larastan errors in unrelated files (Phase 3 origin)

**Discovered during:** Plan 04-07, full-repo `phpstan analyse` verification pass (not required by
this plan's own `<verification>` block, run as an extra precaution).

**Symptom:**

```
app/Http/Controllers/FrontlineStaff/QueueEntryController.php:181
  Match expression does not handle remaining values: App\Enums\JobOrderStatus::DesignApproved|
  InConsultation|InDesign|PendingReview

app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20
  Access to an undefined property (object|string)::$type.
```

**Analysis:** Both files were last modified by Phase 2/3 commits (`0f658f8`, `a0c5e8d`, `57ac8fb`)
— none of which are touched by this plan's `files_modified` list (`ArtistStatus.php`, the
`artist_status`/`break_started_at` migration, `User.php`, `SetArtistSessionStatus.php`,
`SessionStatusController.php`, `UpdateSessionStatusRequest.php`, `portals.php`,
`JobOrderQueueController.php`, `UserManagementController.php`). The `QueueEntryController.php`
match-expression gap predates this plan (it never handled the `InConsultation`/`InDesign`/
`PendingReview`/`DesignApproved` cases Phase 4's earlier plans (04-01 through 04-06) already added
to `JobOrderStatus`), and `UpdateSystemConfigurationRequest.php`'s property-access issue is
unrelated to artist session status entirely.

**Scope:** Out of scope per the Scope Boundary rule — pre-existing, in files this plan does not
modify. Plan 04-07's own `<verification>` block (`Artist`, `UserManagementTest`,
`AssignArtistToJobOrderTest` filters) passes cleanly with zero regressions. Not auto-fixed.

**Suggested follow-up:** A future Phase 4 plan (or a dedicated cleanup pass) should add the missing
`match` arms in `QueueEntryController.php` and investigate the `UpdateSystemConfigurationRequest.php`
property-access type gap.
