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

// IMP-005 section 19 — cleanup is bounded/operational, not time-critical
// like publication; hourly is a defensible cadence against the default
// 24h orphan grace / 7-day purge grace windows (config/media.php).
Schedule::command('content:cleanup-media')->hourly()->withoutOverlapping();

// IMP-008 — Donation expiration sweep (HD-IMP008-04, config-gated: no-op
// while donation.pending_expiry_minutes is null). Laravel Scheduler +
// Cron, shared-hosting compatible — no Redis/Supervisor/PM2/WebSocket/
// separate worker. withoutOverlapping() is a courtesy only: the command's
// own per-row locking already makes overlapping runs safe by construction
// (each identity serializes on its own row lock). The recurring occurrence
// generation SCHEDULING/EXECUTION engine is explicitly deferred (spec
// "Out of Scope") — no generator is scheduled here.
Schedule::command('donation:expire-pending')->everyMinute()->withoutOverlapping();

// IMP-009 — Payment expiration sweep (HD-IMP009-09, config-gated: no-op
// for fallback-window payments while payment.pending_expiry_minutes is
// null; provider-supplied expires_at windows always apply). Laravel
// Scheduler + Cron, shared-hosting compatible. withoutOverlapping() is
// a courtesy only: the command's own per-row locking already makes
// overlapping runs safe by construction.
Schedule::command('payment:expire-pending')->everyMinute()->withoutOverlapping();
