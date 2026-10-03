<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\ReleaseJobOrderRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JobOrderReleaseController extends Controller
{
    /**
     * Release a job order to the customer (POS-09) — the single,
     * server-enforced gate that decides whether a job order can be
     * handed over. Re-checks payment_status and production stage itself on
     * every request, independent of whatever the UI happened to render
     * (RBAC-02 precedent: never trust a client-side-only decision).
     *
     * The ready_for_pickup gate belongs here as of Phase 6: without it a
     * fully-paid job order still at for_production/printing could be
     * stamped released_at, which drops it off the Production Board
     * (whereNull('released_at')) and makes /track report "Completed" for
     * an order that was never printed.
     *
     * The job order is re-read under a row lock first, the same way
     * CancellationController does, so a cancel and a release racing each
     * other can never both go through.
     */
    public function store(ReleaseJobOrderRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        DB::transaction(function () use ($jobOrder): void {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
            abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));

            abort_unless(
                $jobOrder->status === JobOrderStatus::ReadyForPickup,
                422,
                __("This job order isn't ready for pickup yet. Production hasn't marked it complete."),
            );

            abort_unless(
                in_array($jobOrder->payment_status, [PaymentStatus::Paid, PaymentStatus::OnCredit], true),
                422,
                match ($jobOrder->payment_status) {
                    PaymentStatus::CreditPendingApproval => __("This job order's On-Credit request is still pending Admin approval. Send the customer to Cashier."),
                    default => __("This job order isn't fully paid yet. Send the customer to Cashier before releasing it."),
                },
            );

            $jobOrder->forceFill(['released_at' => Carbon::now()])->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Released to customer.'),
        ]);

        return back();
    }
}
