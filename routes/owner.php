<?php

use App\Http\Controllers\Owner\AuditTrailController;
use App\Http\Controllers\Owner\SystemConfigurationController;
use App\Http\Controllers\Owner\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
    Route::get('system-configuration', [SystemConfigurationController::class, 'edit'])->name('system-configuration.edit');
    Route::patch('system-configuration/{configuration}', [SystemConfigurationController::class, 'update'])->name('system-configuration.update');
});
