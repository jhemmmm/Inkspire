<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees every customer by name, with how many job orders each has placed', function () {
    $zara = Customer::factory()->create(['name' => 'Zara Cruz', 'organization' => 'Cruz Bakery']);
    Customer::factory()->create(['name' => 'Ana Reyes']);

    JobOrder::factory()->count(2)->for(QueueEntry::factory()->for($zara))->create();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.customers.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Customers')
        ->has('customers.data', 2)
        ->where('customers.data.0.name', 'Ana Reyes')
        ->where('customers.data.0.job_orders_count', 0)
        ->has('customers.data.0.job_orders', 0)
        ->where('customers.data.1.name', 'Zara Cruz')
        ->where('customers.data.1.organization', 'Cruz Bakery')
        ->where('customers.data.1.contact_number', $zara->contact_number)
        ->where('customers.data.1.email', $zara->email)
        ->where('customers.data.1.job_orders_count', 2)
        ->has('customers.data.1.job_orders', 2)
    );
});

test('each customer carries only their own five newest job orders, across all their visits', function () {
    $regular = Customer::factory()->create(['name' => 'Ana Reyes']);
    $other = Customer::factory()->create(['name' => 'Ben Santos']);

    // Seven job orders over two visits, so the cap and the visit join both matter.
    $older = JobOrder::factory()->count(3)->for(QueueEntry::factory()->for($regular))->create();
    $newer = JobOrder::factory()->count(4)->for(QueueEntry::factory()->for($regular))->create();
    $theirs = JobOrder::factory()->for(QueueEntry::factory()->for($other))->create(['total_amount' => 750])->fresh();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.customers.index'));

    $expected = $older->concat($newer)->sortByDesc('id')->take(5)->pluck('id')->values()->all();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('customers.data.0.job_orders_count', 7)
        ->where('customers.data.0.job_orders', fn ($jobOrders) => collect($jobOrders)->pluck('id')->all() === $expected)
        ->where('customers.data.1.job_orders_count', 1)
        ->has('customers.data.1.job_orders', 1)
        ->where('customers.data.1.job_orders.0.id', $theirs->id)
        ->where('customers.data.1.job_orders.0.number', $theirs->number)
        ->where('customers.data.1.job_orders.0.display_status', $theirs->status->value)
        ->where('customers.data.1.job_orders.0.payment_status', $theirs->payment_status->value)
        ->where('customers.data.1.job_orders.0.display_total', 750)
    );
});

test('the customer search matches name, organization, contact number or email', function (string $term) {
    Customer::factory()->create([
        'name' => 'Maria Santos',
        'organization' => 'Bayanihan Cooperative',
        'contact_number' => '09171234567',
        'email' => 'maria@bayanihan.test',
    ]);
    Customer::factory()->create([
        'name' => 'Pedro Cruz',
        'organization' => null,
        'contact_number' => '09998887766',
        'email' => 'pedro@example.test',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.customers.index', ['q' => $term]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('customers.data', 1)
        ->where('customers.data.0.name', 'Maria Santos')
        ->where('filters.q', $term)
    );
})->with([
    'name' => 'maria',
    'organization' => 'Bayanihan',
    'contact number' => '0917123',
    'email' => 'maria@bayanihan',
]);

test('a percent sign in the customer search is a literal, not a wildcard', function () {
    Customer::factory()->create(['name' => 'Maria Santos']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.customers.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
});

test('admin corrects a customer and the change is written to the audit trail', function () {
    $customer = Customer::factory()->create(['name' => 'Maria Santoz', 'contact_number' => '09171234567']);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
        'name' => 'Maria Santos',
        'organization' => 'Bayanihan Cooperative',
        // Unchanged: a customer keeps their own number without tripping the unique rule.
        'contact_number' => '09171234567',
        'email' => 'maria@bayanihan.test',
        'address' => '12 Rizal St, Quezon City',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

    expect($customer->fresh())
        ->name->toBe('Maria Santos')
        ->organization->toBe('Bayanihan Cooperative')
        ->contact_number->toBe('09171234567')
        ->email->toBe('maria@bayanihan.test')
        ->address->toBe('12 Rizal St, Quezon City');

    expect(AuditLog::query()
        ->where('auditable_type', Customer::class)
        ->where('auditable_id', $customer->id)
        ->where('action', 'updated')
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});

test('a customer cannot be given another customer\'s contact number', function () {
    Customer::factory()->create(['contact_number' => '09170000001']);
    $customer = Customer::factory()->create(['contact_number' => '09170000002']);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
        'name' => $customer->name,
        'contact_number' => '09170000001',
        'email' => $customer->email,
        'address' => $customer->address,
    ]);

    $response->assertSessionHasErrors(['contact_number' => 'Another customer already has that contact number.']);
    expect($customer->fresh()->contact_number)->toBe('09170000002');
});

test('a customer update missing its required details is rejected', function (string $field) {
    $customer = Customer::factory()->create();
    $admin = User::factory()->admin()->create();

    $payload = [
        'name' => $customer->name,
        'contact_number' => $customer->contact_number,
        'email' => $customer->email,
        'address' => $customer->address,
    ];

    $this->actingAs($admin)
        ->patch(route('admin.customers.update', $customer), [...$payload, $field => ''])
        ->assertSessionHasErrors($field);

    expect($customer->fresh()->{$field})->toBe($customer->{$field});
})->with(['name', 'contact_number', 'email', 'address']);

test('only an admin can see or edit customers', function (string $role) {
    $customer = Customer::factory()->create(['name' => 'Maria Santos']);
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('admin.customers.index'))->assertForbidden();

    $this->actingAs($user)->patch(route('admin.customers.update', $customer), [
        'name' => 'Changed',
        'contact_number' => $customer->contact_number,
        'email' => $customer->email,
        'address' => $customer->address,
    ])->assertForbidden();

    expect($customer->fresh()->name)->toBe('Maria Santos');
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);

test('a guest is sent to log in instead of the customer list', function () {
    $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
});
