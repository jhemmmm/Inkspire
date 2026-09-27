---
quick_id: 260927-svw
status: complete
completed: 2026-09-27
committed: false
---

# Quick 260927-svw: dark-mode logo, editable users, Type A file pre-check

The logo turns all-white in dark mode. Admins can now edit a user's name, email and role, and optionally set a new password; an Admin can't change their own role. A Type A file that the check rejects now stops intake at validation, so nothing is saved.

Nothing was committed, as instructed. All changes are left in the working tree alongside the user's own uncommitted work.

## Changes

### #1 Dark-mode logo

- `resources/js/components/AppLogo.vue`: added `dark:brightness-0 dark:invert` to the logo image. This covers both the sidebar and the header.

### #2 Edit users

- `routes/admin.php`: new route `PATCH admin/users/{user}`, named `admin.users.update`.
- `app/Policies/UserPolicy.php`: new `update()` method. Only an Admin can edit, and that includes editing themselves.
- `app/Http/Requests/Admin/UpdateUserRequest.php` (new):
    - Uses `profileRules($user->id)`.
    - `role` is required and must be a valid enum value. When an Admin edits themselves, `Rule::in([current role])` also applies, with the message "You cannot change your own role."
    - `password` is nullable and must meet `Password::default()` and be confirmed.
- `app/Http/Controllers/Admin/UserManagementController.php`, new `update()` method:
    - Saves name, email and role.
    - Keeps `artist_label` in step with the role: it is assigned when the user becomes an artist, cleared when they stop being one, and kept when they stay an artist.
    - Changes the password only when one is entered.
    - Shows the toast ":name's account has been updated." and returns `back()`.
    - `AuditObserver` writes the audit row.
- `resources/js/pages/admin/UserManagement.vue`:
    - Each row has an Edit button (`edit-user-{id}-button`).
    - One shared `Dialog`, controlled by the `editingUser` ref, holds a `<Form>` bound to `update.form(id)`. The form is keyed per user so its default values reset between users.
    - The fields are name, email, role (`Select` plus a hidden input), and new password with confirmation, labelled "Leave blank to keep current password".
    - The role select is disabled when you edit yourself, with a note explaining why.
    - When the role moves away from artist, a note says which artist label will be freed.
    - The dialog closes on `@success`.
    - The row's action cell is now a flex wrapper.

### #3 Type A file pre-check

- `app/Concerns/JobOrderValidationRules.php`: new `rejectUnusableTypeAFiles(Validator, list<string> $prefixes)`.
    - For each Type A row that has a file and no existing file error, it runs `ValidateJobOrderFile`.
    - Only a `Rejected` outcome adds an error, and the error text is the check's `reason`.
- `app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php` has an `after()` hook that checks every `job_orders.{i}.` row.
- `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php` has an `after()` hook that checks the single row.
- `app/Actions/JobOrder/ValidateJobOrderFile.php`: new static `acceptedFormats()`. It is the single source for the `accepted_file_formats` setting and its default, and `__invoke` now uses it.
- `app/Http/Controllers/FrontlineStaff/CustomerController.php`: the New Visit page now receives an `acceptedFileFormats` prop.
- `resources/js/pages/frontline-staff/NewVisit.vue`:
    - The file picker's `accept` list and its "Accepted: ..." hint are built from that prop. It no longer offers `.psd`, `.cdr` or `.jpeg`, and it now offers `.eps`.
    - When `intakeForm.post` fails, an error toast appears.
- `resources/js/pages/frontline-staff/QueueList.vue`: the Add Job Order `<Form>` also shows an error toast when submission fails.
- `QueueEntryController::applyIntakeOutcome()` is unchanged. `NeedsArtist` still creates the job order and sends it to the artist pool.

### Tests

- New `tests/Feature/Admin/UpdateUserTest.php` with 13 tests:
    - updating name, email and role
    - keeping your own email
    - a duplicate email is rejected
    - a blank password keeps the old hash
    - a new password works for logging in
    - a weak password is rejected
    - the artist label is assigned, cleared, or kept
    - an Admin can't change their own role
    - an Admin can still edit their own name
    - a staff role gets 403
    - an audit row is written
- `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php`: the `.xyz` and oversized-file cases now expect a `file` error, no job order, and no stored file.
- `tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php`, two new tests:
    - A rejected file in row 1 returns an error on `job_orders.1.file`, with none on row 0, and creates no `QueueEntry`, no `JobOrder` and no stored file.
    - The intake page's `acceptedFileFormats` prop follows `SystemConfiguration`.

## Deviations from Plan

1. **`ScannerRoutesToArtistTest` is unchanged.** Its `.psd` case calls `ValidateJobOrderFile` directly, not over HTTP, so it still correctly expects `Rejected`.
2. **Two more tests had to change.** They posted a `.xyz` over HTTP and expected a `ValidationFailed` job order, which the new rule makes impossible:
    - `tests/Feature/FrontlineStaff/IntakeCatalogAndRoutingTest.php`, "wrong format is rejected to the counter". It now expects the `job_orders.0.file` error and zero job orders.
    - `tests/Feature/JobOrder/EnterProductionTest.php`, "fails validation stays at ValidationFailed". It is renamed and now expects the `file` error, no job order and zero production logs.
3. **Added `ValidateJobOrderFile::acceptedFormats()`.** Without it, the controller that builds the page prop would have to copy the default format list.
4. **The error toast went into `QueueList.vue`.** The "add-job-order form" is the uncontrolled `<Form>` there, so `@error` was used instead of `onError`.

## Not changed (as planned)

- **`JobOrderController::replaceFile`.** Replacing a file with one that fails the check still records the rejection on the existing job order. **Offer:** the same pre-check can be added to the replace-file request if you want a bad replacement blocked instead.
- **The Add Job Order file input (`QueueList.vue`) still has no `accept` filter.** The server now rejects bad files inline anyway.

## Verification

- `php artisan test --compact tests/Feature/Admin tests/Feature/FrontlineStaff tests/Feature/JobOrder`: **275 passed**, 1240 assertions.
- Full suite (`php artisan test --compact`): 690 tests, 686 passed, 3 skipped, **1 failed**. The failure is `Tests\Feature\Reports\ReportExportTest` "xlsx export ... not capped at 100 rows": the cashier's `reports.export.xlsx` returns 302 instead of 200. It also fails when run on its own, and it goes through the reports export path, which this task didn't touch. `app/Http/Controllers/Reports/ReportController.php` already had uncommitted user changes. This task did not cause it.
- `vendor/bin/pint --dirty --format agent`: passed.
- `vendor/bin/phpstan analyse`:
    - The whole project reports **26 errors**, all in files this task did not touch: Admin and FrontlineStaff `DashboardController`, `JobOrderController`, `QueueEntryController:145`, the Reports controllers, `UpdateSystemConfigurationRequest`, the Cashier requests, `ReportBuilder`, `SpecificationOptionFactory`, and the seeders.
    - PHPStan run on just the 8 PHP files this task touched: **0 errors**.
- `npm run types:check` (vue-tsc): passed. `vp lint` on the 4 changed Vue files: clean.
- Wayfinder was regenerated with `php artisan wayfinder:generate --with-form`. The output is gitignored.

## Not verified

- **Browser check.** Chrome-devtools MCP tools weren't available to this agent, so none of these were checked in a browser: the dark logo, the edit dialog (including keyboard, 375px, and changing an existing role and back), the inline error and toast for a rejected file, and the updated `accept` filter.

## Known Stubs

None.

## Self-Check: PASSED
