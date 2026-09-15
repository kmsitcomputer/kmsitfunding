<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// IMP-005 Q32 — Laravel Scheduler + Cron, shared-hosting compatible (no
// Redis/Supervisor/PM2/WebSocket/separate worker). withoutOverlapping()
// is a courtesy only: section 10's own locking already makes overlapping
// runs safe by construction (each identity serializes on its own row
// lock), this just avoids piling up redundant processes.
Schedule::command('content:run-scheduled-transitions')->everyMinute()->withoutOverlapping();
