<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['Utilities', 'Supplies', 'Rent']),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'description' => fake()->sentence(),
            'recorded_by' => User::factory()->accountingStaff(),
        ];
    }

    /**
     * Indicate that this expense has been voided.
     *
     * Uses afterCreating() rather than state() because voided_at/void_reason
     * are outside Expense's #[Fillable] list and would be silently dropped
     * by a state()-merged create() call (see AccountsReceivableFactory::active()).
     */
    public function voided(): static
    {
        return $this->afterCreating(fn (Expense $expense) => $expense->forceFill([
            'voided_at' => now(),
            'void_reason' => 'Duplicate entry',
        ])->save());
    }
}
