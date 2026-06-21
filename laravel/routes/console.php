<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler configurations for production tasks
Schedule::command('communications:process-reminders')->everyMinute();
Schedule::command('communications:process-scheduled-announcements')->everyMinute();
Schedule::command('reports:process-scheduled')->everyMinute();
Schedule::command('reports:cleanup-expired-exports')->daily();
Schedule::command('gdpr:cleanup-retention')->daily();
Schedule::command('clergy:process-scheduled-transfers')->daily();

// Track scheduler health check indicator
Schedule::call(function () {
    Cache::put('scheduler_last_run', now()->timestamp);
})->everyMinute();
