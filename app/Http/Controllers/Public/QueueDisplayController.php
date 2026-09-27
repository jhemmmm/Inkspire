<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\QueueEntry;
use Inertia\Inertia;
use Inertia\Response;

class QueueDisplayController extends Controller
{
    /**
     * Show the public, unauthenticated shared queue display.
     *
     * Security boundary (D-09, T-02-15): the column list below is the only
     * thing standing between this route and a PII leak — never widen it to
     * a full model, never eager-load or reference the visit-owner relation.
     */
    public function index(): Response
    {
        return Inertia::render('public/QueueDisplay', [
            'queueEntries' => QueueEntry::query()
                // whereDate(), not where(): the 'queue_date' cast serializes
                // with a time component on write, which SQLite does not
                // truncate back to a bare date (see QueueEntry::nextForBusinessDay()).
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->orderByRaw("CASE queue_prefix WHEN 'R' THEN 0 ELSE 1 END")
                ->orderBy('queue_number')
                ->get(['id', 'queue_prefix', 'queue_number', 'status']),
        ]);
    }
}
