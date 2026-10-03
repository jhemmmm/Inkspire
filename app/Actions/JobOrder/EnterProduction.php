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
     * The stages that mean this job order has already entered production —
     * re-entering from any of them would write a second, contradictory
     * "first entry into production" log row and restart the SLA clock.
     *
     * @var array<int, JobOrderStatus>
     */
    private const ALREADY_IN_PRODUCTION = [
        JobOrderStatus::ForProduction,
        JobOrderStatus::Printing,
        JobOrderStatus::ReadyForPickup,
    ];

    /**
     * Automatically advance a job order into ForProduction the instant it
     * becomes eligible — a Type A file passing validation, or a Type B
     * design being approved (D-06, D-08, D-09). Stamps `due_at` from the
     * configured SLA window and writes the first, system-authored
     * `production_logs` row.
     *
     * Idempotent and self-defending: four call sites invoke this, and a
     * retried or double-submitted approval would otherwise produce two
     * `null -> for_production` rows and a silently extended due_at. A
     * cancelled or released job order is never re-entered either, so the
     * action can no longer resurrect an order its caller failed to guard.
     */
    public function __invoke(JobOrder $jobOrder): void
    {
        if ($jobOrder->cancelled_at !== null || $jobOrder->released_at !== null) {
            return;
        }

        if (in_array($jobOrder->status, self::ALREADY_IN_PRODUCTION, true)) {
            return;
        }

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
