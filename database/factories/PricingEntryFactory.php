<?php

namespace Database\Factories;

use App\Models\PricingEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingEntry>
 */
class PricingEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Tarpaulin (per sq ft)',
                'Business Cards (100 pcs)',
                'Sticker (per piece)',
                'Flyers (per 100 pcs)',
            ]),
            'base_price' => fake()->randomFloat(2, 50, 5000),
            'unit' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that this catalog entry is inactive (soft-disabled).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
