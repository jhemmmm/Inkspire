<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Http\Controllers\Controller;
use App\Mail\CustomerBalanceReminder;
use App\Models\AccountsReceivable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Throwable;

class CustomerBalanceEmailController extends Controller
{
    /** Seconds before the same entry can be emailed again, by anyone. */
    private const int RESEND_AFTER_SECONDS = 60;

    /**
     * Email the customer about their unpaid credit balance. The wording
     * follows how far past due it is.
     *
     * Sent synchronously, like the Cashier's payment link, so a failure is
     * visible to the person who pressed the button. The automatic staff
     * reminders keep their own last_reminder_* columns and are not affected.
     */
    public function store(AccountsReceivable $accountsReceivable): RedirectResponse
    {
        abort_unless($accountsReceivable->canEmailCustomer(), 422, __('This entry has no unpaid balance to email about.'));

        $recentlySentKey = 'ar-customer-email:'.$accountsReceivable->id;

        if (! Cache::add($recentlySentKey, true, self::RESEND_AFTER_SECONDS)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This customer was just emailed. Wait a minute before sending again.')]);

            return back();
        }

        $email = $accountsReceivable->jobOrder->queueEntry->contactEmail();

        try {
            Mail::to($email)->send(new CustomerBalanceReminder($accountsReceivable));
        } catch (Throwable $e) {
            report($e);
            Cache::forget($recentlySentKey);

            Inertia::flash('toast', ['type' => 'error', 'message' => __("The email could not be sent. Check the customer's email address.")]);

            return back();
        }

        $accountsReceivable->forceFill(['customer_emailed_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email sent to :email.', ['email' => $email])]);

        return back();
    }
}
