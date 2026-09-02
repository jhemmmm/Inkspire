<?php

namespace Database\Factories;

use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevisionLog>
 */
class RevisionLogFactory extends Factory
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
            'submitted_at' => now(),
        ];
    }

    /**
     * Indicate that this revision was approved.
     *
     * Uses afterCreating() rather than state() because outcome/reviewed_at
     * are outside RevisionLog's #[Fillable] list and would be silently
     * dropped by a state()-merged create() call.
     */
    public function approved(): static
    {
        return $this->afterCreating(fn (RevisionLog $revisionLog) => $revisionLog->forceFill([
            'outcome' => 'approved',
            'reviewed_at' => now(),
        ])->save());
    }

    /**
     * Indicate that changes were requested on this revision.
     *
     * Uses afterCreating() for the same #[Fillable] reason as approved().
     */
    public function changesRequested(): static
    {
        return $this->afterCreating(fn (RevisionLog $revisionLog) => $revisionLog->forceFill([
            'outcome' => 'changes_requested',
            'reviewed_at' => now(),
        ])->save());
    }
}
