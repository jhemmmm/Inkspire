<?php

use App\Http\Controllers\AccountingStaff\AccountsReceivableController;
use App\Http\Controllers\AccountingStaff\CollectionLetterController;
use App\Http\Controllers\AccountingStaff\CollectionStatusController;
use App\Http\Controllers\AccountingStaff\ExpenseController;
use App\Http\Controllers\AccountingStaff\WriteOffRequestController;
use App\Http\Controllers\Artist\DesignEditorController;
use App\Http\Controllers\Artist\JobOrderQueueController;
use App\Http\Controllers\Artist\JobOrderWorkspaceController;
use App\Http\Controllers\Artist\PerformanceReportController;
use App\Http\Controllers\Artist\SessionStatusController;
use App\Http\Controllers\Cashier\CancellationController;
use App\Http\Controllers\Cashier\CreditRequestController;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboardController;
use App\Http\Controllers\Cashier\PaymentController;
use App\Http\Controllers\Cashier\ReceiptController;
use App\Http\Controllers\Cashier\ReconciliationController;
use App\Http\Controllers\FrontlineStaff\CustomerController;
use App\Http\Controllers\FrontlineStaff\DashboardController;
use App\Http\Controllers\FrontlineStaff\JobOrderController;
use App\Http\Controllers\FrontlineStaff\JobOrderReleaseController;
use App\Http\Controllers\FrontlineStaff\QueueEntryController;
use App\Http\Controllers\ProductionStaff\ProductionBoardController;
use App\Http\Controllers\ProductionStaff\ProductionStageController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:frontline_staff'])->prefix('frontline-staff')->name('frontline-staff.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('new-visit', [CustomerController::class, 'index'])->name('new-visit');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::post('queue-entries', [QueueEntryController::class, 'store'])->name('queue-entries.store');
    Route::get('queue', [QueueEntryController::class, 'index'])->name('queue-entries.index');
    Route::patch('queue-entries/{queueEntry}/call-next', [QueueEntryController::class, 'callNext'])->name('queue-entries.call-next');
    Route::patch('queue-entries/{queueEntry}/mark-done', [QueueEntryController::class, 'markDone'])->name('queue-entries.mark-done');
    Route::post('queue-entries/{queueEntry}/job-orders', [QueueEntryController::class, 'addJobOrder'])->name('queue-entries.job-orders.store');
    Route::post('job-orders/{jobOrder}/replace-file', [JobOrderController::class, 'replaceFile'])->name('job-orders.replace-file');
    Route::post('job-orders/{jobOrder}/release', [JobOrderReleaseController::class, 'store'])->name('job-orders.release');
});

Route::middleware(['auth', 'role:artist'])->prefix('artist')->name('artist.')->group(function () {
    Route::get('dashboard', [JobOrderQueueController::class, 'index'])->name('dashboard');
    Route::get('job-orders/{jobOrder}', [JobOrderWorkspaceController::class, 'show'])->name('job-orders.show');
    Route::patch('job-orders/{jobOrder}/consultation', [JobOrderWorkspaceController::class, 'updateConsultation'])->name('job-orders.consultation.update');
    Route::patch('job-orders/{jobOrder}/accept', [JobOrderQueueController::class, 'accept'])->name('job-orders.accept');
    Route::patch('job-orders/{jobOrder}/next', [JobOrderQueueController::class, 'next'])->name('job-orders.next');
    Route::patch('job-orders/{jobOrder}/forward', [JobOrderQueueController::class, 'forward'])->name('job-orders.forward');
    Route::patch('job-orders/{jobOrder}/design/start', [DesignEditorController::class, 'startDesign'])->name('job-orders.design.start');
    Route::post('job-orders/{jobOrder}/design/send-for-review', [DesignEditorController::class, 'sendForReview'])->name('job-orders.design.send-for-review');
    Route::patch('job-orders/{jobOrder}/design/approve', [DesignEditorController::class, 'approve'])->name('job-orders.design.approve');
    Route::patch('job-orders/{jobOrder}/design/request-changes', [DesignEditorController::class, 'requestChanges'])->name('job-orders.design.request-changes');
    Route::patch('session-status/start-shift', [SessionStatusController::class, 'startShift'])->name('session-status.start-shift');
    Route::patch('session-status/start-break', [SessionStatusController::class, 'startBreak'])->name('session-status.start-break');
    Route::patch('session-status/end-break', [SessionStatusController::class, 'endBreak'])->name('session-status.end-break');
    Route::patch('session-status/end-shift', [SessionStatusController::class, 'endShift'])->name('session-status.end-shift');
    Route::get('performance-report', [PerformanceReportController::class, 'index'])->name('performance-report.index');
});

