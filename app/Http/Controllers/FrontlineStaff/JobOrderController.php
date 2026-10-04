<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\EnterProduction;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\ReplaceJobOrderFileRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderController extends Controller
{
    public function __construct(
        public ValidateJobOrderFile $validateJobOrderFile,
        public EnterProduction $enterProduction,
    ) {}

    /**
     * The whole record for one job order, whatever stage it is at and
     * whoever owns it right now (SRCH-01).
     *
     * Deliberately unscoped: the counter is asked about job orders sitting
     * in every other portal's queue — an artist's pool, the press, the
     * cashier's unpaid list — and answering "let me check" means reading
     * the row, not owning it. Nothing here mutates, so the read is safe
     * across role boundaries in a way an action never would be.
     *
     * Money is included because the customer standing at the counter is the
     * one who paid it. The artist's internal consultation notes are not:
     * that is a working scratchpad, not an answer to a customer question.
     */
    public function show(JobOrder $jobOrder): Response
    {
        $jobOrder->load([
            'queueEntry:id,customer_id,queue_prefix,queue_number,queue_date',
            'queueEntry.customer:id,name,contact_number,email,organization',
            'assignedArtist:id,name,artist_label',
            'pricingEntry:id,name,base_price,unit',
            'transactions' => fn ($query) => $query->orderBy('created_at'),
            'productionLogs' => fn ($query) => $query->orderBy('created_at'),
            'designFile:id,job_order_id,created_at',
        ]);

        return Inertia::render('frontline-staff/JobOrderDetail', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'number' => $jobOrder->number,
                'description' => $jobOrder->description,
                'type' => $jobOrder->type->value,
                'status' => $jobOrder->status->value,
                'display_status' => $jobOrder->display_status,
                'is_rush' => $jobOrder->is_rush,
                'print_size' => $jobOrder->print_size,
                'width_ft' => $jobOrder->width_ft,
                'height_ft' => $jobOrder->height_ft,
                'quantity' => $jobOrder->quantity,
                'deadline' => $jobOrder->deadline?->toDateString(),
                'due_at' => $jobOrder->due_at?->toIso8601String(),
                'client_notes' => $jobOrder->client_notes,
                'validation_failure_reason' => $jobOrder->validation_failure_reason,
                'has_file' => $jobOrder->file_path !== null,
                'has_design_file' => $jobOrder->designFile !== null,
                'payment_status' => $jobOrder->payment_status->value,
                'base_price_snapshot' => $jobOrder->base_price_snapshot,
                'rush_fee_applied' => $jobOrder->rush_fee_applied,
                'rush_fee_amount' => $jobOrder->rush_fee_amount,
                'discount_amount' => $jobOrder->discount_amount,
                'total_amount' => $jobOrder->total_amount,
                'outstanding_balance' => $jobOrder->outstandingBalance(),
                'created_at' => $jobOrder->created_at?->toIso8601String(),
                'accepted_at' => $jobOrder->accepted_at?->toIso8601String(),
                'released_at' => $jobOrder->released_at?->toIso8601String(),
                'cancelled_at' => $jobOrder->cancelled_at?->toIso8601String(),
                'tracking_token' => $jobOrder->tracking_token,
                'queue_entry' => $jobOrder->queueEntry === null ? null : [
                    'label' => $jobOrder->queueEntry->paddedNumber(),
                    'queue_date' => $jobOrder->queueEntry->queue_date?->toDateString(),
                    'customer' => $jobOrder->queueEntry->customer === null ? null : [
                        'name' => $jobOrder->queueEntry->customer->name,
                        'contact_number' => $jobOrder->queueEntry->customer->contact_number,
                        'email' => $jobOrder->queueEntry->contactEmail(),
                        'organization' => $jobOrder->queueEntry->customer->organization,
                    ],
                ],
                'assigned_artist' => $jobOrder->assignedArtist === null ? null : [
                    'name' => $jobOrder->assignedArtist->name,
                    'artist_label' => $jobOrder->assignedArtist->artist_label,
                ],
                'pricing_entry' => $jobOrder->pricingEntry === null ? null : [
                    'name' => $jobOrder->pricingEntry->name,
                    'base_price' => $jobOrder->pricingEntry->base_price,
                    'unit' => $jobOrder->pricingEntry->unit,
                ],
                'transactions' => $jobOrder->transactions->map(fn ($transaction): array => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'method' => $transaction->payment_method?->value,
                    'status' => $transaction->status->value,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])->all(),
                'production_logs' => $jobOrder->productionLogs->map(fn ($log): array => [
                    'id' => $log->id,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * The only statuses a file replacement may act on — everything before
     * the job order enters production. The UI only ever renders the replace
     * dialog for ValidationFailed, but the server re-checks independently
     * (RBAC-02 precedent: never trust a client-side-only decision).
     *
     * @var array<int, JobOrderStatus>
     */
    private const REPLACEABLE_STATUSES = [
        JobOrderStatus::Intake,
        JobOrderStatus::ValidationFailed,
        JobOrderStatus::ReadyForProduction,
    ];

    /**
     * Replace a Type A job order's file and re-run validation in one save
     * (D-02/D-03) — Type B job orders have no file concept.
     *
     * Guarded against replacing a file on a job order that has already
     * moved on: this path re-enters production via EnterProduction, which
     * would drag an in-production/released order back to for_production
     * with a freshly reset due_at and a falsified system-authored
     * "first entry into production" log row — or, on a failing file, park
     * it at validation_failed where no board lists it at all.
     */
    public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));
        abort_unless(
            in_array($jobOrder->status, self::REPLACEABLE_STATUSES, true),
            422,
            __('This job order has already entered production and its file can no longer be replaced.'),
        );

        $file = $request->file('file');
        $result = ($this->validateJobOrderFile)(
            $file,
            $jobOrder->print_size,
            $jobOrder->width_ft !== null ? (float) $jobOrder->width_ft : null,
            $jobOrder->height_ft !== null ? (float) $jobOrder->height_ft : null,
        );

        $jobOrder->forceFill([
            'file_path' => $file->store('job-orders', 'local'),
            'status' => match ($result['outcome']) {
                FileValidationOutcome::Passed => JobOrderStatus::ReadyForProduction,
                FileValidationOutcome::NeedsArtist => JobOrderStatus::Intake,
                FileValidationOutcome::Rejected => JobOrderStatus::ValidationFailed,
            },
            'validation_failure_reason' => $result['reason'],
        ])->save();

        if ($result['outcome'] === FileValidationOutcome::Passed) {
            ($this->enterProduction)($jobOrder);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match ($result['outcome']) {
                FileValidationOutcome::Passed => __('File replaced. Job order is ready for production.'),
                FileValidationOutcome::NeedsArtist => __('File replaced, but it is still too low-resolution for this size. An artist will improve it.'),
                FileValidationOutcome::Rejected => __('File replaced, but it still can\'t be used. See the updated reason below.'),
            },
        ]);

        return back();
    }
}
