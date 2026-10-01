---
status: complete
---

# Quick Task 261001-gkq: Profile pictures, editable by Admin and by each user — Summary

One-liner: Every user can now have a profile picture — a nullable `avatar_path` column plus `User::replaceAvatar()` as the single file-writer (store-then-save-then-delete), `avatarRules()` (image/mimes/max:2048, rejecting SVG twice over) shared by the Create User, Update User and Profile Update requests, and one `AvatarField.vue` component (pick/preview/remove/undo) wired into User Management's create and edit dialogs, the User Management table, and Settings > Profile.

## What changed, per task

**Task 1 — Backend: schema, model, validation, controllers, tests**

- `database/migrations/2026_10_01_041131_add_avatar_path_to_users_table.php`: nullable `avatar_path` string column `after('artist_label')`, mirroring the existing `artist_label` migration's style. `down()` drops it. Not yet run against the dev database (see User Setup Required).
- `app/Models/User.php`: `#[Appends(['avatar'])]` class attribute; `avatar()` accessor (`@return Attribute<string|null, never>`, matching `JobOrder::displayTotal()`'s existing generics convention) resolving `avatar_path` to a public URL via `Storage::disk('public')->url()`; `replaceAvatar(?UploadedFile $file, bool $remove): void` — the only place that writes an avatar file. No-op when neither a file nor removal is requested (an ordinary name/email edit never touches storage). Otherwise stores the new file (or nulls the path), saves, then deletes the previous file only after the new state is persisted — a failure between those steps never leaves the user pointing at a file that was already deleted.
- `app/Concerns/ProfileValidationRules.php`: `avatarRules()` returns `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']` — `image` and the explicit `mimes` list each independently exclude SVG.
- `CreateUserRequest`, `UpdateUserRequest`, `ProfileUpdateRequest`: all three `rules()` methods gained `'avatar' => $this->avatarRules()` and `'remove_avatar' => ['nullable', 'boolean']`. `avatar_path` was deliberately **not** added to `User`'s `#[Fillable]` list — all three write paths call `replaceAvatar()` directly, bypassing mass assignment.
- `UserManagementController::store()`/`update()`: call `$user->replaceAvatar($request->file('avatar'), $request->boolean('remove_avatar'))` after the existing `save()`. `index()` added `avatar_path` to the `select()` column list (required for the `avatar` accessor to resolve) and `'avatar' => $user->avatar` to the mapped row.
- `ProfileController::update()`: changed `$request->user()->fill($request->validated())` to `$request->user()->fill($request->safe()->except(['avatar', 'remove_avatar']))` so the upload keys never reach `fill()`, then calls `replaceAvatar()` after `save()`.
- Tests added: 4 in `CreateUserTest.php` (valid upload stores file + appears in the Inertia `users` list; non-image, oversized, and SVG uploads each rejected and store nothing), 4 in `UpdateUserTest.php` (replace deletes the old file; `remove_avatar` nulls the column and deletes the file; untouched picture persists; a forbidden non-admin update leaves the picture unchanged), 3 in `ProfileUpdateTest.php` (user sets their own picture; user removes their own picture; the avatar URL appears in `auth.user` via `assertInertia`).

**Task 2 — Frontend: `AvatarField.vue`, wired into both portals**

- `resources/js/components/AvatarField.vue` (new): props `id`, `name`, `avatarUrl?`, `error?`. `previewUrl`/`pendingRemoval`/`fileInputRef` refs; `displayUrl` computed resolves preview → pending-removal-null → the server's `avatarUrl`. Picking a new file revokes any prior preview object URL and cancels a pending removal. "Remove" marks pending removal and clears the file input; "Keep picture" undoes it. The object URL is revoked both on every change and in `onBeforeUnmount`, so nothing leaks. A visible `Label` (`for="{id}-input"`) plus an `Avatar` (`h-16 w-16`, `object-cover` image, initials fallback), a `sr-only` real file input (`accept="image/png,image/jpeg,image/webp"`), "Change/Add picture" and "Remove"/"Keep picture" buttons, and a hidden `remove_avatar` input mirroring the pending-removal state.
- `resources/js/pages/admin/UserManagement.vue`: `ManagedUser.avatar: string | null` added. Create dialog: `newUserAvatarName` ref fed by the Name input's `@input` (side-channel for the live initials fallback, matching the existing `newUserRole` pattern), reset alongside `newUserRole` when the dialog's trigger is clicked; `AvatarField` placed inside the create `<Form>`. Edit dialog: `AvatarField :avatar-url="editingUser.avatar"` inside the edit `<Form>`, which already remounts per `editingUser.id`. Table: the Name column now renders an `Avatar`/`AvatarFallback` (same gradient classes `UserInfo.vue` uses) beside the name — table itself untouched otherwise, per scope.
- `resources/js/pages/settings/Profile.vue`: `AvatarField` added as the first field inside the existing `<Form>`, bound to `user.avatar ?? null`.
- No Wayfinder regeneration needed — `UserManagementController.update.form()`/`ProfileController.update.form()` already emit multipart POST with PATCH method-spoofing, which is what a real file upload on a PATCH route requires.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Handled `UploadedFile::store()`'s `string|false` return in `replaceAvatar()`**

- **Found during:** Task 1, `composer types:check` verification.
- **Issue:** `$file->store('avatars', 'public')` returns `string|false` (false on a storage failure), but the plan's literal code assigned it straight to `$this->avatar_path`, which is documented `string|null`. Larastan (level 7) correctly flagged this as `assign.propertyType` — a real gap: on a storage failure, the model would have silently been left with `avatar_path = false`, an invalid state that would then break the `avatar` accessor and `Storage::disk('public')->url()`.
- **Fix:** Captured the result in a local `$newPath`, used `throw_if($newPath === false, RuntimeException::class, 'Failed to store the uploaded avatar.')` before assigning, so a genuine storage failure throws instead of corrupting the column.
- **Files modified:** `app/Models/User.php`.
- **Verification:** `composer types:check` — 0 errors in `User.php`; `php artisan test` still green (no code path in the test suite exercises a `store()` failure, so behavior for the success path is unchanged).
- **Committed in:** `8be7080` (Task 1).

**2. [Rule 1 — Bug] Added the `Attribute<string|null, never>` generic to `avatar()`**

- **Found during:** Task 1, `composer types:check` verification.
- **Issue:** `missingType.generics` — Larastan level 7 requires the `Attribute` return type's `TGet`/`TSet` to be specified. The codebase already has a precedent (`JobOrder::displayTotal()`).
- **Fix:** Added `@return Attribute<string|null, never>` to `avatar()`'s docblock, matching that precedent exactly.
- **Files modified:** `app/Models/User.php`.
- **Verification:** `composer types:check` — 0 errors in `User.php`.
- **Committed in:** `8be7080` (Task 1).

---

**Total deviations:** 2 (both Rule 1 type-correctness fixes required for Larastan level 7 compliance, confined to `User.php`, the file the plan was already modifying). No scope creep, no behavior change on the success path.

## Files changed

- `database/migrations/2026_10_01_041131_add_avatar_path_to_users_table.php` — new nullable `avatar_path` column
- `app/Models/User.php` — `#[Appends(['avatar'])]`, `avatar()` accessor, `replaceAvatar()`
- `app/Concerns/ProfileValidationRules.php` — `avatarRules()`
- `app/Http/Requests/Admin/CreateUserRequest.php` — `avatar`/`remove_avatar` rules
- `app/Http/Requests/Admin/UpdateUserRequest.php` — `avatar`/`remove_avatar` rules
- `app/Http/Requests/Settings/ProfileUpdateRequest.php` — `avatar`/`remove_avatar` rules
- `app/Http/Controllers/Admin/UserManagementController.php` — `replaceAvatar()` calls in `store()`/`update()`; `avatar_path`/`avatar` in `index()`
- `app/Http/Controllers/Settings/ProfileController.php` — excludes avatar keys from `fill()`, calls `replaceAvatar()`
- `tests/Feature/Admin/CreateUserTest.php` — 4 new tests
- `tests/Feature/Admin/UpdateUserTest.php` — 4 new tests
- `tests/Feature/Settings/ProfileUpdateTest.php` — 3 new tests
- `resources/js/components/AvatarField.vue` — new shared component
- `resources/js/pages/admin/UserManagement.vue` — `AvatarField` in create/edit dialogs, avatar in the table
- `resources/js/pages/settings/Profile.vue` — `AvatarField` as the first field

## Commits

1. `8be7080` — `feat(quick-261001-gkq): user profile pictures backend (avatar_path, replaceAvatar, validation)` (Task 1)
2. `fb2847b` — `feat(quick-261001-gkq): AvatarField component wired into User Management and Profile` (Task 2)

## Verification results

**`php artisan test --compact tests/Feature/Admin tests/Feature/Settings`** (final, both tasks committed):

```
{"tool":"pest","result":"passed","tests":156,"passed":153,"assertions":760,"duration_ms":22314,"skipped":3}
```

145 baseline (142 passed + 3 skipped) + 11 new (4 CreateUserTest + 4 UpdateUserTest + 3 ProfileUpdateTest) = 156. All green, 0 failures. The 3 skipped are the pre-existing xlsx-writer tests gated on `ext-zip` (unrelated to this task).

**`vendor/bin/pint --dirty --format agent`** (final, nothing dirty — already committed): `{"tool":"pint","result":"passed"}`. Ran and passed after each task before its commit; one run during Task 1 auto-fixed a trailing-newline in `ProfileUpdateTest.php` before that commit.

**`composer types:check`** (Larastan level 7, final):

```
{"tool":"phpstan","result":"failed","errors":21}
```

Not a clean pass — reporting plainly per instructions. **Before this task:** 21 pre-existing errors (`DashboardController.php`, `FrontlineStaff/JobOrderController.php`, `FrontlineStaff/QueueEntryController.php`, `Reports/ReportController.php`, `UpdateSystemConfigurationRequest.php`, `CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `ReportBuilder.php`, `SpecificationOptionFactory.php`, `DatabaseSeeder.php`, `DemoDataSeeder.php`). **After:** still exactly 21, same file set. **Zero errors in any file this task created or modified** — confirmed by two intermediate fix-and-reverify cycles (see Deviations 1–2 above); `User.php` is absent from the final error list.

**`npx vp check --fix resources/js/components/AvatarField.vue resources/js/pages/admin/UserManagement.vue resources/js/pages/settings/Profile.vue`** (final): "Formatting completed for checked files", "Found no warnings or lint errors in 3 files". One run during Task 2 reformatted wrapping/line-breaks only (confirmed via `git diff` before staging) — no unrelated tracked files were touched by this narrower, file-scoped invocation.

**`npm run types:check`** (final): `vue-tsc --noEmit` — no output, zero errors. This also confirms the template's `($event.target as HTMLInputElement).value` TypeScript cast (used in both `AvatarField.vue` and the Name-input side-channel in `UserManagement.vue`) compiles cleanly — Vue's template compiler strips TS-only syntax when `<script setup lang="ts">` is used.

## Decisions Made

- Matched the codebase's existing `Attribute<TGet, TSet>` generics convention (`JobOrder::displayTotal()`) for the new `avatar()` accessor, rather than leaving the Larastan `missingType.generics` error unaddressed.
- Treated `UploadedFile::store()`'s `false` return as a genuine failure to surface (`throw_if` + `RuntimeException`) rather than widening `User::$avatar_path`'s documented type or suppressing the Larastan finding — consistent with CLAUDE.md's "fix the underlying cause" instruction and this project's `prohibitDestructiveCommands`-style preference for failing loudly over silently corrupting state.
- Kept `avatar_path` out of `User`'s `#[Fillable]` list exactly as the plan specified (D3) — every write path uses `replaceAvatar()` or `forceFill()`, never raw mass assignment of the picture.

## Issues Encountered

None blocking. The IDE's static-analysis tool flagged a large number of "undefined method" / "cannot find module" diagnostics while editing (`actingAs`, `@inertiajs/vue3`, `@/components/ui/*`, etc.) — these are pre-existing environment noise (Pest's global `TestCase` binding and the `@/*` path alias are both resolved correctly by the real toolchain, as the actual `php artisan test`, `composer types:check`, and `vue-tsc` runs above confirm) and not real defects.

## User Setup Required

**Two commands must be run manually before this feature works in any environment** (per this task's environment constraints, neither was run here):

- `php artisan migrate` — applies the new `avatar_path` column. Tests pass today because Pest's `RefreshDatabase` migrates its own in-memory SQLite database independently of the dev `.env`'s MySQL connection.
- `php artisan storage:link` — creates the `public/storage` symlink so uploaded avatar URLs (`Storage::disk('public')->url(...)`) actually resolve. Without it, every avatar will upload and save successfully but render as a broken image.

## Next Phase Readiness

- `User::replaceAvatar()` and `avatarRules()` are now the one write path and one validation rule set for any future avatar-adjacent feature (e.g., a future artist/customer-facing picture).
- `AvatarField.vue` is a general-purpose, reusable component — any future form needing a picture-with-remove-and-undo control can reuse it as-is.

## Known Stubs

None. `AvatarField` always renders real `avatarUrl`/`name` props from the server; the User Management table's avatar column reads the real `avatar` field added to the `users` Inertia prop in Task 1.

## Threat Flags

None beyond what the plan's own threat model already covers (D5's `image`/`mimes`/`max:2048` upload validation, applied identically across all three request classes). No new route, auth path, or schema trust boundary was introduced outside that model.

## Unverified by this executor (real-browser pass required)

Per this task's constraints, no browser was driven. The orchestrator's real-browser check should specifically confirm, in both themes at 375px and desktop:

- User Management: create a user with a picture; edit and replace it; remove it, then click "Keep picture" to change your mind before saving — confirm the dialog's avatar and the saved result match at each step.
- The User Management table's new avatar column does not break the table's existing horizontal-scroll behavior at 375px.
- After editing your own account's picture (as an Admin) or saving your own picture from Settings > Profile, the sidebar (`UserInfo.vue`) avatar updates on the next page load.
- Keyboard-only flow: Tab to the "Profile Picture" label or "Add/Change picture" button, Enter opens the native file picker; Tab to "Remove"/"Keep picture" and activate with Enter/Space; focus rings visible in both themes.
- The preview object URL swap feels instant when choosing a new file, and no console warnings appear for a revoked-then-reused object URL across repeated picks within one dialog session.

## Self-Check

```
FOUND: database/migrations/2026_10_01_041131_add_avatar_path_to_users_table.php
FOUND: app/Models/User.php
FOUND: app/Concerns/ProfileValidationRules.php
FOUND: app/Http/Requests/Admin/CreateUserRequest.php
FOUND: app/Http/Requests/Admin/UpdateUserRequest.php
FOUND: app/Http/Requests/Settings/ProfileUpdateRequest.php
FOUND: app/Http/Controllers/Admin/UserManagementController.php
FOUND: app/Http/Controllers/Settings/ProfileController.php
FOUND: tests/Feature/Admin/CreateUserTest.php
FOUND: tests/Feature/Admin/UpdateUserTest.php
FOUND: tests/Feature/Settings/ProfileUpdateTest.php
FOUND: resources/js/components/AvatarField.vue
FOUND: resources/js/pages/admin/UserManagement.vue
FOUND: resources/js/pages/settings/Profile.vue
FOUND commit: 8be7080
FOUND commit: fb2847b
```

## Self-Check: PASSED

## Orchestrator follow-ups (2026-10-01, after a real-browser pass)

Driven in headless Chrome against a seeded SQLite copy (migration applied to that copy only).

- `php artisan storage:link` was run on the working tree by the orchestrator, because uploads cannot be served without it. `public/storage` is gitignored.
- Fix commit: a chosen file can now be undone; the field resets when the saved avatar changes (the Profile page stays mounted across a save, so the file was re-uploaded on the next edit); the hidden file input is out of the tab order.
- Verified by eye: create with a picture, replace, remove, keep, remove again; a fake `.png` and an `.svg` are both rejected with a visible error and change nothing; the sidebar avatar updates after a self-edit; no overflow at 375px.
- Correction to the verification note above: the 3 skipped tests are the pre-existing two-factor tests in `tests/Feature/Settings/SecurityTest.php`, not xlsx tests.

Still for the user to run: `php artisan migrate` on the dev MySQL database.

Noted, not changed: Settings > Profile still has the starter kit's "Delete account" action, which hard-deletes the user. That contradicts the project rule that users are deactivated, never deleted.
