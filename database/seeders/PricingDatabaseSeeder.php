<?php

namespace Database\Seeders;

use App\Models\PricingEntry;
use Illuminate\Database\Seeder;

class PricingDatabaseSeeder extends Seeder
{
    /**
     * Seed SquareFoot Graphics & Ads' actual walk-in price list, as supplied
     * by the owner.
     *
     * Several source rows carry a compound price — "100 / 150 Installation",
     * "150 / 250 b2b", "400/sqft + 50/pc CB". `base_price` holds the plain
     * print figure and `unit` carries the qualifier verbatim, so the Cashier
     * reads the real terms at the point of pricing. Modelling
     * installed-vs-supplied as its own priced option is a pricing-engine
     * change, not an intake one.
     *
     * `firstOrCreate` on `name` keeps this idempotent and fills gaps only:
     * the Admin maintains the catalog from `admin.products.index` after
     * this, and a re-seed must not undo a price they changed or restore an
     * entry they retired.
     *
     * @return array<int, array{name: string, base_price: float, unit: string}>
     */
    private function walkInEntries(): array
    {
        return [
            ['name' => 'Tarpaulin', 'base_price' => 12, 'unit' => 'sq ft'],
            ['name' => 'Blackout', 'base_price' => 20, 'unit' => 'sq ft'],
            ['name' => 'Sticker Vinyl Printed', 'base_price' => 80, 'unit' => 'sq ft'],
            ['name' => 'Sticker Vinyl Printed (Pre-cut)', 'base_price' => 150, 'unit' => 'sq ft'],
            ['name' => 'Sticker Vinyl Cutted', 'base_price' => 200, 'unit' => 'sq ft'],
            ['name' => 'Sticker with Lamination', 'base_price' => 125, 'unit' => 'sq ft'],
            ['name' => 'Reflectorize Printed', 'base_price' => 200, 'unit' => 'sq ft'],
            ['name' => 'Reflectorize Cutted', 'base_price' => 300, 'unit' => 'sq ft'],
            ['name' => 'Transparent Sticker Printed', 'base_price' => 80, 'unit' => 'sq ft'],
            ['name' => 'Frosted Sticker Printed', 'base_price' => 100, 'unit' => 'sq ft (150 installed)'],
            ['name' => 'Frosted Sticker Cutted', 'base_price' => 200, 'unit' => 'sq ft (250 installed)'],
            ['name' => 'Perforated Sticker Printed', 'base_price' => 100, 'unit' => 'sq ft (150 installed)'],
            ['name' => 'Prismatic Reflective Sticker', 'base_price' => 300, 'unit' => 'sq ft'],
            ['name' => 'Panaflex Print / UV Print', 'base_price' => 90, 'unit' => 'sq ft (150 UV)'],
            ['name' => 'Panaflex with Laminate', 'base_price' => 130, 'unit' => 'sq ft'],
            ['name' => 'Pull up Banner (Big)', 'base_price' => 1500, 'unit' => 'piece'],
            ['name' => 'Pull up Banner (Small)', 'base_price' => 1200, 'unit' => 'piece'],
            ['name' => 'X-Stand Banner', 'base_price' => 500, 'unit' => 'piece'],
            ['name' => 'Sticker on Magnet', 'base_price' => 200, 'unit' => 'sq ft'],
            ['name' => 'Sticker on Sintraboard', 'base_price' => 200, 'unit' => 'sq ft (300 back-to-back)'],
            ['name' => 'Sticker on Foamboard', 'base_price' => 100, 'unit' => 'sq ft'],
            ['name' => 'Matte Photopaper', 'base_price' => 50, 'unit' => 'sq ft'],
            ['name' => 'Printed Sticker on Acrylic', 'base_price' => 400, 'unit' => 'sq ft'],
            ['name' => 'Cutted Sticker on Acrylic', 'base_price' => 500, 'unit' => 'sq ft'],
            ['name' => 'Tarp with Lamination', 'base_price' => 40, 'unit' => 'sq ft (65 installed)'],
            ['name' => 'Tarp on Foamboard', 'base_price' => 60, 'unit' => 'sq ft'],
            ['name' => 'Tarp on Sintraboard', 'base_price' => 80, 'unit' => 'sq ft'],
            ['name' => 'Tarp with Wood Frame', 'base_price' => 50, 'unit' => 'sq ft'],
            ['name' => 'Tarp with Metal Frame', 'base_price' => 80, 'unit' => 'sq ft'],
            ['name' => 'Blackout with Wood Frame', 'base_price' => 60, 'unit' => 'sq ft'],
            ['name' => 'Blackout with Metal Frame', 'base_price' => 100, 'unit' => 'sq ft'],
            ['name' => 'Canvas Print Only', 'base_price' => 100, 'unit' => 'sq ft'],
            ['name' => 'Canvas with Frame', 'base_price' => 200, 'unit' => 'sq ft'],
            ['name' => 'Backlit Print', 'base_price' => 100, 'unit' => 'sq ft'],
            ['name' => 'Mug Print', 'base_price' => 100, 'unit' => 'piece'],
            ['name' => '3mm Acrylic Sandwich', 'base_price' => 400, 'unit' => 'sq ft (+50/pc CB)'],
        ];
    }

    /**
     * The owner's "PLAIN ONLY" table — unprinted media sold by the sheet,
     * roll or square foot. Suffixed so they never collide with the printed
     * entry of the same media in the walk-in list above.
     *
     * @return array<int, array{name: string, base_price: float, unit: string}>
     */
    private function plainEntries(): array
    {
        return [
            ['name' => 'Tarpaulin (Plain)', 'base_price' => 6, 'unit' => 'sq ft'],
            ['name' => 'Tarpaulin Roll (Plain)', 'base_price' => 6300, 'unit' => 'roll'],
            ['name' => 'Panaflex (Plain)', 'base_price' => 30, 'unit' => 'sq ft'],
            ['name' => 'Blackout (Plain)', 'base_price' => 12, 'unit' => 'sq ft'],
            ['name' => 'Sticker Vinyl (Plain)', 'base_price' => 30, 'unit' => 'sq ft'],
            ['name' => 'Transparent Sticker (Plain)', 'base_price' => 25, 'unit' => 'sq ft'],
            ['name' => 'Photopaper (Plain)', 'base_price' => 25, 'unit' => 'sq ft'],
            ['name' => 'Frosted Sticker (Plain)', 'base_price' => 50, 'unit' => 'sq ft'],
            ['name' => 'Perforated Sticker (Plain)', 'base_price' => 50, 'unit' => 'sq ft'],
            ['name' => 'Reflectorize (Plain)', 'base_price' => 100, 'unit' => 'sq ft'],
            ['name' => 'Sintraboard 3mm (Plain)', 'base_price' => 600, 'unit' => 'piece'],
            ['name' => 'Sintraboard 5mm (Plain)', 'base_price' => 800, 'unit' => 'piece'],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([...$this->walkInEntries(), ...$this->plainEntries()] as $entry) {
            PricingEntry::query()->firstOrCreate(
                ['name' => $entry['name']],
                [...$entry, 'is_active' => true],
            );
        }
    }
}
