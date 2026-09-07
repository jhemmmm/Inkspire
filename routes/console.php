<?php

use App\Console\Commands\SendAccountsReceivableReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendAccountsReceivableReminders::class)->daily()->withoutOverlapping()->onOneServer();
