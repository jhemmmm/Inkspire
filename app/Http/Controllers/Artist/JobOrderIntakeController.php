<?php

namespace App\Http\Controllers\Artist;

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Actions\JobOrder\OpenVisit;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\StoreJobOrderRequest;
use App\Mail\JobOrdersReceived;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderIntakeController extends Controller
{
    /**
     * Show the form an Artist uses to book a client's emailed request.
     */
    public function create(): Response
    {
        return Inertia::render('artist/NewJobOrder', [
            // ponytail: the whole list ships to the page; move to server-side search when it outgrows that.
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'organization', 'contact_number']),
            'specificationOptions' => SpecificationOption::activeLabelsByCategory(),
            'printSizeDimensions' => SpecificationOption::printSizeDimensionsByLabel(),
            'rushFeePercentage' => SystemConfiguration::getFloat('rush_fee_percentage', 0.0),
            'acceptedFileFormats' => ValidateJobOrderFile::acceptedFormats(),
            'pricingEntries' => PricingEntry::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'base_price', 'unit']),
        ]);
    }

    /**
     * Save the job orders, put them in the Artist's own queue when they are
     * on shift, and email the customer their tracking links.
     */
    public function store(StoreJobOrderRequest $request, OpenVisit $openVisit, ClaimJobOrderForArtist $claim): RedirectResponse
    {
        $artist = $request->user();

        [$customer, $entry, $claimed, $pooled] = DB::transaction(function () use ($request, $openVisit, $claim, $artist): array {
            $customer = $request->filled('customer_id')
                ? Customer::query()->findOrFail($request->integer('customer_id'))
                : Customer::create($request->validated('customer'));

            $entry = $openVisit($customer->id, $request->validated('job_orders'), QueueEntry::ONLINE_PREFIX);

            $claimed = [];
            $pooled = [];

            foreach ($entry->jobOrders as $jobOrder) {
                if ($jobOrder->status !== JobOrderStatus::Intake) {
                    continue;
                }

                if ($claim($jobOrder, $artist)) {
                    $claimed[] = $jobOrder->number;
                } else {
                    $pooled[] = $jobOrder->number;
                }
            }

            return [$customer, $entry, $claimed, $pooled];
        });

        try {
            Mail::to($customer->email)->send(new JobOrdersReceived($entry->load('jobOrders')));
        } catch (\Throwable $e) {
            report($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->toastMessage($entry, $claimed, $pooled)]);

        return to_route('artist.dashboard');
    }

    /**
     * @param  array<int, string|null>  $claimed
     * @param  array<int, string|null>  $pooled
     */
    private function toastMessage(QueueEntry $entry, array $claimed, array $pooled): string
    {
        $parts = [];

        if ($claimed !== []) {
            $parts[] = __(':numbers added to your queue.', ['numbers' => implode(', ', $claimed)]);
        }

        if ($pooled !== []) {
            $parts[] = count($pooled) === 1
                ? __(':numbers is in Available Jobs. Start your shift to accept it.', ['numbers' => $pooled[0]])
                : __(':numbers are in Available Jobs. Start your shift to accept them.', ['numbers' => implode(', ', $pooled)]);
        }

        if ($parts === []) {
            return __('Job order saved: :numbers.', [
                'numbers' => $entry->jobOrders->map(fn (JobOrder $jobOrder): ?string => $jobOrder->number)->implode(', '),
            ]);
        }

        return implode(' ', $parts);
    }
}
