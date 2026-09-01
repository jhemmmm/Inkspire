<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Owner->value,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an Owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner->value,
        ]);
    }

    /**
     * Indicate that the user is an Admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin->value,
        ]);
    }

    /**
     * Indicate that the user is Frontline Staff.
     */
    public function frontlineStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::FrontlineStaff->value,
        ]);
    }

    /**
     * Indicate that the user is an Artist.
     */
    public function artist(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Artist->value,
        ]);
    }

    /**
     * Indicate that the user is a Cashier.
     */
    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cashier->value,
        ]);
    }

    /**
     * Indicate that the user is Production Staff.
     */
    public function productionStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ProductionStaff->value,
        ]);
    }

    /**
     * Indicate that the user is Accounting Staff.
     */
    public function accountingStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AccountingStaff->value,
        ]);
    }

    /**
     * Indicate that the user's account is currently locked out.
     *
     * Uses afterCreating() rather than state() because failed_login_attempts
     * and locked_until are outside User's #[Fillable] list and would be
     * silently dropped by a state()-merged create() call.
     */
    public function locked(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ])->save());
    }

    /**
     * Indicate that the user's account has been deactivated.
     *
     * Uses afterCreating() rather than state() because is_active is outside
     * User's #[Fillable] list and would be silently dropped by a
     * state()-merged create() call.
     */
    public function deactivated(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'is_active' => false,
        ])->save());
    }
}
