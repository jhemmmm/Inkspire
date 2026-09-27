<?php

use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can unlock a locked design file, clearing locked_at and writing an updated audit_trail row', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->create();
    $designFile = DesignFile::factory()->for($jobOrder)->locked()->create();

    $response = $this->actingAs($admin)->patch(route('admin.design-files.unlock', $designFile));

    $response->assertRedirect();
    expect($designFile->fresh()->locked_at)->toBeNull();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', DesignFile::class)
            ->where('auditable_id', $designFile->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();
});

test('an artist is forbidden from unlocking their own design file', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->create();
    $designFile = DesignFile::factory()->for($jobOrder)->locked()->create();

    $response = $this->actingAs($artist)->patch(route('admin.design-files.unlock', $designFile));

    $response->assertForbidden();
});

test('a non-admin role is forbidden from the design-overrides index route entirely', function () {
    $artist = User::factory()->artist()->create();

    $response = $this->actingAs($artist)->get(route('admin.design-overrides.index'));

    $response->assertForbidden();
});

test('the design overrides index lists only job orders whose design file is currently locked', function () {
    $admin = User::factory()->admin()->create();

    $lockedJobOrder = JobOrder::factory()->create();
    DesignFile::factory()->for($lockedJobOrder)->locked()->create();

    $unlockedJobOrder = JobOrder::factory()->create();
    DesignFile::factory()->for($unlockedJobOrder)->create();

    $response = $this->actingAs($admin)->get(route('admin.design-overrides.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/DesignOverrides')
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $lockedJobOrder->id)
    );
});
