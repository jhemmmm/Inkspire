<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:frontline_staff'])->prefix('frontline-staff')->name('frontline-staff.')->group(function () {
    Route::inertia('dashboard', 'frontline-staff/Dashboard')->name('dashboard');
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
