<?php

use App\Services\BackupService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bms:daily-checks', function () {
    $count = NotificationService::runScheduledChecks();
    $this->info("Debt alerts: {$count['debts']}, closing alerts: {$count['closing']}, SMS reminders: {$count['sms']}");
})->purpose('Raise overdue debt and daily closing reminders');

Artisan::command('bms:backup', function (BackupService $backups) {
    $file = $backups->run();
    $this->info('Backup saved: '.$file.' ('.round(filesize($file) / 1024).' KB)');
})->purpose('Back up the database to storage/app/backups (keeps the latest BACKUP_KEEP files)');

// Run with `php artisan schedule:work` (dev) or a cron entry calling `php artisan schedule:run` every minute.
Schedule::command('bms:daily-checks')->hourly();
Schedule::command('bms:backup')->dailyAt('23:30');
