<?php

use App\Http\Controllers\Owner\AuditTrailController;
use App\Http\Controllers\Owner\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
});
