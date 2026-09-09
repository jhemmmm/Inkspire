<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use Inertia\Inertia;
use Inertia\Response;

class CollectionLetterController extends Controller
{
    /**
     * Render the printable collection letter for an Active AR entry
     * (D-11/D-12). Not-yet-due entries get a 200 with `pastDue: false` --
     * never a 404 -- so the Vue page itself renders the not-yet-due guard
     * copy (behavior block). `letterBody()` is only ever called on a
     * past-due bracket; a `Current` entry's body would throw.
     */
    public function show(AccountsReceivable $accountsReceivable): Response
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);

        $accountsReceivable->loadMissing([
            'jobOrder:id,number,description,total_amount,queue_entry_id',
            'jobOrder.queueEntry.customer:id,name',
            'jobOrder.transactions:id,job_order_id,amount,status',
        ]);

        $amountPaid = (float) $accountsReceivable->jobOrder->transactions->where('status', TransactionStatus::Completed->value)->sum('amount');
        $amountDue = $accountsReceivable->jobOrder->outstandingBalance();

        $bracket = $accountsReceivable->agingBracket();
        $pastDue = $bracket !== AccountsReceivableAgingBracket::Current;

        return Inertia::render('accounting-staff/CollectionLetter', [
            'jobOrderNumber' => $accountsReceivable->jobOrder->number,
            'customerName' => $accountsReceivable->jobOrder->queueEntry?->customer?->name,
            'jobOrderDescription' => $accountsReceivable->jobOrder->description,
            'creditExtended' => (float) $accountsReceivable->balance,
            'amountPaid' => $amountPaid,
            'amountDue' => $amountDue,
            'dueDate' => $accountsReceivable->due_at,
            'daysPastDue' => $accountsReceivable->daysPastDue(),
            'pastDue' => $pastDue,
            'letterBody' => $pastDue ? $bracket->letterBody() : null,
        ]);
    }
}
