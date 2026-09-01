<?php

namespace Database\Factories;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOrder>
 */
class JobOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'queue_entry_id' => QueueEntry::factory(),
            'description' => fake()->randomElement([
                'Tarpaulin, 3x5ft',
                'Business Cards, 100pcs',
                'Sticker, A4',
            ]),
            'type' => JobOrderType::TypeB->value,
            'status' => JobOrderStatus::Intake->value,
            'file_path' => null,
        ];
    }

    /**
     * Indicate that this job order is Type A (print-ready file).
     */
    public function typeA(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => JobOrderType::TypeA->value,
        ]);
    }
}
