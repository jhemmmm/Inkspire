<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\PriceJobOrder;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\SendPaymentLinkRequest;
use App\Mail\PaymentRequested;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Throwable;

class PaymentLinkController extends Controller
{
    public function __construct(public PriceJobOrder $priceJobOrder) {}

    /**
     * Save the job order's price (while it is still editable) without taking
     * payment, then email the customer a link to pay it online from their
     * tracking page. No Transaction is created and payment_status is left
     * as it was: the customer's own payment does that later.
     */
    public function store(SendPaymentLinkRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->abortUnlessPayable($jobOrder);

        $jobOrder = DB::transaction(function () use ($request, $jobOrder): JobOrder {
            $locked = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            $this->abortUnlessPayable($locked);

            if ($locked->pricingIsEditable()) {
                ($this->priceJobOrder)($locked, $request->validated());
                $locked->save();
            }

            abort_unless($locked->outstandingBalance() > 0, 422, __('This job order has no balance to pay yet.'));

            return $locked;
        });

        $email = $jobOrder->queueEntry->customer->email;

        // Unlike the other customer mails this one is sent synchronously: a
        // failure has to be visible to the Cashier, who can then fix the
        // address or hand the customer the link another way.
        try {
            Mail::to($email)->send(new PaymentRequested($jobOrder));
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __("The price was saved, but the email could not be sent. Check the customer's email address."),
            ]);

            return to_route('cashier.dashboard');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Price saved. Payment link emailed to :email.', ['email' => $email]),
        ]);

        return to_route('cashier.dashboard');
    }

    /**
     * The same eligibility rules PaymentController::store() applies.
     */
    private function abortUnlessPayable(JobOrder $jobOrder): void
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless(
            in_array($jobOrder->status, [
                JobOrderStatus::ReadyForProduction,
                JobOrderStatus::DesignApproved,
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::ReadyForPickup,
            ], true),
            422,
            'This job order is not ready for pricing.',
        );
        abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
        abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));
        abort_if($jobOrder->payment_status === PaymentStatus::CreditPendingApproval, 422, __('This job order has an On-Credit request awaiting Admin approval. Resolve it before recording a payment.'));
    }
}
