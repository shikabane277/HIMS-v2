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
