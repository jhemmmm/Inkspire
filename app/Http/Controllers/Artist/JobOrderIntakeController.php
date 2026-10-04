<?php

namespace App\Http\Controllers\Artist;

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Actions\JobOrder\OpenVisit;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\SearchCustomersRequest;
use App\Http\Requests\Artist\StoreJobOrderRequest;
use App\Mail\JobOrdersReceived;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use App\Models\SystemConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderIntakeController extends Controller
{
    /**
     * How many customers the picker is handed at a time. The page searches
     * the server as the Artist types, so the list never has to hold everyone
     * the shop has served.
     */
    private const int CUSTOMER_OPTIONS = 20;

    /**
     * Show the form an Artist uses to book a client's emailed request.
     *
     * The customer picker re-requests `customers` and `hasMoreCustomers`
     * alone on every search, so everything else is a closure: a partial
     * reload skips it instead of rebuilding the whole intake catalog per
     * keystroke.
     */
    public function create(SearchCustomersRequest $request): Response
    {
        // One row past the limit is how the page learns there are more.
        $customers = Customer::query()
            ->when($request->filled('customer_search'), function (Builder $query) use ($request): void {
                $query->search((string) $request->string('customer_search'));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::CUSTOMER_OPTIONS + 1)
            ->get(['id', 'name', 'organization', 'contact_number']);

        return Inertia::render('artist/NewJobOrder', [
            'customers' => $customers->take(self::CUSTOMER_OPTIONS),
            'hasMoreCustomers' => $customers->count() > self::CUSTOMER_OPTIONS,
            'specificationOptions' => fn () => SpecificationOption::activeLabelsByCategory(),
            'printSizeDimensions' => fn () => SpecificationOption::printSizeDimensionsByLabel(),
            'rushFeePercentage' => fn () => SystemConfiguration::getFloat('rush_fee_percentage', 0.0),
            'acceptedFileFormats' => fn () => ValidateJobOrderFile::acceptedFormats(),
            'pricingEntries' => fn () => PricingEntry::query()
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

        rescue(fn () => Mail::to($customer->email)->send(new JobOrdersReceived($entry->load('jobOrders'))));

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
