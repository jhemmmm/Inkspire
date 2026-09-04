<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_order_id' => JobOrder::factory(),
            'type' => TransactionType::FullPayment->value,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'status' => TransactionStatus::Completed->value,
            'recorded_by' => User::factory()->cashier(),
            'confirmed_at' => now(),
        ];
    }

    /**
     * Indicate that this transaction is a down payment rather than a full
     * payment.
     */
    public function downPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::DownPayment->value,
        ]);
    }

    /**
     * Indicate that this transaction is a GCash/Maya payment still awaiting
     * webhook/reconciliation confirmation.
     */
    public function pendingConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::PendingConfirmation->value,
            'confirmed_at' => null,
            'payment_method' => PaymentMethod::Gcash->value,
        ]);
    }

    /**
     * Indicate that this transaction failed or expired.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Failed->value,
        ]);
    }

    /**
     * Indicate that this transaction was paid via GCash.
     */
    public function gcash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Gcash->value,
        ]);
    }

    /**
     * Indicate that this transaction was paid via Maya.
     */
    public function maya(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Maya->value,
        ]);
    }
}
