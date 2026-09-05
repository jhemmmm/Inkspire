<?php

namespace Database\Factories;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
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
            'number' => 'JO-'.now()->year.'-'.fake()->unique()->numerify('####'),
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

    /**
     * Indicate that this job order's file failed validation.
     *
     * Uses afterCreating() rather than state() because
     * validation_failure_reason is outside JobOrder's #[Fillable] list and
     * would be silently dropped by a state()-merged create() call.
     */
    public function validationFailed(): static
    {
        return $this->afterCreating(fn (JobOrder $jobOrder) => $jobOrder->forceFill([
            'status' => JobOrderStatus::ValidationFailed->value,
            'validation_failure_reason' => 'Image resolution is 150 DPI. Minimum required is 300 DPI. Replace the file with a higher-resolution version.',
        ])->save());
    }

    /**
     * Indicate that this job order's file passed validation.
     *
     * Uses afterCreating() for consistency with the other post-processing
     * states here, even though status alone is already fillable.
     */
    public function readyForProduction(): static
    {
        return $this->afterCreating(fn (JobOrder $jobOrder) => $jobOrder->forceFill([
            'status' => JobOrderStatus::ReadyForProduction->value,
        ])->save());
    }

    /**
     * Indicate that this job order has been auto-assigned to an artist.
     *
     * Uses afterCreating() rather than state() because assigned_artist_id is
     * outside JobOrder's #[Fillable] list and would be silently dropped by a
     * state()-merged create() call. The artist is created here, not inline
     * inside forceFill(), since factory relation expansion only happens
     * inside definition()/state(), not inside a raw forceFill() call.
     */
    public function assigned(): static
    {
        return $this->afterCreating(function (JobOrder $jobOrder) {
            $artist = User::factory()->artist()->create();

            $jobOrder->forceFill([
                'status' => JobOrderStatus::Assigned->value,
                'assigned_artist_id' => $artist->id,
            ])->save();
        });
    }

    /**
     * Indicate that this job order has been auto-assigned to a specific,
     * already-known artist.
     *
     * Distinct from assigned() (which creates its own random artist) —
     * this state exists for tests that need to act as a specific artist.
     * Uses afterCreating() rather than state() because assigned_artist_id is
     * outside JobOrder's #[Fillable] list and would be silently dropped by a
     * state()-merged create() call.
     *
     * Defaults status to Assigned only when the caller didn't already
     * override it via a create(['status' => ...]) array — that array is
     * applied to definition() before this afterCreating() hook runs, so a
     * caller-supplied status (e.g. 'in_consultation') is respected instead
     * of being clobbered.
     */
    public function assignedTo(User $artist): static
    {
        return $this->afterCreating(function (JobOrder $jobOrder) use ($artist) {
            $jobOrder->forceFill([
                'status' => $jobOrder->status === JobOrderStatus::Intake
                    ? JobOrderStatus::Assigned->value
                    : $jobOrder->status,
                'assigned_artist_id' => $artist->id,
            ])->save();
        });
    }
}
