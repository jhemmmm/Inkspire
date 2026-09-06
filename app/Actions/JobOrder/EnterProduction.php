<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\DB;

class EnterProduction
{
    /**
     * Automatically advance a job order into ForProduction the instant it
     * becomes eligible — a Type A file passing validation, or a Type B
     * design being approved (D-06, D-08, D-09). Stamps `due_at` from the
     * configured SLA window and writes the first, system-authored
     * `production_logs` row.
     */
    public function __invoke(JobOrder $jobOrder): void
    {
        DB::transaction(function () use ($jobOrder): void {
            $dueAt = now()->addDays(SystemConfiguration::getInt('default_sla_days', 3));

            $jobOrder->forceFill([
                'status' => JobOrderStatus::ForProduction,
                'due_at' => $dueAt,
            ])->save();

            ProductionLog::create([
                'job_order_id' => $jobOrder->id,
                'from_status' => null,
                'to_status' => JobOrderStatus::ForProduction,
                'reason' => null,
                'recorded_by' => null,
            ]);
        });
    }
}
