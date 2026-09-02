<?php

use App\Http\Controllers\Artist\DesignEditorController;
use App\Http\Controllers\Artist\JobOrderQueueController;
use App\Http\Controllers\Artist\JobOrderWorkspaceController;
use App\Http\Controllers\Artist\SessionStatusController;
use App\Http\Controllers\FrontlineStaff\CustomerController;
use App\Http\Controllers\FrontlineStaff\JobOrderController;
use App\Http\Controllers\FrontlineStaff\QueueEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:frontline_staff'])->prefix('frontline-staff')->name('frontline-staff.')->group(function () {
    Route::inertia('dashboard', 'frontline-staff/Dashboard')->name('dashboard');
    Route::get('new-visit', [CustomerController::class, 'index'])->name('new-visit');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::post('queue-entries', [QueueEntryController::class, 'store'])->name('queue-entries.store');
    Route::get('queue', [QueueEntryController::class, 'index'])->name('queue-entries.index');
    Route::patch('queue-entries/{queueEntry}/call-next', [QueueEntryController::class, 'callNext'])->name('queue-entries.call-next');
    Route::patch('queue-entries/{queueEntry}/mark-done', [QueueEntryController::class, 'markDone'])->name('queue-entries.mark-done');
    Route::post('queue-entries/{queueEntry}/job-orders', [QueueEntryController::class, 'addJobOrder'])->name('queue-entries.job-orders.store');
    Route::post('job-orders/{jobOrder}/replace-file', [JobOrderController::class, 'replaceFile'])->name('job-orders.replace-file');
});

Route::middleware(['auth', 'role:artist'])->prefix('artist')->name('artist.')->group(function () {
    Route::get('dashboard', [JobOrderQueueController::class, 'index'])->name('dashboard');
    Route::get('job-orders/{jobOrder}', [JobOrderWorkspaceController::class, 'show'])->name('job-orders.show');
    Route::patch('job-orders/{jobOrder}/consultation', [JobOrderWorkspaceController::class, 'updateConsultation'])->name('job-orders.consultation.update');
    Route::patch('job-orders/{jobOrder}/next', [JobOrderQueueController::class, 'next'])->name('job-orders.next');
    Route::patch('job-orders/{jobOrder}/forward', [JobOrderQueueController::class, 'forward'])->name('job-orders.forward');
    Route::patch('job-orders/{jobOrder}/not-appear', [JobOrderQueueController::class, 'notAppear'])->name('job-orders.not-appear');
    Route::patch('job-orders/{jobOrder}/design/start', [DesignEditorController::class, 'startDesign'])->name('job-orders.design.start');
    Route::post('job-orders/{jobOrder}/design/send-for-review', [DesignEditorController::class, 'sendForReview'])->name('job-orders.design.send-for-review');
    Route::patch('job-orders/{jobOrder}/design/approve', [DesignEditorController::class, 'approve'])->name('job-orders.design.approve');
    Route::patch('job-orders/{jobOrder}/design/request-changes', [DesignEditorController::class, 'requestChanges'])->name('job-orders.design.request-changes');
    Route::patch('session-status/start-break', [SessionStatusController::class, 'startBreak'])->name('session-status.start-break');
    Route::patch('session-status/end-break', [SessionStatusController::class, 'endBreak'])->name('session-status.end-break');
    Route::patch('session-status/end-shift', [SessionStatusController::class, 'endShift'])->name('session-status.end-shift');
});

Route::middleware(['auth', 'role:cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::inertia('dashboard', 'cashier/Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'role:production_staff'])->prefix('production-staff')->name('production-staff.')->group(function () {
    Route::inertia('dashboard', 'production-staff/Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'role:accounting_staff'])->prefix('accounting-staff')->name('accounting-staff.')->group(function () {
    Route::inertia('dashboard', 'accounting-staff/Dashboard')->name('dashboard');
});
