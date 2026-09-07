<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ApproveCreditRequest;
use App\Http\Requests\Owner\RejectCreditRequest;
use App\Models\AccountsReceivable;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditApprovalController extends Controller
{
    /**
     * Show the Owner's Credit Requests approval queue (POS-08). Admin can
     * view this page (route-group `role:owner,admin` middleware), but only
     * Owner can act on it — enforced by AccountsReceivablePolicy.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('owner/CreditRequests', [
            'creditRequests' => AccountsReceivable::query()
                ->where('status', AccountsReceivableStatus::PendingApproval->value)
                ->with(['jobOrder:id,number,description', 'jobOrder.queueEntry.customer:id,name', 'requestedBy:id,name'])
                ->get(['id', 'job_order_id', 'balance', 'requested_by', 'created_at']),
        ]);
    }

    /**
     * Approve a credit request, posting it as an active receivable and
     * unlocking the job order's On Credit payment path.
     *
     * Both writes (AccountsReceivable + JobOrder) are wrapped in a single
     * DB::transaction() per the Money-Moving precedent.
     */
    public function approve(ApproveCreditRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($request, $accountsReceivable): void {
            // Locked re-read (CR-05) — mirrors ConfirmPaymentIntent's own
            // idempotency boundary so a double-submitted/replayed approve
            // (slow network retry, double click, stale browser tab) can
            // never re-execute against an already-resolved receivable.
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $accountsReceivable->status === AccountsReceivableStatus::PendingApproval,
                422,
                __('This credit request has already been resolved.'),
            );

            $accountsReceivable->forceFill([
                'status' => AccountsReceivableStatus::Active,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'due_at' => now()->addDays(SystemConfiguration::getInt('credit_term_days', 30)),
            ])->save();

            $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::OnCredit])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit approved and posted to Accounts Receivable.')]);

        return back();
    }

    /**
     * Reject a credit request. No automatic fallback to another payment
     * method (D-07) — the job order stays flagged Credit Rejected until the
     * Cashier takes a different action.
     */
    public function reject(RejectCreditRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($request, $accountsReceivable): void {
            // Locked re-read (CR-05) — same idempotency boundary as
            // approve(), so rejecting an already-approved/already-rejected
            // receivable can never silently reverse a prior Owner decision.
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $accountsReceivable->status === AccountsReceivableStatus::PendingApproval,
                422,
                __('This credit request has already been resolved.'),
            );

            $accountsReceivable->forceFill([
                'status' => AccountsReceivableStatus::Rejected,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::CreditRejected])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit request rejected.')]);

        return back();
    }
}
