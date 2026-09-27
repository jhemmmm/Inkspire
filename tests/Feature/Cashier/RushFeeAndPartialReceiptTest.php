<?php

use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function rushFeeConfig(float $percentage = 10): void
{
    SystemConfiguration::create([
        'key' => 'rush_fee_percentage',
        'group' => 'business_rules',
        'value' => $percentage,
        'type' => 'decimal',
        'label' => 'Rush fee (%)',
    ]);
}

test('a rush fee is applied to a job order that was already priced but never paid', function () {
    rushFeeConfig();
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);

    // Priced, no transaction against it — the state a rejected On-Credit
    // request leaves behind. The pricing form is editable here, so what the
    // Cashier submits has to be what gets charged.
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'is_rush' => true,
        'total_amount' => 1000,
        'base_price_snapshot' => 1000,
        'rush_fee_amount' => 0,
    ]);

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => true,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 1100,
    ])->assertSessionHasNoErrors();

    $jobOrder->refresh();

    expect((float) $jobOrder->rush_fee_amount)->toBe(100.0)
        ->and((float) $jobOrder->total_amount)->toBe(1100.0)
        ->and($jobOrder->rush_fee_applied)->toBeTrue();
});

test('the receipt prints the rush fee that was charged', function () {
    rushFeeConfig();
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['is_rush' => true, 'total_amount' => null]);

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => true,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 1100,
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrder.is_rush', true)
        ->where('jobOrder.rush_fee_applied', true)
        ->where('jobOrder.rush_fee_amount', '100.00')
        ->where('jobOrder.total_amount', '1100.00')
        ->etc());
});

test('pricing can no longer be changed once a transaction exists', function () {
    rushFeeConfig();
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'base_price_snapshot' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 300]);

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 9999,
        'rush_fee_applied' => true,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 700,
    ])->assertSessionHasNoErrors();

    // The customer already paid against the original total; repricing now
    // would move the goalposts under money that has changed hands.
    expect((float) $jobOrder->fresh()->total_amount)->toBe(1000.0);
});

test('a down payment lands on the receipt so the customer gets proof of what they paid', function () {
    rushFeeConfig();
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => null]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'down_payment_amount' => 300,
        'amount_tendered' => 300,
    ]);

    $response->assertRedirect(route('cashier.job-orders.receipt.show', $jobOrder));
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);
});

test('the receipt for a partially paid job order shows what was paid and what is left', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 300]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('amountPaid', 300)
        ->where('balance', 700)
        ->etc());
});

test('the cashier list offers a receipt for a partially paid job order', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 300]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.id', $jobOrder->id)
        ->where('jobOrders.0.amount_paid', 300)
        ->etc());
});
