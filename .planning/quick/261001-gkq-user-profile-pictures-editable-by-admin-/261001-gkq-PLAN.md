---
quick_id: 261001-gkq
mode: quick
status: approved
---

# Quick Task 261001-gkq: Profile pictures, editable by Admin and by each user

Source: `let-s-create-a-plan-twinkly-breeze.md`, section "3. Profile pictures" ONLY. Sections 1-2
are done (`261001-er9`, `261001-few`). Sections 4-5 (search/filters, responsive layout) are
separate later tasks — out of scope here.

Goal: every user can have a profile picture. The Admin sets, replaces and removes it in User
Management's create and edit dialogs; each user does the same for their own under
Settings > Profile. The picture shows in the User Management table and wherever the app already
renders the signed-in user (`UserInfo.vue` — sidebar and user menu).

Locked decisions are numbered 1-14 in the task brief; cited inline below as (D1)...(D14) for
traceability. Do not revisit a locked decision. Before editing, go through `/gsd-quick`; no
`.ai/rules` directory exists in this repo, so there is nothing to read there. Load the
`laravel-best-practices`, `testing-best-practices`, `inertia-vue-development`,
`wayfinder-development` and `tailwindcss-development` skills as you touch matching files.

---

## Task 1: Backend — schema, model, validation, controllers, tests

**Files:** `database/migrations/{timestamp}_add_avatar_path_to_users_table.php` (new),
`app/Models/User.php`, `app/Concerns/ProfileValidationRules.php`,
`app/Http/Requests/Admin/CreateUserRequest.php`, `app/Http/Requests/Admin/UpdateUserRequest.php`,
`app/Http/Requests/Settings/ProfileUpdateRequest.php`,
`app/Http/Controllers/Admin/UserManagementController.php`,
`app/Http/Controllers/Settings/ProfileController.php`, `tests/Feature/Admin/CreateUserTest.php`,
`tests/Feature/Admin/UpdateUserTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`

1. **Migration (D2).** Create with
   `php artisan make:migration add_avatar_path_to_users_table --no-interaction`, then edit: in
   `up()`, `$table->string('avatar_path')->nullable()->after('artist_label');` (mirrors the
   nullable-and-unconstrained style of `2026_09_10_100933_add_artist_label_to_users_table.php`);
   `down()` drops the column.

2. **`User` model (D1, D3, D4, D8, D9).** Add `@property string|null $avatar_path` and
   `@property string|null $avatar` to the class docblock, right after the existing
   `$artist_label` line. Import `Illuminate\Database\Eloquent\Attributes\Appends`,
   `Illuminate\Database\Eloquent\Casts\Attribute`, `Illuminate\Http\UploadedFile`,
   `Illuminate\Support\Facades\Storage`. Add `#[Appends(['avatar'])]` alongside the existing
   `#[Fillable]`/`#[Hidden]`/`#[ObservedBy]` class attributes — do **not** add `avatar_path` to
   `#[Fillable]` (D3; both controllers here already force-fill, and `replaceAvatar()` below
   assigns the property directly, bypassing mass assignment entirely).

   New accessor:
   ```php
   protected function avatar(): Attribute
   {
       return Attribute::make(
           get: fn (): ?string => $this->avatar_path !== null
               ? Storage::disk('public')->url($this->avatar_path)
               : null,
       );
   }
   ```

   New method — the one place that writes the file (D4):
   ```php
   public function replaceAvatar(?UploadedFile $file, bool $remove): void
   {
       if ($file === null && ! $remove) {
           return;
       }

       $previousPath = $this->avatar_path;

       // ponytail: no server-side resize or re-encode -- a 2MB original is
       // served as-is and displayed with object-cover. No image library is
       // installed. If page weight becomes a problem, resize on upload here.
       $this->avatar_path = $file !== null ? $file->store('avatars', 'public') : null;
       $this->save();

       if ($previousPath !== null) {
           Storage::disk('public')->delete($previousPath);
       }
   }
   ```
   Store-then-save-then-delete, in that order: the new file is written to disk and the new
   state persisted before the previous file is ever touched (D4). The no-op branch (no file,
   no removal) returns before calling `save()`, so an ordinary update that doesn't touch the
   picture never writes to storage or produces an extra audit row.

   `deactivate()`/`reactivate()` on `UserManagementController` need no change — neither touches
   `avatar_path`, so a deactivated user keeps their picture (D8).

3. **`ProfileValidationRules` (D5).** Add:
   ```php
   protected function avatarRules(): array
   {
       return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
   }
   ```
   Both rules together are what rejects SVG — Laravel's `image` rule alone already excludes svg
   unless `allow_svg` is set, and `mimes:jpg,jpeg,png,webp` excludes it a second way.

