<?php

use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bms:daily-checks', function () {
    $count = NotificationService::runScheduledChecks();
    $this->info("Debt alerts: {$count['debts']}, closing alerts: {$count['closing']}");
})->purpose('Raise overdue debt and daily closing reminders');

// Run with `php artisan schedule:work` (dev) or a cron entry calling `php artisan schedule:run` every minute.
Schedule::command('bms:daily-checks')->hourly();
