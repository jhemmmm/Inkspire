<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees the job order list with totals', function () {
    $admin = User::factory()->admin()->create();
    $pricingEntry = PricingEntry::factory()->create(['name' => 'Tarpaulin']);
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'pricing_entry_id' => $pricingEntry->id,
        'quoted_amount' => 288,
        'total_amount' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/JobOrders')
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $jobOrder->id)
        ->where('jobOrders.data.0.display_total', 288)
        ->where('jobOrders.data.0.total_amount', null)
    );
});

test('the search filter narrows results by job order number or customer name', function () {
    $admin = User::factory()->admin()->create();

    $customer = Customer::factory()->create(['name' => 'Marites Dela Cruz']);
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $matching = JobOrder::factory()->for($queueEntry)->create(['number' => 'JO-2026-0001']);
    $other = JobOrder::factory()->create(['number' => 'JO-2026-9999']);

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['q' => 'Marites']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.id', $matching->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['q' => 'JO-2026-9999']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.id', $other->id)
        );
});

test('the status filter narrows results', function () {
    $admin = User::factory()->admin()->create();

    $intake = JobOrder::factory()->create(['status' => 'intake']);
    $readyForProduction = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index', ['status' => 'ready_for_production']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $readyForProduction->id)
    );

    expect($intake)->not->toBeNull();
});

test('pagination limits the list to 25 per page', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->count(30)->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 25)
        ->where('jobOrders.total', 30)
        ->where('jobOrders.last_page', 2)
    );
});

test('rush jobs lead the admin list before pagination', function () {
    $admin = User::factory()->admin()->create();
    $rush = JobOrder::factory()->rush()->create();
    JobOrder::factory()->count(26)->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 25)
        ->where('jobOrders.data.0.id', $rush->id)
        ->where('jobOrders.total', 27));
});

test('a cancelled job order is excluded from the list', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create(['cancelled_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 0)
    );
});

test('every other role gets 403 on the admin job orders index', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.job-orders.index'))->assertForbidden();
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);

test('the payment_status filter narrows results', function () {
    $admin = User::factory()->admin()->create();

    $unpaid = JobOrder::factory()->create(['payment_status' => PaymentStatus::Unpaid]);
    JobOrder::factory()->create(['payment_status' => PaymentStatus::Paid]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index', ['payment_status' => PaymentStatus::Unpaid->value]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $unpaid->id)
    );
});

test('the from and to filters narrow results by created_at', function () {
    $admin = User::factory()->admin()->create();

    $before = JobOrder::factory()->create();
    $before->forceFill(['created_at' => now()->subDays(10)])->save();

    $inRange = JobOrder::factory()->create();
    $inRange->forceFill(['created_at' => now()->subDays(5)])->save();

    $after = JobOrder::factory()->create();
    $after->forceFill(['created_at' => now()])->save();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index', [
        'from' => now()->subDays(6)->toDateString(),
        'to' => now()->subDays(4)->toDateString(),
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $inRange->id)
    );

    expect($before)->not->toBeNull();
    expect($after)->not->toBeNull();
});

test('the from and to filters use the shop\'s Asia/Manila day, not the UTC one', function () {
    $admin = User::factory()->admin()->create();

    // 23:30 UTC on Sep 9 is 07:30 on Sep 10 at the shop.
    $earlyMorning = JobOrder::factory()->create();
    $earlyMorning->forceFill(['created_at' => '2026-09-09 23:30:00'])->save();

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['from' => '2026-09-10', 'to' => '2026-09-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.id', $earlyMorning->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['from' => '2026-09-09', 'to' => '2026-09-09']))
        ->assertInertia(fn (Assert $page) => $page->has('jobOrders.data', 0));
});

test('the xlsx export honours the active filters', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();

    $matching = JobOrder::factory()->readyForProduction()->create();
    JobOrder::factory()->create(['status' => JobOrderStatus::Intake->value]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.export.xlsx', ['status' => JobOrderStatus::ReadyForProduction->value]));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows)->toHaveCount(3); // header + 1 matching + Total
    expect($rows[1][0])->toBe($matching->number);
});

test('exporting job orders to pdf returns a real PDF and audits exactly one row', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create();

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.export.pdf'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    // Landscape A4. Eleven columns do not fit a portrait page, and the
    // layout's own `@page` rule silently overrides dompdf's setPaper().
    expect($response->getContent())->toContain('/MediaBox [0.000 0.000 841.890 595.280]');

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(1);

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->new_values['report'])->toBe('job-orders');
    expect($audit->new_values['format'])->toBe('pdf');
});

test('exporting job orders to xlsx audits exactly one row, and viewing the index writes none', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create();

    $this->actingAs($admin)->get(route('admin.job-orders.index'));
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.export.xlsx'));

    $response->assertOk();
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(1);
});

test('every other role gets 403 on the job orders export routes and writes no audit row', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.job-orders.export.pdf'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.job-orders.export.xlsx'))->assertForbidden();

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);

test('the xlsx export is uncapped past the 25-row page size', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();
    JobOrder::factory()->count(30)->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.export.xlsx'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows)->toHaveCount(32); // header + 30 + Total
});

test('exported label cells are display-ready, not raw enum values or booleans', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();
    JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.export.xlsx'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[1][4])->toBe('For Production');
    expect($rows[1][6])->toBe('Rush');
});

test('a released job order is listed as released, not at the stage it last reached', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value, 'released_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('jobOrders.data.0.display_status', 'released'));
});

test('the status filter tells released orders apart from ones still waiting for pickup', function (string $status, string $expectedNumber) {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create(['number' => 'JO-2026-0001', 'status' => JobOrderStatus::ReadyForPickup->value]);
    JobOrder::factory()->create(['number' => 'JO-2026-0002', 'status' => JobOrderStatus::ReadyForPickup->value, 'released_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['status' => $status]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.number', $expectedNumber)
        );
})->with([
    'ready for pickup' => ['ready_for_pickup', 'JO-2026-0001'],
    'released' => ['released', 'JO-2026-0002'],
]);
