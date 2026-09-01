<?php

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
    Route::inertia('dashboard', 'artist/Dashboard')->name('dashboard');
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
