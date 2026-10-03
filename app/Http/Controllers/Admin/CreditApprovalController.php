<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveCreditRequest;
use App\Http\Requests\Admin\RejectCreditRequest;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditApprovalController extends Controller
{
    /**
     * Show the Admin's Credit Requests approval queue (POS-08). Admin can
     * view this page (route-group `role:admin` middleware), but only
     * Admin can act on it — enforced by AccountsReceivablePolicy.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/CreditRequests', [
            'creditRequests' => AccountsReceivable::query()
                ->where('status', AccountsReceivableStatus::PendingApproval->value)
                // `queue_entry_id` is not optional in this select: without the
                // foreign key loaded, Eloquent cannot match the nested
                // queueEntry, `job_order.queue_entry` serialises as null, and
                // the page's `queue_entry.customer.name` throws — blanking the
                // whole screen for every pending request.
                ->with(['jobOrder:id,number,description,queue_entry_id', 'jobOrder.queueEntry.customer:id,name', 'requestedBy:id,name'])
                ->get(['id', 'job_order_id', 'balance', 'requested_by', 'created_at']),
        ]);
    }

    /**
     * Approve a credit request, posting it as an active receivable and
     * unlocking the job order's On Credit payment path.
     *
     * Both writes (AccountsReceivable + JobOrder) are wrapped in a single
     * DB::transaction() per the Money-Moving precedent.
     *
     * A locked re-read of the job order also guards against CR-01: a real
     * payment can land through a path other than PaymentController (which
     * now preventively blocks payment during CreditPendingApproval) while
     * this request sits pending. `outstandingBalance()` (D-16) is the
     * authoritative, current-code re-derivation — if it's already settled,
     * the approval is rejected and the AR row's `balance` is re-snapshotted
     * from that same derived value rather than the possibly-stale
     * request-time balance, matching WriteOffApprovalController::approve()'s
     * established re-snapshot discipline.
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

            $jobOrder = JobOrder::query()->whereKey($accountsReceivable->job_order_id)->lockForUpdate()->firstOrFail();

            abort_if($jobOrder->cancelled_at !== null, 422, __('This job order was cancelled, so its credit request can no longer be approved.'));
            abort_if($jobOrder->outstandingBalance() <= 0.0, 422, __('This job order was settled before the credit request could be approved.'));

            $accountsReceivable->forceFill([
                'status' => AccountsReceivableStatus::Active,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'due_at' => now()->addDays(SystemConfiguration::getInt('credit_term_days', 30)),
                'balance' => $jobOrder->outstandingBalance(),
            ])->save();

            $jobOrder->forceFill(['payment_status' => PaymentStatus::OnCredit])->save();
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
            // receivable can never silently reverse a prior Admin decision.
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
