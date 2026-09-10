<?php

use App\Http\Controllers\Owner\AuditTrailController;
use App\Http\Controllers\Owner\CreditApprovalController;
use App\Http\Controllers\Owner\DesignFileController;
use App\Http\Controllers\Owner\SystemConfigurationController;
use App\Http\Controllers\Owner\UserManagementController;
use App\Http\Controllers\Owner\WriteOffApprovalController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
    Route::get('system-configuration', [SystemConfigurationController::class, 'edit'])->name('system-configuration.edit');
    Route::patch('system-configuration/{configuration}', [SystemConfigurationController::class, 'update'])->name('system-configuration.update');
    Route::get('design-overrides', [DesignFileController::class, 'index'])->name('design-overrides.index');
    Route::patch('design-files/{designFile}/unlock', [DesignFileController::class, 'unlock'])->name('design-files.unlock');
    Route::get('credit-requests', [CreditApprovalController::class, 'index'])->name('credit-requests.index');
    Route::patch('credit-requests/{accountsReceivable}/approve', [CreditApprovalController::class, 'approve'])->name('credit-requests.approve');
    Route::patch('credit-requests/{accountsReceivable}/reject', [CreditApprovalController::class, 'reject'])->name('credit-requests.reject');
    Route::get('write-off-requests', [WriteOffApprovalController::class, 'index'])->name('write-off-requests.index');
    Route::patch('accounts-receivable/{accountsReceivable}/write-off/approve', [WriteOffApprovalController::class, 'approve'])->name('write-off-requests.approve');
    Route::patch('accounts-receivable/{accountsReceivable}/write-off/reject', [WriteOffApprovalController::class, 'reject'])->name('write-off-requests.reject');
});

// A SECOND, owner-only group -- never role:owner,admin. D-05: Admin sees no
// reports. Placing this route inside the group above would let an Admin
// through with a 200 (Pitfall 2/T-08-09).
Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});
