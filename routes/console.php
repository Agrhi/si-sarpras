<?php

use App\Console\Commands\SendLoanReminders;
use App\Console\Commands\SendMaintenanceReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendLoanReminders::class)
    ->dailyAt('07:00')
    ->timezone(config('sarpras.timezone'))
    ->withoutOverlapping();

Schedule::command(SendMaintenanceReminders::class)
    ->dailyAt('07:00')
    ->timezone(config('sarpras.timezone'))
    ->withoutOverlapping();
