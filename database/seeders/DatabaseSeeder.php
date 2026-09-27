<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The shop's real starting state: the reference data the system cannot
     * run without, plus exactly one account to sign in with.
     *
     * DemoDataSeeder is deliberately NOT called from here. It fabricates
     * customers, job orders and transactions, which is what you want for a
     * walkthrough and never what you want in front of real money. Run it by
     * name when you need it:
     *
     *     php artisan db:seed --class=DemoDataSeeder
     *
     * The three seeders below are the opposite: PricingDatabaseSeeder holds
     * SquareFoot's actual walk-in price list, SpecificationOptionSeeder the
     * print sizes, SystemConfigurationSeeder the business rules (rush fee,
     * DPI floor, file size cap). All three are idempotent
     * on their natural key, so re-running them updates rather than
     * duplicates.
     */
    public function run(): void
    {
        $this->call(SystemConfigurationSeeder::class);
        $this->call(PricingDatabaseSeeder::class);
        $this->call(SpecificationOptionSeeder::class);

        $this->createAdministrator();
    }

    /**
     * One Admin, so there is somebody who can create everybody else.
     *
     * Staff accounts are not seeded: they are real people with real names,
     * added from Admin → Users once you are signed in. `is_active` is set
     * with forceFill because it sits outside User's #[Fillable] list by
     * design — the same reason DemoDataSeeder does it that way.
     */
    private function createAdministrator(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@inkspire.test'],
            [
                'name' => 'Admin',
                'password' => 'DemoPass123!',
                'role' => UserRole::Admin->value,
                'email_verified_at' => now(),
            ],
        );

        $admin->forceFill(['is_active' => true])->save();

        $this->command?->warn('Administrator: admin@inkspire.test / DemoPass123! — change this password before the shop uses it.');
    }
}
