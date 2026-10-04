<?php

namespace App\Http\Controllers\Public;

use App\Actions\JobOrder\OpenVisit;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\JobOrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreOnlineOrderRequest;
use App\Mail\ConfirmOnlineOrder;
use App\Mail\JobOrdersReceived;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\OnlineOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class OnlineOrderController extends Controller
{
    /** Orders one connection may send before it has to wait. */
    private const int ORDERS_PER_WINDOW = 20;

    private const int WINDOW_SECONDS = 600;

    /**
     * Show the public order form. Prices are deliberately never sent: the
     * shop confirms the price after the order is placed.
     */
    public function create(): Response
    {
        return Inertia::render('public/Order', [
            'pricingEntries' => PricingEntry::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit']),
            'specificationOptions' => SpecificationOption::activeLabelsByCategory(),
            'printSizeDimensions' => SpecificationOption::printSizeDimensionsByLabel(),
            'acceptedFileFormats' => ValidateJobOrderFile::acceptedFormats(),
            'sentTo' => session('orderSentTo'),
        ]);
    }

    /**
     * Park the order and email the customer a link to confirm it. Nothing
     * reaches the shop's workflow until that link is opened.
     */
    public function store(StoreOnlineOrderRequest $request): RedirectResponse
    {
        // Counted here rather than by the route's throttle, so that only a
        // submission about to send mail uses up the allowance. A visitor
        // still fixing a typo must not be locked out of their own order.
        $limiterKey = 'online-order:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::ORDERS_PER_WINDOW)) {
            return back()->withErrors(['email' => __('Too many orders were sent from this connection. Please try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($limiterKey) / 60),
            ])]);
        }

        RateLimiter::hit($limiterKey, self::WINDOW_SECONDS);

        $validated = $request->validated();
        $storedPaths = [];

        /** @var array<int, array<string, mixed>> $submittedRows */
        $submittedRows = $validated['job_orders'];

        $productNames = PricingEntry::query()
            ->whereIn('id', array_column($submittedRows, 'pricing_entry_id'))
            ->pluck('name', 'id');

        $rows = array_map(function (array $row, int|string $index) use ($request, $productNames, &$storedPaths): array {
            $file = $row['file'] ?? null;
            unset($row['file']);

            // The catalog's name, never the visitor's text: this is what the
            // shop's own emails and tables go on to show.
            $row['description'] = $productNames[$row['pricing_entry_id']];

            // Only a print-ready row may carry a file, and the request
            // already inspected it: its verdict is reused here rather than
            // reading the file a second time. Anything attached to a design
            // request was never checked, so it is not kept.
            $result = $request->fileCheck("job_orders.{$index}.file");

            if (! $file instanceof UploadedFile || $row['type'] !== JobOrderType::TypeA->value || $result === null) {
                return $row;
            }

            $row['file_path'] = $storedPaths[] = $file->store('job-orders', 'local');
            $row['file_check'] = ['outcome' => $result['outcome']->value, 'reason' => $result['reason']];

            return $row;
        }, $submittedRows, array_keys($submittedRows));

        $order = OnlineOrder::create([
            'email' => $validated['email'],
            'payload' => [
                'customer' => [
                    'name' => $validated['name'],
                    'organization' => $validated['organization'] ?? null,
                    'contact_number' => $validated['contact_number'],
                    'email' => $validated['email'],
                    'address' => $validated['address'],
                ],
                'job_orders' => $rows,
            ],
        ]);

        try {
            Mail::to($order->email)->send(new ConfirmOnlineOrder($order));
        } catch (\Throwable $e) {
            report($e);

            Storage::disk('local')->delete($storedPaths);
            $order->delete();

            return back()->withErrors(['email' => __("We couldn't send the confirmation email. Check the address and try again.")]);
        }

        return to_route('public.orders.create')->with('orderSentTo', $order->email);
    }

    /**
     * Show the signed confirmation page for an order.
     */
    public function show(int $onlineOrder): Response
    {
        $order = OnlineOrder::find($onlineOrder);

        if ($order === null) {
            return Inertia::render('public/OrderConfirm', ['state' => 'expired']);
        }

        if ($order->confirmed_at !== null) {
            return $this->renderConfirmed($order);
        }

        return Inertia::render('public/OrderConfirm', [
            'state' => 'pending',
            'email' => $order->email,
            'items' => collect($order->payload['job_orders'])->pluck('description')->all(),
            'confirmUrl' => URL::temporarySignedRoute(
                'public.orders.confirm',
                now()->addHours(48),
                ['onlineOrder' => $order->id],
            ),
        ]);
    }

    /**
     * Place the order: find or create the customer, open the online-lane
     * visit and email the tracking links. Opening the link twice does
     * nothing the second time.
     *
     * The customer is matched by mobile number alone and an existing record
     * is never overwritten: the visitor has proved they can read the email
     * they typed, not that the number is theirs. So the visit keeps that
     * email as its own contact address, and every later mail about these
     * job orders goes there rather than to the address on file.
     */
    public function confirm(int $onlineOrder, OpenVisit $openVisit): Response
    {
        $order = DB::transaction(function () use ($onlineOrder, $openVisit): ?OnlineOrder {
            $order = OnlineOrder::query()->lockForUpdate()->find($onlineOrder);

            if ($order === null || $order->confirmed_at !== null) {
                return $order;
            }

            $customer = Customer::firstOrCreate(
                ['contact_number' => $order->payload['customer']['contact_number']],
                [
                    'name' => $order->payload['customer']['name'],
                    'organization' => $order->payload['customer']['organization'] ?? null,
                    'email' => $order->payload['customer']['email'],
                    'address' => $order->payload['customer']['address'],
                ],
            );

            $entry = $openVisit($customer->id, $order->payload['job_orders'], QueueEntry::ONLINE_PREFIX, $order->email);

            $order->forceFill(['confirmed_at' => now(), 'queue_entry_id' => $entry->id])->save();

            return $order;
        });

        if ($order === null) {
            return Inertia::render('public/OrderConfirm', ['state' => 'expired']);
        }

        // Only the request that confirmed the order sends the mail.
        if ($order->wasChanged('confirmed_at')) {
            rescue(fn () => Mail::to($order->email)->send(new JobOrdersReceived($order->queueEntry()->firstOrFail()->load('jobOrders'))));
        }

        return $this->renderConfirmed($order);
    }

    /**
     * The confirmed state: each job order's number and its tracking link.
     */
    private function renderConfirmed(OnlineOrder $order): Response
    {
        $jobOrders = $order->queueEntry?->jobOrders()->orderBy('id')->get() ?? collect();

        return Inertia::render('public/OrderConfirm', [
            'state' => 'confirmed',
            'jobOrders' => $jobOrders->map(fn (JobOrder $jobOrder): array => [
                'number' => $jobOrder->number,
                'description' => $jobOrder->description,
                'trackingUrl' => route('public.tracking.token', ['token' => $jobOrder->tracking_token]),
            ])->all(),
        ]);
    }
}
