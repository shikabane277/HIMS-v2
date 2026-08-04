<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        /*
        |----------------------------------------------------------------------
        | A note on 'timeout' — do not set it back to null
        |----------------------------------------------------------------------
        | MailManager only forwards this to the socket when isset() is true, and
        | isset(null) is false. Left null, the connection inherits PHP's
        | default_socket_timeout (60s), which on a host that firewalls outbound
        | SMTP is longer than max_execution_time: PHP hits its own limit while
        | still blocked in stream_socket_client() and dies with a FatalError, so
        | Symfony never raises the TransportException that
        | PasswordResetLinkController is there to catch. The visitor gets a crash
        | page instead of the friendly message.
        |
        | An explicit timeout shorter than max_execution_time keeps the failure
        | inside the framework, where it is handled and logged.
        */

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) (env('MAIL_TIMEOUT') ?: 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
        |----------------------------------------------------------------------
        | Consumer webmail presets
        |----------------------------------------------------------------------
        | Host, port and scheme are baked in so switching provider is a single
        | change of MAIL_MAILER — only MAIL_USERNAME and MAIL_PASSWORD differ.
        |
        | All three reject a normal account password over SMTP. Each needs an
        | app-specific password generated from the account's security settings,
        | and each requires MAIL_FROM_ADDRESS to be the same mailbox that is
        | authenticating, or the message is rejected or filed as spam.
        |
        | All three are also SMTP on port 587, which many PaaS hosts (Railway,
        | Render, Heroku, Vercel, Fly) firewall on lower plans to protect their
        | IP reputation. Where that is the case these presets cannot work at any
        | timeout — the platform drops the connection — and an HTTPS API
        | transport such as Resend, Brevo or Postmark is the only option.
        */

        'gmail' => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) (env('MAIL_TIMEOUT') ?: 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'outlook' => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => env('MAIL_OUTLOOK_HOST', 'smtp-mail.outlook.com'),
            'port' => 587,
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) (env('MAIL_TIMEOUT') ?: 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'yahoo' => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) (env('MAIL_TIMEOUT') ?: 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        /*
        |----------------------------------------------------------------------
        | Brevo (Sendinblue) — HTTPS API transport (works on Railway)
        |----------------------------------------------------------------------
        | Brevo sends via HTTPS (api.brevo.com on port 443), not SMTP, so it
        | bypasses the port blocks that Railway imposes on non-Pro plans.
        |
        | Single Sender advantage: Brevo allows verifying a single personal or
        | work email address (e.g. yourname@gmail.com) via an inbox link,
        | without requiring full DNS domain verification.
        |
        | Setup: composer require symfony/brevo-mailer (installed)
        |        MAIL_MAILER=brevo
        |        BREVO_API_KEY=xkeysib-xxxxxxxxx
        |        MAIL_FROM_ADDRESS=verified-single-sender@example.com
        */

        'brevo' => [
            'transport' => 'brevo',
            'key' => env('BREVO_API_KEY'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    /*
    | The "or null" fallbacks matter: env() returns an empty string for a key
    | that is present but blank (MAIL_FROM_ADDRESS=), and an empty string is not
    | a missing value, so the second argument to env() would never apply. That
    | left the From header empty and made every send fail with "An email must
    | have a From or Sender header". Falling back to MAIL_USERNAME keeps the
    | sender aligned with the authenticated mailbox, which is what Gmail,
    | Outlook and Yahoo require anyway.
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS') ?: (env('MAIL_USERNAME') ?: 'no-reply@hospital.ph'),
        'name' => env('MAIL_FROM_NAME') ?: env('APP_NAME', 'HIMS'),
    ],

];