4. **`CreateUserRequest::rules()` (D5).** Add `'avatar' => $this->avatarRules(), 'remove_avatar'
   => ['nullable', 'boolean'],` to the returned array (alongside the existing `...$this->profileRules()`
   spread).

5. **`UpdateUserRequest::rules()` (D5).** Same two keys added to its returned array, alongside
   the existing `...$this->profileRules($target->id)` spread.

6. **`ProfileUpdateRequest::rules()` (D5).** Currently `return $this->profileRules($this->user()->id);`
   — change to:
   ```php
   return [
       ...$this->profileRules($this->user()->id),
       'avatar' => $this->avatarRules(),
       'remove_avatar' => ['nullable', 'boolean'],
   ];
   ```

7. **`UserManagementController::store()` (D1, D4).** After the existing
   `$user->forceFill([...])->save();`, add
   `$user->replaceAvatar($request->file('avatar'), $request->boolean('remove_avatar'));`.

8. **`UserManagementController::update()` (D4).** After the existing `$user->save();`, add the
   same `replaceAvatar()` call.

9. **`UserManagementController::index()` (D10).** Add `'avatar_path'` to the existing
   `->select([...])` column list — required, since the `avatar` accessor reads
   `$this->avatar_path`, which the query would otherwise strip from every row. Add
   `'avatar' => $user->avatar,` to the mapped row array.

10. **`ProfileController::update()` (D6).** Change `$request->user()->fill($request->validated());`
    to `$request->user()->fill($request->safe()->except(['avatar', 'remove_avatar']));` so the
    upload keys never reach `fill()`. After the existing `$request->user()->save();`, add
    `$request->user()->replaceAvatar($request->file('avatar'), $request->boolean('remove_avatar'));`.

Authorization is unchanged (D7) — `UserPolicy::update()`/`create()` already gate who may touch
another user's account, and `ProfileUpdateRequest` only ever touches `$request->user()`. No new
policy method.

### Tests

Every new test wraps `Storage::fake('public');` first and uses `UploadedFile::fake()->image(...)`
/`->create(...)`, following this codebase's existing convention
(`tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php`, `AddJobOrderToVisitTest.php`):
`Storage::disk('public')->assertExists($path)`, `->assertMissing($path)`,
`expect(Storage::disk('public')->allFiles())->toBeEmpty()`. No new factory state is added —
seed an existing avatar in a test by calling `$user->replaceAvatar($file, false);` directly
against the model before the HTTP request, which is simpler than inventing a `withAvatar()`
factory state for a single-field setup.

**`CreateUserTest.php`**

- `creating a user with a picture stores the file, sets avatar_path, and the avatar appears in
  the Inertia user list` — post `admin.users.store` with
  `'avatar' => UploadedFile::fake()->image('avatar.jpg')`; assert the created user's
  `avatar_path` is not null and `Storage::disk('public')->assertExists($created->avatar_path)`;
  then, still acting as the same admin, `get(route('admin.users.index'))` and assert the
  matching row's `avatar` equals `Storage::disk('public')->url($created->avatar_path)`. This one
  test covers both the "stores the file" bullet and the "avatar appears in the Inertia users
  prop" bullet from the task brief, without a fourth test file.
- `a non-image upload is rejected and stores nothing` —
  `UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf')`;
  `assertSessionHasErrors('avatar')`; user not created; `Storage::disk('public')->allFiles()`
  empty.
- `an oversized picture is rejected and stores nothing` —
  `UploadedFile::fake()->create('oversized.jpg', 3000)` (3000 KB > the 2048 KB cap); same
  assertions.
- `an svg upload is rejected and stores nothing` —
  `UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml')`; same assertions.

**`UpdateUserTest.php`**

- `updating with a new picture stores it and deletes the previous one` — create a target, call
  `$target->replaceAvatar(UploadedFile::fake()->image('old.jpg'), false);`, capture
  `$oldPath = $target->avatar_path`; patch `admin.users.update` with a new `avatar` file; assert
  `$target->refresh()->avatar_path !== $oldPath`,
  `Storage::disk('public')->assertExists($target->avatar_path)`,
  `Storage::disk('public')->assertMissing($oldPath)`.
- `remove_avatar deletes the file and nulls the column` — same setup with an existing avatar;
  patch with `'remove_avatar' => '1'` and no `avatar`; assert `avatar_path` is null and the old
  file is missing.
