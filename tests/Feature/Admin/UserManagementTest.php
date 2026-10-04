<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Models\DesignFile;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view the user management list', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/UserManagement'));
});

test('admin can delete another user while preserving referenced expenses and audit history', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->create(['recorded_by' => $target->id, 'expense_date' => now()]);
    $originalEmail = $target->email;

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(User::find($target->id))->toBeNull();
    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();
    expect($expense->fresh()->recorded_by)->toBe($target->id);
    expect(DB::table('audit_trail')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $target->id)
        ->where('action', 'deleted')
        ->exists())->toBeTrue();

    $this->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->doesntContain('id', $target->id)));

    $accountingStaff = User::factory()->accountingStaff()->create();
    $this->actingAs($accountingStaff)
        ->get(route('accounting-staff.expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.recorded_by', $target->name));

    auth()->logout();
    $this->post(route('login.store'), [
        'email' => $originalEmail,
        'password' => 'password',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    User::factory()->create(['email' => $originalEmail]);
});

test('deleting an artist returns unfinished work to the shared pool and preserves historical assignments', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $artist = User::factory()->artist()->create();
    $queueEntry = QueueEntry::factory()->serving()->create();
    $assigned = JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create();
    $pendingReview = JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create([
        'status' => JobOrderStatus::PendingReview,
        'consultation_notes' => 'Keep the approved layout.',
    ]);
    $designFile = DesignFile::factory()->for($pendingReview)->create();
    $revisionLog = RevisionLog::factory()->for($pendingReview)->create();
    $finished = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::DesignApproved]);
    $cancelled = JobOrder::factory()->assignedTo($artist)->create([
        'status' => JobOrderStatus::InDesign,
        'cancelled_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $artist))
        ->assertRedirect();

    expect($assigned->fresh()->status)->toBe(JobOrderStatus::Intake);
    expect($pendingReview->fresh()->status)->toBe(JobOrderStatus::PendingReview);

    foreach ([$assigned, $pendingReview] as $jobOrder) {
        expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
        expect($jobOrder->fresh()->accepted_at)->toBeNull();
    }

    expect(ClaimJobOrderForArtist::pool()->whereKey([$assigned->id, $pendingReview->id])->count())->toBe(2);
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Waiting);
    expect($pendingReview->fresh()->consultation_notes)->toBe('Keep the approved layout.');
    expect($designFile->fresh())->not->toBeNull();
    expect($finished->fresh()->assigned_artist_id)->toBe($artist->id);
    expect($cancelled->fresh()->assigned_artist_id)->toBe($artist->id);

    $showReviewUrl = URL::temporarySignedRoute('public.design-review.show', now()->addDays(7), ['revisionLog' => $revisionLog->id]);
    $this->get($showReviewUrl)
        ->assertInertia(fn (Assert $page) => $page->where('state', 'active'));

    $requestChangesUrl = URL::temporarySignedRoute('public.design-review.request-changes', now()->addDays(7), ['revisionLog' => $revisionLog->id]);
    $this->post($requestChangesUrl, ['message' => 'Please enlarge the heading.'])->assertOk();

    expect($pendingReview->fresh()->status)->toBe(JobOrderStatus::InDesign);
    expect(ClaimJobOrderForArtist::pool()->whereKey($pendingReview->id)->exists())->toBeTrue();

    $newArtist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);
    $this->actingAs($newArtist)->patch(route('artist.job-orders.accept', $pendingReview))->assertRedirect();

    expect($pendingReview->fresh()->assigned_artist_id)->toBe($newArtist->id);
    expect($pendingReview->fresh()->status)->toBe(JobOrderStatus::InDesign);
});

test('the soft-delete migration refuses to restore deleted accounts on rollback', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $target))->assertRedirect();

    $migration = require database_path('migrations/2026_10_04_172616_add_deleted_at_to_users_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();
});

test('admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertForbidden();

    expect($admin->fresh())->not->toBeNull();
});

test('staff cannot delete another user', function () {
    $staff = User::factory()->cashier()->create();
    $target = User::factory()->create();

    $this->actingAs($staff)
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull();
});

test('admin deactivating a user flips is_active to false and writes an audited update row', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();

    $row = DB::table('audit_trail')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $target->id)
        ->where('action', 'updated')
        ->first();

    expect($row->new_values)->toContain('"is_active":false');
});

test('admin cannot deactivate their own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.deactivate', $admin));

    $response->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('an admin can deactivate another admin', function () {
    // Blocked while Admin existed. With one administrative role left, an
    // Admin nobody can deactivate would be permanent.
    $admin = User::factory()->admin()->create();
    $anotherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $anotherAdmin));

    expect($anotherAdmin->fresh()->is_active)->toBeFalse();
});

test('admin can deactivate a staff role user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('admin can deactivate an admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('admin can reactivate a previously deactivated user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->deactivated()->create();

    $this->actingAs($admin)->patch(route('admin.users.reactivate', $target));

    expect($target->fresh()->is_active)->toBeTrue();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'updated')
            ->where('new_values', 'like', '%"is_active":true%')
            ->exists()
    )->toBeTrue();
});

test('the user list exposes artist_status and exceeded_break_time only for artist-role users, null for everyone else', function () {
    $admin = User::factory()->admin()->create();
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/UserManagement')
        ->where('users', function ($users) use ($admin, $artist) {
            $adminRow = collect($users)->firstWhere('id', $admin->id);
            $artistRow = collect($users)->firstWhere('id', $artist->id);

            expect($adminRow['artist_status'])->toBeNull();
            expect($adminRow['exceeded_break_time'])->toBeFalse();
            expect($artistRow['artist_status'])->toBe(ArtistStatus::Available->value);

            return true;
        })
    );
});

test('exceeded_break_time is true once break_started_at exceeds max_artist_break_minutes and false otherwise', function () {
    $admin = User::factory()->admin()->create();
    $exceeded = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(30),
    ]);
    $withinLimit = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users', function ($users) use ($exceeded, $withinLimit) {
            $exceededRow = collect($users)->firstWhere('id', $exceeded->id);
            $withinLimitRow = collect($users)->firstWhere('id', $withinLimit->id);

            expect($exceededRow['exceeded_break_time'])->toBeTrue();
            expect($withinLimitRow['exceeded_break_time'])->toBeFalse();

            return true;
        })
    );
});

test('an account serving a lockout is flagged in the list', function () {
    // The Admin dashboard counts these and links here, so this page has to
    // show which account the count is about.
    $admin = User::factory()->admin()->create();
    $lockedOut = User::factory()->cashier()->create(['locked_until' => now()->addMinutes(15)]);
    $expired = User::factory()->cashier()->create(['locked_until' => now()->subMinutes(15)]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($lockedOut, $expired) {
            $users = collect($page->toArray()['props']['users']);

            expect($users->firstWhere('id', $lockedOut->id)['is_locked_out'])->toBeTrue();
            expect($users->firstWhere('id', $expired->id)['is_locked_out'])->toBeFalse();
        });
});
