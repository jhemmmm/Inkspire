<?php

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\Customer;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'queue_date' => now()->timezone('Asia/Manila')->toDateString(),
            'queue_number' => fake()->unique()->numberBetween(1, 999),
            'status' => QueueStatus::Waiting->value,
        ];
    }

    /**
     * Indicate that the visit is currently being served.
     */
    public function serving(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QueueStatus::Serving->value,
        ]);
    }

    /**
     * Indicate that the visit has been completed.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QueueStatus::Done->value,
        ]);
    }
}
