<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PickupConfirmationController extends Controller
{
    /**
     * Record that a customer received the order staff released to them, from
     * their own tracking page. The token is the credential and nothing else
     * is taken from the request.
     *
     * Anything other than a released, unconfirmed order is left as it is and
     * still redirects: the tracking page shows where the order stands, and a
     * public page has nowhere to show an error toast. The row lock keeps the
     * first confirmation's time when two arrive together.
     */
    public function store(string $token): RedirectResponse
    {
        DB::transaction(function () use ($token): void {
            $jobOrder = JobOrder::query()
                ->where('tracking_token', $token)
                ->lockForUpdate()
                ->firstOrFail(['id', 'released_at', 'cancelled_at', 'pickup_confirmed_at']);

            if ($jobOrder->pickupState() === 'awaiting') {
                $jobOrder->forceFill(['pickup_confirmed_at' => now()])->save();
            }
        });

        return to_route('public.tracking.token', ['token' => $token]);
    }
}
