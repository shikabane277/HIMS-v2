<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Daily credential / reassessment sweep — 06:30 by default, CREDENTIAL_SCAN_TIME.
|
| This needs a system cron entry running `php artisan schedule:run` every
| minute. Without one nothing fires — the command is still runnable by hand,
| and `--dry-run` is the safe way to see what it would raise.
|
| withoutOverlapping() matters because the scan writes alert-log rows: two
| copies racing on a slow night would double-notify before either committed.
*/
if (config('hims.credential_scan_enabled')) {
    Schedule::command('hims:scan-credential-expiry')
        ->dailyAt(config('hims.credential_scan_time', '06:30'))
        ->withoutOverlapping()
        ->onOneServer();
}

/*
| Automated HR1 and HR2 synchronization.
|
| Background schedule to pull staff clinical credentials from HR1 and competency
| assessments from HR2 hourly, avoiding manual intervention.
*/
Schedule::command('hims:sync-hr --system=all')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

/*
| GDPR Article 5(1)(e) — Automated Data Retention & Storage Limitation Purge.
*/
Schedule::command('hims:prune-expired-data')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

/*
| Automatic awards — service milestones and CPD achievement, daily 06:10.
| Each award is issued once, with its certificate and badge.
*/
Schedule::command('hims:auto-awards')
    ->dailyAt('06:10')
    ->withoutOverlapping()
    ->onOneServer();

/*
| Weekly supervisor digest — Monday 07:45, before the morning huddle.
| The command existed but nothing ever ran it.
*/
Schedule::command('hims:send-supervisor-digest')
    ->weeklyOn(1, '07:45')
    ->withoutOverlapping()
    ->onOneServer();

/*
| Nightly backup — 01:30. Only meaningful on a host with persistent storage
| (Laragon, Docker volume, VM); on Vercel use the database provider's own
| backups instead. Copy storage/app/backups off the server as well.
*/
if (config('hims.backup_schedule_enabled', true)) {
    Schedule::command('hims:backup')
        ->dailyAt('01:30')
        ->withoutOverlapping()
        ->onOneServer();
}
