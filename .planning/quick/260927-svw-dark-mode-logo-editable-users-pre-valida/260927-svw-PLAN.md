---
quick_id: 260927-svw
type: quick
---

# Plan: dark-mode logo, editable users, pre-check Type A files

## Context

Three issues:

1. In dark mode the logo in the sidebar and header can't be seen. `/logo.png` has near-black "spire" lettering.
2. Admin User Management can create, deactivate and reactivate users, but can't edit them.
3. Frontline intake saves the Type A job order and stores its file _before_ running `ValidateJobOrderFile`. A bad file leaves a `validation_failed` job order in the database, and staff still get a success toast. The file should be checked first. If it's invalid, no job order is created and the error shows on the form.

Decisions from the user:

- The logo turns all-white in dark mode.
- An admin can edit a user's name, email, role, and optionally the password. An admin can't change their own role.
- Only hard-invalid files (the `Rejected` outcome) block creation. Low-DPI files (`NeedsArtist`) still create the job order and go to the artist pool.

Before editing, go through `/gsd-quick`. Read `.ai/rules/index.md` and the matching rules if they exist.

---

## #1 Dark-mode logo

- `resources/js/components/AppLogo.vue:12-16`: add `dark:brightness-0 dark:invert` to the `<img src="/logo.png">`. This is the same fix `resources/js/pages/Welcome.vue:173` already uses.
- That one change covers both `AppSidebar.vue` and `AppHeader.vue`. The auth layout puts the logo on a panel that is always `bg-white`, so it needs no change.
- No test: this is CSS only. Check it by eye in both themes.

## #2 Edit users (Admin → User Management)

**Backend**

- `routes/admin.php`: add `Route::patch('users/{user}', [UserManagementController::class, 'update'])->name('users.update');`
- `app/Policies/UserPolicy.php`: add `update(User $actor, User $target): bool`, returning true only when the actor is an Admin.
- New `app/Http/Requests/Admin/UpdateUserRequest.php`. Create it with `php artisan make:request`, modelled on `CreateUserRequest`.
    - `authorize()`: `can('update', $this->route('user'))`.
    - `rules()`: `profileRules($this->route('user')->id)`. That method already ignores the user's own email in the unique check.
    - `role`: `required` and `Rule::enum(UserRole::class)`. If the target is the acting user, the role must equal their current role (use `Rule::in` with the current value). This stops an admin from locking themselves out.
    - `password`: `nullable`, `string`, `Password::default()`, `confirmed`.
- `UserManagementController::update(UpdateUserRequest $request, User $user): RedirectResponse`:
    - `forceFill` name, email and role.
    - Keep `artist_label` in step with the role:
        - role changes _to_ artist: assign `User::nextArtistLabel()`
        - role changes _away_ from artist: set it to `null`
        - role unchanged: keep the existing label
    - Set the password only when one was entered.
    - Call `save()`. `AuditObserver` writes the audit row automatically.
    - Flash a success toast (":name's account has been updated.") and return `back()`, the same as `store`.

**Frontend** (`resources/js/pages/admin/UserManagement.vue`)

- Add an "Edit" button on each row (`data-test="edit-user-{id}-button"`). It opens a `Dialog` with a `<Form v-bind="UserManagementController.update.form(user.id)">`.
- The fields match the create dialog, pre-filled with `:default-value`:
    - name and email
    - role, using `Select` plus a hidden input, the same as create
    - password and confirmation, labelled "Leave blank to keep current password"
- Disable the role select when editing yourself. Compare against `page.props.auth.user.id`.
- Use one shared dialog driven by an `editingUser` ref, not one dialog per row. Close it on `onSuccess`.
- Run the UI check from CLAUDE.md: keyboard access, both themes, 375px width, and the path where an existing value is changed.

**Tests**: new `tests/Feature/Admin/UpdateUserTest.php`, following the `CreateUserTest` style.

- An admin updates name, email and role.
- The email must be unique but may be kept unchanged.
- A blank password keeps the old hash; a new password lets the user log in.
- Changing the role to or from artist assigns or clears `artist_label`.
- An admin can't change their own role.
- Other roles are forbidden.
- An audit row is written.

## #3 Check the Type A file before creating the job order

`ValidateJobOrderFile` is stateless and works on an `UploadedFile`, so it can run during request validation.

- Add an `after()` validation hook to `StoreQueueEntryRequest` (`job_orders.*`) and `AddJobOrderRequest` (single `file`):
    - For each Type A row with a file, call `app(ValidateJobOrderFile::class)($file, $printSize)`.
    - If the outcome is `Rejected`, add the returned `reason` to `job_orders.{i}.file` (or `file`).
    - Put the loop in one small method on the `JobOrderValidationRules` trait so both requests share it.
- The result: Laravel throws a validation error before the controller runs. No `QueueEntry`, no job order and no stored file are created. The transaction in `QueueEntryController::store()` never starts.
- `NewVisit.vue` already shows `intakeForm.errors['job_orders.${index}.file']` under the file input. `useForm` keeps the input and doesn't reset on error, so staff only need to pick a different file.
    - Also show an error toast when the submit fails, from the `onError` of `intakeForm.post`, so the problem is noticed on a long form.
    - Do the same for the add-job-order form.
- `QueueEntryController::applyIntakeOutcome()` stays as it is: it still routes `Passed` to production and `NeedsArtist` to the artist pool. `Rejected` can then only come from the replace-file path.
- `replaceFile` (`JobOrderController.php:140`) is left unchanged, so a rejected replacement is still recorded on the job order. (Say so in the summary, and offer to apply the same pre-check there.)
- Fix a small related mismatch in `NewVisit.vue:281` and `:1344`:
    - The file picker offers `.psd` and `.cdr`, which the backend always rejects, and leaves out `.eps`.
    - Pass the backend's `accepted_file_formats` (from `SystemConfiguration`) as a page prop and build `accept` and the hint text from it.

**Tests**

- Update `JobOrderProcessingTest`: the `.xyz` and oversized cases (lines 30 and 47) now expect `assertSessionHasErrors('file')` and no job order in the database.
- Add a `QueueEntryIntakeTest` case: a `store` with a rejected Type A file returns an error on `job_orders.0.file` and creates no `QueueEntry` or `JobOrder`.
- Keep the passing and low-DPI cases green, including `ScannerRoutesToArtistTest`.
    - `ScannerRoutesToArtistTest`'s `.psd`-rejected case will change to expect a validation error.

---

## Verification

- `php artisan test --compact tests/Feature/Admin tests/Feature/FrontlineStaff tests/Feature/JobOrder`
- `vendor/bin/pint --dirty --format agent`, `composer types:check`, `npm run types:check`
- In the browser (chrome-devtools MCP), in both themes and at 375px and desktop widths:
    - The sidebar logo is visible in dark mode.
    - Edit a user: change the role to artist and back, change the password, and try editing your own role.
    - Frontline new visit: upload a `.xyz` file and confirm the inline error and toast, with no queue entry created. Then fix the file and submit successfully.
- Then ask the user to run the full suite with `php artisan test --compact`.