Route::middleware(['auth', 'role:cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');
    Route::get('job-orders/{jobOrder}/payment', [PaymentController::class, 'edit'])->name('job-orders.payment.edit');
    Route::post('job-orders/{jobOrder}/payment', [PaymentController::class, 'store'])->name('job-orders.payment.store');
    Route::get('job-orders/{jobOrder}/receipt', [ReceiptController::class, 'show'])->name('job-orders.receipt.show');
    Route::post('job-orders/{jobOrder}/reconcile', [ReconciliationController::class, 'store'])->name('job-orders.reconcile');
    Route::post('job-orders/{jobOrder}/cancel', [CancellationController::class, 'store'])->name('job-orders.cancel');
    Route::post('job-orders/{jobOrder}/credit-request', [CreditRequestController::class, 'store'])->name('job-orders.credit-request.store');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportKey}/export/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/{reportKey}/export/xlsx', [ReportExportController::class, 'exportXlsx'])->name('reports.export.xlsx');
});

Route::middleware(['auth', 'role:production_staff'])->prefix('production-staff')->name('production-staff.')->group(function () {
    Route::get('dashboard', [ProductionBoardController::class, 'index'])->name('dashboard');
    Route::patch('job-orders/{jobOrder}/advance', [ProductionStageController::class, 'advance'])->name('job-orders.advance');
    Route::patch('job-orders/{jobOrder}/send-back', [ProductionStageController::class, 'sendBack'])->name('job-orders.send-back');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportKey}/export/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/{reportKey}/export/xlsx', [ReportExportController::class, 'exportXlsx'])->name('reports.export.xlsx');
});

Route::middleware(['auth', 'role:accounting_staff'])->prefix('accounting-staff')->name('accounting-staff.')->group(function () {
    Route::get('dashboard', [ReconciliationController::class, 'index'])->name('dashboard');
    Route::get('accounts-receivable', [AccountsReceivableController::class, 'index'])->name('accounts-receivable.index');
    Route::get('accounts-receivable/{accountsReceivable}', [AccountsReceivableController::class, 'show'])->name('accounts-receivable.show');
    Route::patch('accounts-receivable/{accountsReceivable}/collection-status', [CollectionStatusController::class, 'update'])->name('accounts-receivable.collection-status.update');
    Route::get('accounts-receivable/{accountsReceivable}/collection-letter', [CollectionLetterController::class, 'show'])->name('accounts-receivable.collection-letter.show');
    Route::get('accounts-receivable/{accountsReceivable}/collection-letter/pdf', [CollectionLetterController::class, 'pdf'])->name('accounts-receivable.collection-letter.pdf');
    Route::post('accounts-receivable/{accountsReceivable}/write-off', [WriteOffRequestController::class, 'store'])->name('accounts-receivable.write-off.store');
    Route::post('job-orders/{jobOrder}/reconcile', [ReconciliationController::class, 'store'])->name('job-orders.reconcile');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::patch('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportKey}/export/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/{reportKey}/export/xlsx', [ReportExportController::class, 'exportXlsx'])->name('reports.export.xlsx');
});
