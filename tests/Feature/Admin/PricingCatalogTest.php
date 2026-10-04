<?php

use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;
use Database\Seeders\PricingDatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees the whole catalog by name, retired entries included', function () {
    PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 12, 'unit' => 'sq ft']);
    PricingEntry::factory()->inactive()->create(['name' => 'Mug Print']);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.products.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Products')
        ->has('pricingEntries', 2)
        ->where('pricingEntries.0.name', 'Mug Print')
        ->where('pricingEntries.0.is_active', false)
        ->where('pricingEntries.1.name', 'Tarpaulin')
        ->where('pricingEntries.1.base_price', '12.00')
        ->where('pricingEntries.1.unit', 'sq ft')
    );
});

test('a product the admin adds is offered at intake', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.products.store'), [
        'name' => 'Tarpaulin',
        'base_price' => '12.50',
        'unit' => 'sq ft',
    ]);

    $response->assertSessionHasNoErrors();

    $created = PricingEntry::where('name', 'Tarpaulin')->firstOrFail();

    expect($created->base_price)->toBe('12.50');
    expect($created->unit)->toBe('sq ft');
    expect($created->is_active)->toBeTrue();

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pricingEntries', 1)
            ->where('pricingEntries.0.name', 'Tarpaulin')
            ->where('pricingEntries.0.base_price', '12.50')
        );
});

test('a product name must be unique in the catalog', function () {
    PricingEntry::factory()->create(['name' => 'Tarpaulin']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.products.store'), [
        'name' => 'Tarpaulin',
        'base_price' => 20,
    ])->assertSessionHasErrors(['name' => 'That product or service is already in the catalog.']);

    expect(PricingEntry::where('name', 'Tarpaulin')->count())->toBe(1);
});

test('a product cannot be saved with an invalid price', function (mixed $price, string $message) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.products.store'), [
        'name' => 'Tarpaulin',
        'base_price' => $price,
    ])->assertSessionHasErrors(['base_price' => $message]);

    expect(PricingEntry::count())->toBe(0);
})->with([
    'negative' => [-1, 'The price cannot be negative.'],
    'not a number' => ['twelve', 'The base price field must be a number.'],
    'missing' => [null, 'The base price field is required.'],
]);

test('admin can rename and reprice a product without touching job orders already priced from it', function () {
    $entry = PricingEntry::factory()->create(['name' => 'Tarp', 'base_price' => 12, 'unit' => 'sq ft']);
    $jobOrder = JobOrder::factory()->create(['pricing_entry_id' => $entry->id, 'base_price_snapshot' => 240]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.products.update', $entry), [
        'name' => 'Tarpaulin',
        'base_price' => 15,
        'unit' => 'piece',
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();

    $entry->refresh();

    expect($entry->name)->toBe('Tarpaulin');
    expect($entry->base_price)->toBe('15.00');
    expect($entry->unit)->toBe('piece');
    expect($jobOrder->fresh()->base_price_snapshot)->toEqual(240);
});

test('a product can keep its own name when it is edited', function () {
    $entry = PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 12]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.products.update', $entry), [
        'name' => 'Tarpaulin',
        'base_price' => 14,
    ])->assertSessionHasNoErrors();

    expect($entry->fresh()->base_price)->toBe('14.00');
});

test('retiring a product removes it from intake without deleting it', function () {
    $offered = PricingEntry::factory()->create(['name' => 'Tarpaulin']);
    $retired = PricingEntry::factory()->create(['name' => 'Mug Print', 'base_price' => 100]);
    $jobOrder = JobOrder::factory()->create(['pricing_entry_id' => $retired->id]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.products.update', $retired), [
        'name' => $retired->name,
        'base_price' => $retired->base_price,
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($retired->fresh()->is_active)->toBeFalse();
    expect($jobOrder->fresh()->pricing_entry_id)->toBe($retired->id);

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pricingEntries', 1)
            ->where('pricingEntries.0.name', $offered->name)
        );
});

test('re-seeding the price list keeps the prices and retirements the admin set', function () {
    $this->seed(PricingDatabaseSeeder::class);

    $admin = User::factory()->admin()->create();
    $tarpaulin = PricingEntry::where('name', 'Tarpaulin')->firstOrFail();

    $this->actingAs($admin)->patch(route('admin.products.update', $tarpaulin), [
        'name' => 'Tarpaulin',
        'base_price' => 15,
        'unit' => 'sq ft',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    $this->seed(PricingDatabaseSeeder::class);

    $tarpaulin->refresh();

    expect($tarpaulin->base_price)->toBe('15.00');
    expect($tarpaulin->is_active)->toBeFalse();
    expect(PricingEntry::where('name', 'Tarpaulin')->count())->toBe(1);
});

test('a fresh seed prices Sticker on Sintraboard once, at the list price', function () {
    $this->seed(PricingDatabaseSeeder::class);

    $entry = PricingEntry::where('name', 'Sticker on Sintraboard')->sole();

    expect($entry->base_price)->toBe('200.00');
    expect($entry->unit)->toBe('sq ft (300 back-to-back)');
});

test('staff roles cannot reach the product catalog', function (string $factoryState) {
    $entry = PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 12]);

    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.products.index'))->assertForbidden();

    $this->actingAs($user)->post(route('admin.products.store'), [
        'name' => 'Smuggled In',
        'base_price' => 1,
    ])->assertForbidden();

    $this->actingAs($user)->patch(route('admin.products.update', $entry), [
        'name' => 'Tarpaulin',
        'base_price' => 1,
    ])->assertForbidden();

    expect(PricingEntry::where('name', 'Smuggled In')->exists())->toBeFalse();
    expect($entry->fresh()->base_price)->toBe('12.00');
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);

test('a guest is sent to sign in instead of the product catalog', function () {
    $this->get(route('admin.products.index'))->assertRedirect(route('login'));
});
