<?php

namespace Database\Seeders;

use App\Enums\SpecificationCategory;
use App\Models\SpecificationOption;
use Illuminate\Database\Seeder;

class SpecificationOptionSeeder extends Seeder
{
    /**
     * Seed the print specification catalog behind intake's Print Size
     * select. Owner/Admin maintains it from
     * `owner.specifications.index` after this.
     *
     * Print sizes carry their printed dimensions in inches. Those are not
     * decoration: effective DPI is pixels per printed inch, so
     * ValidateJobOrderFile cannot judge whether a customer's file is sharp
     * enough without them. "Custom Size" deliberately has none — it stands in
     * for a size nobody has recorded yet, and the quality check skips rather
     * than guesses.
     *
     * @return array<int, array{0: SpecificationCategory, 1: string, 2: float|null, 3: float|null}>
     */
    private function options(): array
    {
        return [
            // Tarpaulin sizes, quoted in feet by the shop; 1 ft = 12 in.
            [SpecificationCategory::PrintSize, 'Tarpaulin 2x3ft', 24, 36],
            [SpecificationCategory::PrintSize, 'Tarpaulin 3x5ft', 36, 60],
            [SpecificationCategory::PrintSize, 'Tarpaulin 3x6ft', 36, 72],
            [SpecificationCategory::PrintSize, 'Tarpaulin 4x8ft', 48, 96],
            [SpecificationCategory::PrintSize, 'Tarpaulin 6x10ft', 72, 120],
            [SpecificationCategory::PrintSize, 'A4 (8.3x11.7in)', 8.3, 11.7],
            [SpecificationCategory::PrintSize, 'A3 (11.7x16.5in)', 11.7, 16.5],
            [SpecificationCategory::PrintSize, 'Business Card (3.5x2in)', 3.5, 2],
            [SpecificationCategory::PrintSize, 'Mug Wrap (8.5x3.5in)', 8.5, 3.5],
            // The owner's MINIMUM SIZES table: media the shop will not print
            // below a floor, squared off at that minimum.
            [SpecificationCategory::PrintSize, 'Frosted Sticker (min. 4ft)', 48, 48],
            [SpecificationCategory::PrintSize, 'Perforated Sticker (min. 54in)', 54, 54],
            [SpecificationCategory::PrintSize, 'Sticker on Magnet (min. 2ft)', 24, 24],
            [SpecificationCategory::PrintSize, 'Photopaper (min. 50in)', 50, 50],
            [SpecificationCategory::PrintSize, 'Transparent Sticker (min. 4.5ft)', 54, 54],
            [SpecificationCategory::PrintSize, 'Panaflex Corflex (min. 4.5ft)', 54, 54],
            [SpecificationCategory::PrintSize, 'Canvas (min. 4ft)', 48, 48],
            [SpecificationCategory::PrintSize, 'Custom Size', null, null],
        ];
    }

    /**
     * Placeholder labels seeded before the owner's real lists arrived.
     *
     * These carry no dimensions, so leaving them offered would silently opt
     * their job orders out of the intake file check — and each near-duplicates
     * a real entry ("A4 (210x297mm)" vs "A4 (8.3x11.7in)", "Photo Paper" vs
     * "Photopaper"), which is an easy mis-pick at the counter.
     *
     * Deactivated rather than deleted so the Owner can see what happened on
     * the Specifications screen and delete them deliberately.
     *
     * @return array<int, string>
     */
    private function supersededPrintSizes(): array
    {
        return [
            'A4 (210x297mm)',
            'A3 (297x420mm)',
            'Business Card (90x55mm)',
        ];
    }

    /**
     * Run the database seeds.
     *
     * `updateOrCreate` on the (category, label) unique key keeps this
     * idempotent and re-runnable, matching SystemConfigurationSeeder and
     * PricingDatabaseSeeder.
     */
    public function run(): void
    {
        foreach ($this->options() as $index => [$category, $label, $width, $height]) {
            SpecificationOption::query()->updateOrCreate(
                ['category' => $category, 'label' => $label],
                [
                    'width_inches' => $width,
                    'height_inches' => $height,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        SpecificationOption::query()
            ->category(SpecificationCategory::PrintSize)
            ->whereIn('label', $this->supersededPrintSizes())
            ->update(['is_active' => false]);
    }
}
