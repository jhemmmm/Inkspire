<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use Barryvdh\DomPDF\Facade\Pdf;
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
     *
     * Also 404s for a Paid or WrittenOff `collection_status` (CR-03) --
     * mirrors `WriteOffRequestController::store()`'s existing terminal-state
     * guard shape, so an already-closed entry can never render a
     * customer-facing demand/final-notice letter.
     */
    public function show(AccountsReceivable $accountsReceivable): Response
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);

        $accountsReceivable->loadMissing([
            'jobOrder:id,number,description,total_amount,queue_entry_id',
            'jobOrder.queueEntry.customer:id,name',
            'jobOrder.transactions:id,job_order_id,amount,status',
        ]);

        abort_if(in_array($accountsReceivable->collectionStatus(), [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true), 404);

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

    /**
     * Download the collection letter as a real PDF (D-03). The two guard
     * lines below are copied verbatim from show() (D-03's exact-reproduction
     * requirement). A third guard -- 404 on the Current bracket -- is added
     * because a PDF has no equivalent of show()'s "not past due yet" screen
     * state: AccountsReceivableAgingBracket::Current->letterBody() throws by
     * design, so a Current entry must never reach that call.
     */
    public function pdf(AccountsReceivable $accountsReceivable): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);

        $accountsReceivable->loadMissing([
            'jobOrder:id,number,description,total_amount,queue_entry_id',
            'jobOrder.queueEntry.customer:id,name',
            'jobOrder.transactions:id,job_order_id,amount,status',
        ]);

        abort_if(in_array($accountsReceivable->collectionStatus(), [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true), 404);

        $bracket = $accountsReceivable->agingBracket();
        abort_if($bracket === AccountsReceivableAgingBracket::Current, 404);

        $amountPaid = (float) $accountsReceivable->jobOrder->transactions->where('status', TransactionStatus::Completed->value)->sum('amount');
        $amountDue = $accountsReceivable->jobOrder->outstandingBalance();
        $daysPastDue = $accountsReceivable->daysPastDue();
        $dueDateLabel = $accountsReceivable->due_at?->format('F j, Y') ?? '—';

        $letterBodyText = str_replace(
            ['{due date}', '{n}'],
            [$dueDateLabel, (string) ($daysPastDue ?? 0)],
            $bracket->letterBody(),
        );

        return Pdf::loadView('reports.collection-letter', [
            'title' => 'Statement of Account',
            'jobOrderNumber' => $accountsReceivable->jobOrder->number,
            'customerName' => $accountsReceivable->jobOrder->queueEntry?->customer?->name,
            'jobOrderDescription' => $accountsReceivable->jobOrder->description,
            'creditExtended' => (float) $accountsReceivable->balance,
            'amountPaid' => $amountPaid,
            'amountDue' => $amountDue,
            'dueDateLabel' => $dueDateLabel,
            'daysPastDue' => $daysPastDue,
            'letterBodyText' => $letterBodyText,
            'today' => now()->format('F j, Y'),
        ])
            ->setOption('isPhpEnabled', true)
            ->download("collection-letter_{$accountsReceivable->jobOrder->number}.pdf");
    }
}
