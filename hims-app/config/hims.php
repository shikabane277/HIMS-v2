<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credential alert email
    |--------------------------------------------------------------------------
    |
    | In-app notifications and the credential_alert_log are always written by
    | hims:scan-credential-expiry. Email is opt-in on top of that, because a
    | deployment running MAIL_MAILER=log would otherwise silently "send"
    | expiry warnings nobody receives.
    |
    | Turn on only once `php artisan hims:mail-test <address>` delivers.
    |
    */

    'credential_alert_email' => env('CREDENTIAL_ALERT_EMAIL', false),

    /*
    |--------------------------------------------------------------------------
    | Nightly credential scan
    |--------------------------------------------------------------------------
    |
    | Local time of the daily sweep, and the master switch for it. Scheduling
    | requires a system cron entry running `php artisan schedule:run` every
    | minute; without that the command still works when run by hand.
    |
    */

    'credential_scan_enabled' => env('CREDENTIAL_SCAN_ENABLED', true),

    'credential_scan_time' => env('CREDENTIAL_SCAN_TIME', '06:30'),

];
