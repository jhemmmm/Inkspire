<?php

namespace Database\Factories;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountsReceivable>
 */
class AccountsReceivableFactory extends Factory
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
            'balance' => fake()->randomFloat(2, 100, 5000),
            'status' => AccountsReceivableStatus::PendingApproval->value,
            'requested_by' => User::factory()->cashier(),
        ];
    }

    /**
     * Indicate that this credit request has been approved and is now an
     * active receivable.
     *
     * Uses afterCreating() rather than state() because approved_by/
     * approved_at are outside AccountsReceivable's #[Fillable] list and
     * would be silently dropped by a state()-merged create() call.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountsReceivableStatus::Active->value,
        ])->afterCreating(fn (AccountsReceivable $accountsReceivable) => $accountsReceivable->forceFill([
            'approved_by' => User::factory()->owner()->create()->id,
            'approved_at' => now(),
            'due_at' => now()->addDays(30),
        ])->save());
    }

    /**
     * Indicate that this active receivable's due_at is set such that its
     * derived agingBracket() (D-03) is exactly the given bracket.
     */
    public function atBracket(AccountsReceivableAgingBracket $bracket): static
    {
        return $this->active()->afterCreating(function (AccountsReceivable $accountsReceivable) use ($bracket) {
            $daysPastDue = match ($bracket) {
                AccountsReceivableAgingBracket::Current => -5,
                AccountsReceivableAgingBracket::OneToFifteen => 10,
                AccountsReceivableAgingBracket::SixteenToThirty => 20,
                AccountsReceivableAgingBracket::ThirtyOneToSixty => 45,
                AccountsReceivableAgingBracket::SixtyOneToNinety => 75,
                AccountsReceivableAgingBracket::NinetyPlus => 120,
            };

            $accountsReceivable->forceFill(['due_at' => now()->subDays($daysPastDue)])->save();
        });
    }

    /**
     * Indicate that this credit request has been rejected.
     *
     * Uses afterCreating() rather than state() for the same forceFill-only
     * reason as active().
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountsReceivableStatus::Rejected->value,
        ])->afterCreating(fn (AccountsReceivable $accountsReceivable) => $accountsReceivable->forceFill([
            'approved_by' => User::factory()->owner(),
            'approved_at' => now(),
        ])->save());
    }
}
