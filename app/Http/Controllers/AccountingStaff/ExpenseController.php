<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingStaff\FilterExpensesRequest;
use App\Http\Requests\AccountingStaff\StoreExpenseRequest;
use App\Http\Requests\AccountingStaff\UpdateExpenseRequest;
use App\Http\Requests\AccountingStaff\VoidExpenseRequest;
use App\Models\Expense;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Show Accounting Staff's expense ledger (EXP-01) -- defaults to This
     * Month when no range is supplied. The row list includes voided rows
     * (they stay visible per D-11), but `total` is a separate,
     * server-computed figure summing only non-voided rows via
     * Expense::active(), never derived client-side from the row array.
     */
    public function index(FilterExpensesRequest $request): Response
    {
        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->date('from')->startOfDay();
            $to = $request->date('to')->endOfDay();
        } else {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        }

        $rows = Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->with('recordedBy:id,name')
            ->orderByDesc('expense_date')
            ->get(['id', 'expense_date', 'category', 'description', 'amount', 'recorded_by', 'voided_at', 'void_reason']);

        return Inertia::render('accounting-staff/Expenses/Index', [
            'rows' => $rows->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->toDateString(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'recorded_by' => $expense->recordedBy->name,
                'voided_at' => $expense->voided_at,
                'void_reason' => $expense->void_reason,
            ]),
            'total' => (float) Expense::query()->whereBetween('expense_date', [$from, $to])->active()->sum('amount'),
            'activeCount' => Expense::query()->whereBetween('expense_date', [$from, $to])->active()->count(),
            'voidedCount' => Expense::query()->whereBetween('expense_date', [$from, $to])->whereNotNull('voided_at')->count(),
            'categories' => SystemConfiguration::getArray('expense_categories', ['Utilities', 'Supplies', 'Rent']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * Record a new expense (EXP-01). `recorded_by` is always set server-side
     * from the acting user, never from client input -- Expense's #[Fillable]
     * list omits it entirely as a second layer of defense.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create($request->validated() + ['recorded_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense recorded.')]);

        return back();
    }

    /**
     * Edit a non-voided expense's fields (D-11). Editing a voided expense is
     * impossible -- the guard below matches WriteOffRequestController's
     * closed-state precondition shape.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        abort_if($expense->voided_at !== null, 422, __("This expense was voided and can't be edited."));

        $expense->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return back();
    }

    /**
     * Void an expense with a mandatory reason (D-11). The row survives and
     * stays visible, but is excluded from every sum via Expense::active().
     * This forceFill()->save() is still an `updated` event, so
     * AuditObserver captures it automatically -- no extra audit call.
     */
    public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        abort_if($expense->voided_at !== null, 422, __('This expense has already been voided.'));

        $expense->forceFill([
            'voided_at' => now(),
            'void_reason' => $request->validated('reason'),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense voided. It no longer counts toward reports.')]);

        return back();
    }
}
