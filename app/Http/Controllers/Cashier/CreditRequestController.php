<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\ComputeJobOrderPrice;
use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CreateCreditRequestRequest;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CreditRequestController extends Controller
{
    public function __construct(public ComputeJobOrderPrice $computeJobOrderPrice) {}

    /**
     * Request On-Credit approval for a job order's full outstanding
     * balance (POS-08/D-06). Open-eligibility per D-08 — any Cashier can
     * request credit; the real gate is the Owner-only approval step.
     */
    public function store(CreateCreditRequestRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless(
            in_array($jobOrder->status, [
                JobOrderStatus::ReadyForProduction,
                JobOrderStatus::DesignApproved,
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::QualityCheck,
                JobOrderStatus::ReadyForPickup,
            ], true),
            422,
            'This job order is not ready for pricing.',
        );
        abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
        abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot be placed on credit.'));
        abort_if(
            in_array($jobOrder->payment_status, [PaymentStatus::PendingConfirmation, PaymentStatus::CreditPendingApproval], true),
            422,
            'This job order already has a payment action pending.',
        );

        DB::transaction(function () use ($request, $jobOrder): void {
            // Locked re-read + re-checked preconditions (WR-04) — the
            // abort_if() calls above ran on an unlocked read, so a
            // double-submitted/replayed request could otherwise race past
            // them both and create two AccountsReceivable rows for the same
            // job order before either transaction commits.
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
            abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
            abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot be placed on credit.'));
            abort_if(
                in_array($jobOrder->payment_status, [PaymentStatus::PendingConfirmation, PaymentStatus::CreditPendingApproval], true),
                422,
                'This job order already has a payment action pending.',
            );

            if ($jobOrder->total_amount === null) {
                // On Credit can be the very first payment action taken for a
                // job order (D-08 needs no prior pricing step), but the
                // Pricing card is still shown/submitted alongside per
                // UI-SPEC §2's single combined page — snapshot pricing the
                // same way PaymentController::store() does.
                $computed = ($this->computeJobOrderPrice)(
                    (float) $request->validated('line_amount'),
                    (bool) $request->validated('rush_fee_applied'),
                    $request->validated('discount_type'),
                    $request->validated('discount_value') !== null ? (float) $request->validated('discount_value') : null,
                );

                $jobOrder->forceFill([
                    'pricing_entry_id' => $request->validated('pricing_entry_id'),
                    'base_price_snapshot' => $computed['base_price_snapshot'],
                    'rush_fee_applied' => $request->validated('rush_fee_applied'),
                    'rush_fee_amount' => $computed['rush_fee_amount'],
                    'discount_type' => $request->validated('discount_type'),
                    'discount_value' => $request->validated('discount_value'),
                    'discount_amount' => $computed['discount_amount'],
                    'total_amount' => $computed['total_amount'],
                ]);
            }

            // The full REMAINING outstanding amount, not the original total
            // — if a down payment already exists, the AR balance only
            // covers what's actually still owed (D-09).
            $outstandingBalance = $jobOrder->outstandingBalance();

            AccountsReceivable::create([
                'job_order_id' => $jobOrder->id,
                'balance' => $outstandingBalance,
                'status' => AccountsReceivableStatus::PendingApproval->value,
                'requested_by' => $request->user()->id,
            ]);

            $jobOrder->forceFill(['payment_status' => PaymentStatus::CreditPendingApproval])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('On-Credit requested. Awaiting Owner approval.')]);

        // Explicit route rather than back() — the submitting <Form> lives on
        // this same Job Order Payment page, so back() would return here
        // (now stale: the job order is no longer payment-eligible) instead
        // of the Cashier Dashboard UI-SPEC §2 specifies.
        return to_route('cashier.dashboard');
    }
}
