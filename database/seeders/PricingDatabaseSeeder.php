<?php

namespace Database\Seeders;

use App\Models\PricingEntry;
use Illuminate\Database\Seeder;

class PricingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds a starter catalog of common print-shop products/services so
     * POS-01's catalog pick has something to select from. No catalog
     * management UI exists yet this phase — Owner/Admin CRUD over this
     * table is out of scope until a later phase defines it. Uses
     * `updateOrCreate` so this seeder is idempotent and safe to re-run,
     * matching `SystemConfigurationSeeder`'s pattern.
     */
    public function run(): void
    {
        $entries = [
            ['name' => 'Tarpaulin (per sq ft)', 'base_price' => 25, 'unit' => 'sq ft', 'is_active' => true],
            ['name' => 'Business Cards (100 pcs)', 'base_price' => 250, 'unit' => '100 pcs', 'is_active' => true],
            ['name' => 'Sticker (per piece)', 'base_price' => 15, 'unit' => 'piece', 'is_active' => true],
            ['name' => 'Flyers A5 (100 pcs)', 'base_price' => 300, 'unit' => '100 pcs', 'is_active' => true],
            ['name' => 'ID Cards (per piece)', 'base_price' => 40, 'unit' => 'piece', 'is_active' => true],
            ['name' => 'Streamer Banner (per linear ft)', 'base_price' => 120, 'unit' => 'linear ft', 'is_active' => true],
            ['name' => 'Signage Vinyl (per sq ft)', 'base_price' => 180, 'unit' => 'sq ft', 'is_active' => true],
            ['name' => 'Wedding Invitation (per piece)', 'base_price' => 35, 'unit' => 'piece', 'is_active' => true],
        ];

        foreach ($entries as $entry) {
            PricingEntry::query()->updateOrCreate(
                ['name' => $entry['name']],
                $entry,
            );
        }
    }
}
