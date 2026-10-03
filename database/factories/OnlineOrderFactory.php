<?php

namespace Database\Factories;

use App\Models\OnlineOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineOrder>
 */
class OnlineOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'email' => $email,
            'payload' => [
                'customer' => [
                    'name' => fake()->name(),
                    'organization' => null,
                    'contact_number' => fake()->unique()->numerify('09#########'),
                    'email' => $email,
                    'address' => fake()->address(),
                ],
                'job_orders' => [
                    ['description' => 'Poster, A2', 'type' => 'type_b'],
                ],
            ],
        ];
    }

    /**
     * Indicate that the customer already confirmed this order.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
        ]);
    }
}
