<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\PriceJobOrder;
use App\Enums\JobOrderStatus;
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
        $jobOrder = DB::transaction(function () use ($request, $jobOrder): JobOrder {
            $locked = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            // The same eligibility rules PaymentController::store() applies.
            if (($blocker = $locked->paymentBlocker()) !== null) {
                abort(422, __($blocker));
            }

            abort_unless(in_array($locked->status, JobOrderStatus::PAYABLE, true), 422, 'This job order is not ready for pricing.');

            if ($locked->pricingIsEditable()) {
                ($this->priceJobOrder)($locked, $request->validated());
                $locked->save();
            }

            // The tracking page's own rule, so a link is never sent for an
            // order it will not offer payment on (On Credit above all).
            abort_unless($locked->onlinePaymentState() === 'due', 422, __('This job order has nothing the customer can pay online.'));

            return $locked;
        });

        $email = $jobOrder->queueEntry->contactEmail();

        // Unlike the other customer mails this one is sent synchronously: a
        // failure has to be visible to the Cashier, who can then fix the
        // address or hand the customer the link another way.
        try {
            Mail::to($email)->send(new PaymentRequested($jobOrder));

            $toast = ['type' => 'success', 'message' => __('Price saved. Payment link emailed to :email.', ['email' => $email])];
        } catch (Throwable $e) {
            report($e);

            $toast = ['type' => 'error', 'message' => __("The price was saved, but the email could not be sent. Check the customer's email address.")];
        }

        Inertia::flash('toast', $toast);

        return to_route('cashier.dashboard');
    }
}
