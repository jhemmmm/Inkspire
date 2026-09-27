<?php

namespace Database\Factories;

use App\Enums\SpecificationCategory;
use App\Models\SpecificationOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecificationOption>
 */
class SpecificationOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `label` is unique per category, so it is generated rather than picked
     * from a fixed list — a factory that collides with itself on the second
     * call is a factory nobody can use in a loop.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => SpecificationCategory::PrintSize,
            'label' => ucfirst(fake()->unique()->words(2, true)),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that this option belongs to the print size catalog.
     */
    public function printSize(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => SpecificationCategory::PrintSize,
        ]);
    }

    /**
     * Indicate that this option is retired and no longer offered at intake.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
