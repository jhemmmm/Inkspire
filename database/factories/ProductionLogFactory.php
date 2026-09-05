<?php

namespace Database\Factories;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLog>
 */
class ProductionLogFactory extends Factory
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
            'from_status' => null,
            'to_status' => JobOrderStatus::ForProduction->value,
            'reason' => null,
            'recorded_by' => null,
        ];
    }
}
