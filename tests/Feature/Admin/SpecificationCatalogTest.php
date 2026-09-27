<?php

use App\Enums\SpecificationCategory;
use App\Models\JobOrder;
use App\Models\SpecificationOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees the catalog grouped by specification category', function () {
    SpecificationOption::factory()->printSize()->create(['label' => 'Tarpaulin 3x5ft']);
    SpecificationOption::factory()->printSize()->create(['label' => 'A4 (8.3x11.7in)']);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.specifications.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Specifications')
        ->has('categories', 1)
        ->has('options.print_size', 2)
        ->where('options.print_size.0.label', 'A4 (8.3x11.7in)')
    );
});

test('admin can add an option and it lands at the end of its own category', function () {
    SpecificationOption::factory()->printSize()->create(['sort_order' => 7]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.specifications.store'), [
        'category' => SpecificationCategory::PrintSize->value,
        'label' => 'Tarpaulin 4x8ft',
    ]);

    $response->assertSessionHasNoErrors();

    $created = SpecificationOption::where('label', 'Tarpaulin 4x8ft')->firstOrFail();

    expect($created->category)->toBe(SpecificationCategory::PrintSize);
    expect($created->is_active)->toBeTrue();
    expect($created->sort_order)->toBe(8);
});

test('an option label must be unique within its category', function () {
    SpecificationOption::factory()->printSize()->create(['label' => 'A4']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.specifications.store'), [
        'category' => SpecificationCategory::PrintSize->value,
        'label' => 'A4',
    ])->assertSessionHasErrors('label');

    expect(SpecificationOption::where('label', 'A4')->count())->toBe(1);
});

test('admin can rename an option', function () {
    $option = SpecificationOption::factory()->printSize()->create(['label' => 'Tarp 3x5']);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(
        route('admin.specifications.update', $option),
        ['label' => 'Tarpaulin 3x5ft', 'is_active' => true],
    );

    $response->assertSessionHasNoErrors();

    expect($option->fresh()->label)->toBe('Tarpaulin 3x5ft');
});

test('retiring an option removes it from intake without deleting it', function () {
    $offered = SpecificationOption::factory()->printSize()->create(['label' => 'Tarpaulin 3x5ft']);
    $retired = SpecificationOption::factory()->printSize()->create(['label' => 'Tarpaulin 10x20ft']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(
        route('admin.specifications.update', $retired),
        ['label' => $retired->label, 'is_active' => false],
    )->assertSessionHasNoErrors();

    expect($retired->fresh()->exists)->toBeTrue();

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('specificationOptions.print_size', [$offered->label])
        );
});

test('admin can delete an option and job orders keep the label they were created with', function () {
    $option = SpecificationOption::factory()->printSize()->create(['label' => 'Tarpaulin 3x5ft']);

    $jobOrder = JobOrder::factory()->create(['print_size' => 'Tarpaulin 3x5ft']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.specifications.destroy', $option))
        ->assertSessionHasNoErrors();

    expect(SpecificationOption::find($option->id))->toBeNull();
    expect($jobOrder->fresh()->print_size)->toBe('Tarpaulin 3x5ft');
});

test('admin may maintain the catalog', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.specifications.store'), [
            'category' => SpecificationCategory::PrintSize->value,
            'label' => 'Canvas',
        ])
        ->assertSessionHasNoErrors();

    expect(SpecificationOption::where('label', 'Canvas')->exists())->toBeTrue();
});

test('staff roles cannot reach the catalog', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.specifications.index'))->assertForbidden();

    $this->actingAs($user)->post(route('admin.specifications.store'), [
        'category' => SpecificationCategory::PrintSize->value,
        'label' => 'Smuggled In',
    ])->assertForbidden();

    expect(SpecificationOption::where('label', 'Smuggled In')->exists())->toBeFalse();
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);
