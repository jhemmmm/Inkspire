<?php

namespace Database\Factories;

use App\Models\DesignFile;
use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignFile>
 */
class DesignFileFactory extends Factory
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
            'file_path' => 'design-files/'.fake()->uuid().'.png',
        ];
    }

    /**
     * Indicate that this design file is locked (JOB-06).
     *
     * Uses afterCreating() rather than state() because locked_at is outside
     * DesignFile's #[Fillable] list and would be silently dropped by a
     * state()-merged create() call.
     */
    public function locked(): static
    {
        return $this->afterCreating(fn (DesignFile $designFile) => $designFile->forceFill([
            'locked_at' => now(),
        ])->save());
    }
}