- `updating without touching the picture leaves it in place` — same setup; patch with only the
  usual name/email/role fields (no `avatar`, no `remove_avatar`); assert `avatar_path` is
  unchanged and the file still exists.
- `a non-admin cannot change another user's picture` — repeat the existing "a staff role is
  forbidden from the update user route" case with an `avatar` file attached to the request;
  assert still 403 and the target's `avatar_path` unchanged.

**`ProfileUpdateTest.php`**

- `a user can set their own picture` — patch `profile.update` with `name`, `email`, and
  `'avatar' => UploadedFile::fake()->image('me.jpg')`; assert `avatar_path` set and the file
  exists.
- `a user can remove their own picture` — on a user with an existing avatar (seeded via
  `replaceAvatar()` as above), patch with `'remove_avatar' => '1'`; assert it's nulled and the
  file is gone.
- `the avatar appears in auth.user` — set an avatar, then `get(route('profile.edit'))`,
  `assertInertia` that `auth.user.avatar` matches `Storage::disk('public')->url(...)`.
- Non-image/oversized/svg rejection is already covered once in `CreateUserTest.php` — not
  duplicated here, since `avatarRules()` is the same method behind all three requests.

**Verify:** `php artisan test --compact tests/Feature/Admin tests/Feature/Settings`,
`vendor/bin/pint --dirty --format agent`, `composer types:check` (zero new errors; zero errors
in any file this task touches — baseline has 21 pre-existing errors elsewhere).

---

## Task 2: Frontend — `AvatarField.vue`, wired into both portals

**Files:** `resources/js/components/AvatarField.vue` (new),
`resources/js/pages/admin/UserManagement.vue`, `resources/js/pages/settings/Profile.vue`

Depends on Task 1 — the `avatar` field must already be present on `auth.user` and in the
`UserManagement` `users` prop before this task's components have anything real to render.

1. **`AvatarField.vue` (D11).** Props: `id: string` (unique DOM id prefix per usage — this
   component is mounted up to three times, in the create dialog, the edit dialog, and
   `settings/Profile.vue`), `name: string` (for the initials fallback and the image's `alt`),
   `avatarUrl?: string | null` (the current picture from the server; `null`/absent for a
   brand-new user), `error?: string`.

   State: `previewUrl = ref<string | null>(null)` (an object URL for a newly chosen file),
   `pendingRemoval = ref(false)`, `fileInputRef = ref<HTMLInputElement | null>(null)`.

   `displayUrl = computed(() => previewUrl.value ?? (pendingRemoval.value ? null : (props.avatarUrl ?? null)))`.

   `onFileChange(event)`: read `event.target.files?.[0]`; revoke any existing `previewUrl` first
   (`URL.revokeObjectURL`); if a file was picked, `previewUrl.value = URL.createObjectURL(file)`
   and `pendingRemoval.value = false` (D11 — choosing a new file cancels a pending removal).

   `removePicture()`: `pendingRemoval.value = true`; clear `fileInputRef.value!.value = ''`;
   revoke and clear `previewUrl` if set.

   `keepPicture()`: `pendingRemoval.value = false` — the undo (D11).

   `onBeforeUnmount`: revoke `previewUrl` if still set (D11 — revoked on change and on unmount).

   Template (single root `<div class="grid gap-2">`): a visible
   `<Label :for="`${id}-input`">Profile Picture</Label>` (clicking it also opens the file dialog,
   since labels are natively associated with file inputs); an `Avatar` (`h-16 w-16`) containing
   `AvatarImage` (`v-if="displayUrl"`, `:src="displayUrl"`, `:alt="name"`, **`class="object-cover"`**
   — the shared `AvatarImage.vue` primitive has no `object-fit` of its own and must not be
   hand-edited, so add it per-usage here per D9) and `AvatarFallback` showing
   `getInitials(name)` from `useInitials`; the real
   `<input :id="`${id}-input`" ref="fileInputRef" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onFileChange">`;
   a `Button type="button" variant="outline" size="sm" @click="fileInputRef?.click()"` reading
   "Change picture" when `displayUrl` is set, "Add picture" otherwise; a
   `Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive" v-if="avatarUrl && !pendingRemoval" @click="removePicture"`
   reading "Remove"; a `Button type="button" variant="ghost" size="sm" v-if="pendingRemoval" @click="keepPicture"`
   reading "Keep picture"; a
   `<input type="hidden" name="remove_avatar" :value="pendingRemoval ? '1' : '0'">`; and
   `<InputError :message="error" />`. Tailwind utilities and semantic tokens only — reuse
   `Avatar`/`AvatarImage`/`AvatarFallback`/`Button`/`Label`/`InputError`, no new styling
   primitives (D11).

2. **`UserManagement.vue` (D10, D12).**
   - `ManagedUser` interface: add `avatar: string | null;`.
   - Create dialog: add `const newUserAvatarName = ref('');` updated via
     `@input="newUserAvatarName = ($event.target as HTMLInputElement).value"` on the existing
     Name `<Input>` — a side-channel ref purely to feed the live initials fallback, matching
     this file's existing `newUserRole` pattern (a plain ref alongside the Form, not a
     controlled input); the Name field's own native `name="name"` submission is untouched.
     Place `<AvatarField id="create-user-avatar" :name="newUserAvatarName" :avatar-url="null" :error="errors.avatar" />`
     inside the create `<Form>`.
   - Edit dialog: `<AvatarField id="edit-user-avatar" :name="editingUser.name" :avatar-url="editingUser.avatar" :error="errors.avatar" />`
     inside the edit `<Form>` — which already carries `:key="editingUser.id"`, so switching the
     user being edited remounts the field with fresh state; no manual reset needed.
   - Table (D12 — do not restructure this table, no search/filter here): in the Name `<td>`,
     wrap the existing `{{ user.name }}` with an avatar, same gradient/classes `UserInfo.vue`
     already uses so the look matches the sidebar:
     ```html
     <div class="flex items-center gap-3">
       <Avatar class="h-8 w-8 overflow-hidden rounded-full">
         <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" class="object-cover" />
         <AvatarFallback class="from-ink-cyan to-primary text-primary-foreground bg-linear-to-br text-xs font-semibold">
           {{ getInitials(user.name) }}
         </AvatarFallback>
       </Avatar>
       <span>{{ user.name }}</span>
     </div>
     ```
   - Imports: `Avatar, AvatarFallback, AvatarImage` from `@/components/ui/avatar`,
     `useInitials` from `@/composables/useInitials`, `AvatarField` from
     `@/components/AvatarField.vue`.

