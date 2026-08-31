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
                'key' => 'dpi_threshold_minimum',
                'group' => 'business_rules',
                'value' => 300,
                'type' => 'integer',
                'label' => 'Minimum DPI threshold',
                'description' => null,
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
        }
    }
}
