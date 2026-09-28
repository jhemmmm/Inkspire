<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds every CONFIG-01 business rule, grouped to match the UI-SPEC's
     * three tabs (Security, Business Rules, File Handling). Uses
     * `updateOrCreate` so this seeder is idempotent and safe to re-run.
     */
    public function run(): void
    {
        $configurations = [
            // Security
            [
                'key' => 'account_lockout_max_attempts',
                'group' => 'security',
                'value' => 5,
                'type' => 'integer',
                'label' => 'Account lockout: max failed attempts',
                'description' => null,
            ],
            [
                'key' => 'account_lockout_minutes',
                'group' => 'security',
                'value' => 15,
                'type' => 'integer',
                'label' => 'Account lockout: duration (minutes)',
                'description' => null,
            ],
            [
                'key' => 'session_idle_timeout_minutes',
                'group' => 'security',
                'value' => 20,
                'type' => 'integer',
                'label' => 'Idle session timeout (minutes)',
                'description' => null,
            ],
            [
                'key' => 'password_min_length',
                'group' => 'security',
                'value' => 12,
                'type' => 'integer',
                'label' => 'Minimum password length',
                'description' => null,
            ],

            // Business Rules
            [
                'key' => 'rush_fee_percentage',
                'group' => 'business_rules',
                'value' => 0,
                'type' => 'decimal',
                'label' => 'Rush fee (%)',
                'description' => null,
            ],
            [
                'key' => 'vat_percentage',
                'group' => 'business_rules',
                'value' => 12,
                'type' => 'decimal',
                'label' => 'VAT (%)',
                'description' => 'Prices already include VAT. The receipt shows the VAT portion of the total; set 0 if the shop is not VAT-registered.',
            ],
            [
                'key' => 'large_format_minimum_dpi',
                'group' => 'business_rules',
                'value' => 100,
                'type' => 'integer',
                'label' => 'Minimum effective DPI',
                'description' => 'Pixels per printed inch a Type A file must reach for the ordered print size. Below this the job order goes to an artist for improvement instead of straight to production. 100 suits large-format viewing distances; raise it for small close-read work.',
            ],
            [
                'key' => 'default_sla_days',
                'group' => 'business_rules',
                'value' => 3,
                'type' => 'integer',
                'label' => 'Default SLA (days)',
                'description' => 'Global default only. A per-product SLA override is deferred until the Phase 3/5 pricing tables exist to key it against.',
            ],
            [
                'key' => 'max_artist_break_minutes',
                'group' => 'business_rules',
                'value' => 15,
                'type' => 'integer',
                'label' => 'Max artist break duration (minutes)',
                'description' => null,
            ],
            [
                'key' => 'expense_categories',
                'group' => 'business_rules',
                'value' => ['Utilities', 'Supplies', 'Rent'],
                'type' => 'array',
                'label' => 'Expense categories',
                'description' => null,
            ],
            [
                'key' => 'discount_cap_percentage',
                'group' => 'business_rules',
                'value' => 20,
                'type' => 'decimal',
                'label' => 'Discount cap (%)',
                'description' => null,
            ],
            [
                'key' => 'discount_cap_flat_amount',
                'group' => 'business_rules',
                'value' => 500,
                'type' => 'decimal',
                'label' => 'Discount cap (flat ₱)',
                'description' => null,
            ],
            [
                'key' => 'cancellation_fee_amount',
                'group' => 'business_rules',
                'value' => 500,
                'type' => 'decimal',
                'label' => 'Cancellation fee (flat ₱)',
                'description' => null,
            ],
            [
                'key' => 'credit_term_days',
                'group' => 'business_rules',
                'value' => 30,
                'type' => 'integer',
                'label' => 'Credit term (days)',
                'description' => 'Days after Owner approval before an On-Credit balance is considered due (D-02).',
            ],

            // File Handling
            [
                'key' => 'accepted_file_formats',
                'group' => 'file_handling',
                'value' => ['pdf', 'ai', 'eps', 'jpg', 'png'],
                'type' => 'array',
                'label' => 'Accepted file formats',
                'description' => null,
            ],
            [
                'key' => 'max_file_size_mb',
                'group' => 'file_handling',
                'value' => 50,
                'type' => 'integer',
                'label' => 'Max file size (MB)',
                'description' => null,
            ],
            [
                'key' => 'file_retention_days',
                'group' => 'file_handling',
                'value' => 90,
                'type' => 'integer',
                'label' => 'File retention (days)',
                'description' => null,
            ],
        ];

        foreach ($configurations as $configuration) {
            SystemConfiguration::updateOrCreate(
                ['key' => $configuration['key']],
                $configuration,
            );

            SystemConfiguration::invalidate($configuration['key']);
        }
    }
}
