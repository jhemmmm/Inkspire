<?php

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\AccountsReceivable;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard renders on an empty database', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('attention.creditRequests', 0)
            ->where('attention.writeOffRequests', 0)
            ->where('attention.lockedDesigns', 0)
            ->where('attention.lockedAccounts', 0)
            ->where('shop.outstandingAmount', 0)
            ->where('shop.unpaidJobOrders', 0)
            ->has('recentActivity'));
});

test('the credit request count matches the queue it links to', function () {
    $admin = User::factory()->admin()->create();
    AccountsReceivable::factory()->count(2)->create(['status' => AccountsReceivableStatus::PendingApproval->value]);
    AccountsReceivable::factory()->create(['status' => AccountsReceivableStatus::Active->value]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention.creditRequests', 2));
});

test('a write-off request settled since it was raised is not counted', function () {
    // The stored collection_status still reads Pending; only the derived
    // value knows it is paid. Counting on SQL alone would overstate this
    // tile against the page it opens.
    $admin = User::factory()->admin()->create();

    $settled = JobOrder::factory()->create(['total_amount' => 500]);
    Transaction::factory()->for($settled)->create([
        'amount' => 500,
        'status' => TransactionStatus::Completed->value,
    ]);
    AccountsReceivable::factory()->for($settled)->create([
        'write_off_requested_at' => now(),
        'collection_status' => AccountsReceivableCollectionStatus::Pending->value,
    ]);

    $stillOwed = JobOrder::factory()->create(['total_amount' => 500]);
    AccountsReceivable::factory()->for($stillOwed)->create([
        'write_off_requested_at' => now(),
        'collection_status' => AccountsReceivableCollectionStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention.writeOffRequests', 1));
});

test('locked designs and locked-out accounts are counted', function () {
    $admin = User::factory()->admin()->create();
    DesignFile::factory()->for(JobOrder::factory())->create(['locked_at' => now()]);
    DesignFile::factory()->for(JobOrder::factory())->create(['locked_at' => null]);
    User::factory()->cashier()->create(['locked_until' => now()->addMinutes(15)]);
    User::factory()->cashier()->create(['locked_until' => now()->subMinutes(15)]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention.lockedDesigns', 1)
            ->where('attention.lockedAccounts', 1));
});

test('outstanding money uses the same definition as outstandingBalance', function () {
    $admin = User::factory()->admin()->create();

    $partial = JobOrder::factory()->create(['total_amount' => 1000]);
    Transaction::factory()->for($partial)->create(['amount' => 400, 'status' => TransactionStatus::Completed->value]);
    // Pending money is not money the shop has.
    Transaction::factory()->for($partial)->create(['amount' => 200, 'status' => TransactionStatus::PendingConfirmation->value]);

    $unpriced = JobOrder::factory()->create(['total_amount' => null]);
    $cancelled = JobOrder::factory()->create(['total_amount' => 800, 'cancelled_at' => now()]);
    $settled = JobOrder::factory()->create(['total_amount' => 300]);
    Transaction::factory()->for($settled)->create(['amount' => 300, 'status' => TransactionStatus::Completed->value]);

    expect($partial->fresh()->outstandingBalance())->toBe(600.0);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shop.outstandingAmount', 600)
            ->where('shop.unpaidJobOrders', 1));

    expect($unpriced->id)->not->toBeNull()
        ->and($cancelled->id)->not->toBeNull();
});

test('outstanding money excludes written-off job orders (bug 5)', function () {
    $admin = User::factory()->admin()->create();

    JobOrder::factory()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::WrittenOff->value]);
    JobOrder::factory()->create(['total_amount' => 500]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shop.outstandingAmount', 500)
            ->where('shop.unpaidJobOrders', 1));
});

test('in-production counts only job orders actually on the press', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    JobOrder::factory()->create(['status' => JobOrderStatus::QualityCheck->value]);
    JobOrder::factory()->create(['status' => JobOrderStatus::InDesign->value]);
    JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value, 'cancelled_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('shop.inProduction', 2));
});

test('another portal cannot reach the admin dashboard', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