3. **`settings/Profile.vue` (D11).** Add
   `<AvatarField id="profile-avatar" :name="user.name" :avatar-url="user.avatar ?? null" :error="errors.avatar" />`
   as the first field inside the existing `<Form>`, before the Name field. Import
   `AvatarField` from `@/components/AvatarField.vue`.

**Confirmed before writing, not changed (D13):** `UserManagementController.update.form()` and
`ProfileController.update.form()` (the generated Wayfinder helpers) already produce
`{ method: 'post', ... _method: 'PATCH' ... }` — multipart POST with method spoofing, exactly
what a real file upload on a PATCH route needs, since PHP does not parse multipart bodies on a
true PATCH request. No route change and no Wayfinder regeneration needed for this task.

**Verify:**
`npx vp check --fix resources/js/components/AvatarField.vue resources/js/pages/admin/UserManagement.vue resources/js/pages/settings/Profile.vue`,
`npm run types:check`.

---

## Verification

- `php artisan test --compact tests/Feature/Admin tests/Feature/Settings`
- `vendor/bin/pint --dirty --format agent`
- `composer types:check` — baseline has 21 pre-existing Larastan errors in unrelated files; the
  bar is zero new errors and zero errors in any file this task creates or modifies.
- `npx vp check --fix resources/js/components/AvatarField.vue resources/js/pages/admin/UserManagement.vue resources/js/pages/settings/Profile.vue`
  (not the bare `npm run check:fix`, which reformats unrelated tracked files) and
  `npm run types:check`.

**Environment.** Run on the main tree — worktrees are disabled for this project (agent
worktrees symlink `vendor/` and lack `.env`; an in-worktree composer call corrupts the shared
autoloader). Do **not** run `php artisan migrate` or `migrate:fresh` against the default
connection — the dev `.env` points at a real-looking MySQL database; tests use their own
database via `RefreshDatabase`. Tell the user to run `php artisan migrate` and
`php artisan storage:link` themselves once this lands. The laravel-boost MCP server was down
this session, so `search-docs` was unavailable — every API used here (`#[Appends]`,
`Attribute::make()`, `$request->boolean()`, `$request->safe()->except()`, Laravel's `image`
rule's svg exclusion, the test client's automatic `extractFilesFromDataArray()` on
`post()`/`patch()`) was verified directly against `vendor/laravel/framework` source during
planning (D14 — no new dependency was needed or added to make any of this work).

Not required to close this task, but the orchestrator will do it afterward: in the browser, both
themes, 375px and desktop — User Management: create a user with a picture; edit and replace it;
remove it, then change your mind and re-add it before saving; confirm the sidebar avatar
updates. Repeat from Settings > Profile as a non-admin.
