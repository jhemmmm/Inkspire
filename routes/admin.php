<?php

use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\CreditApprovalController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignFileController;
use App\Http\Controllers\Admin\JobOrderController;
use App\Http\Controllers\Admin\SpecificationOptionController;
use App\Http\Controllers\Admin\SystemConfigurationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WriteOffApprovalController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportExportController;
use Illuminate\Support\Facades\Route;

/**
 * One administrative portal. The Owner role used to sit above Admin here --
 * a second group carried the reports routes at `role:owner` so an Admin
 * could not reach them, and four policy methods narrowed approvals to Owner
 * alone. Owner and Admin did the same job in practice, so the role is gone
 * and Admin inherits all of it; there is nothing left for a second group to
 * separate.
 */
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('job-orders', [JobOrderController::class, 'index'])->name('job-orders.index');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
    Route::get('system-configuration', [SystemConfigurationController::class, 'edit'])->name('system-configuration.edit');
    Route::patch('system-configuration/{configuration}', [SystemConfigurationController::class, 'update'])->name('system-configuration.update');
    Route::get('specifications', [SpecificationOptionController::class, 'index'])->name('specifications.index');
    Route::post('specifications', [SpecificationOptionController::class, 'store'])->name('specifications.store');
    Route::patch('specifications/{specificationOption}', [SpecificationOptionController::class, 'update'])->name('specifications.update');
    Route::delete('specifications/{specificationOption}', [SpecificationOptionController::class, 'destroy'])->name('specifications.destroy');
    Route::get('design-overrides', [DesignFileController::class, 'index'])->name('design-overrides.index');
    Route::patch('design-files/{designFile}/unlock', [DesignFileController::class, 'unlock'])->name('design-files.unlock');
    Route::get('credit-requests', [CreditApprovalController::class, 'index'])->name('credit-requests.index');
    Route::patch('credit-requests/{accountsReceivable}/approve', [CreditApprovalController::class, 'approve'])->name('credit-requests.approve');
    Route::patch('credit-requests/{accountsReceivable}/reject', [CreditApprovalController::class, 'reject'])->name('credit-requests.reject');
    Route::get('write-off-requests', [WriteOffApprovalController::class, 'index'])->name('write-off-requests.index');
    Route::patch('accounts-receivable/{accountsReceivable}/write-off/approve', [WriteOffApprovalController::class, 'approve'])->name('write-off-requests.approve');
    Route::patch('accounts-receivable/{accountsReceivable}/write-off/reject', [WriteOffApprovalController::class, 'reject'])->name('write-off-requests.reject');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportKey}/export/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/{reportKey}/export/xlsx', [ReportExportController::class, 'exportXlsx'])->name('reports.export.xlsx');
});
