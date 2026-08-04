<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Verify that this deployment can actually deliver email.
 *
 * "Forgot password?" depends entirely on the mail transport, and the most
 * common failure is silent: MAIL_MAILER=log accepts every message and writes
 * it to storage/logs/laravel.log, so the app reports success while the user
 * never receives anything. This command makes that state obvious and sends a
 * real message once a transport is configured.
 *
 * Supports both SMTP presets (gmail/outlook/yahoo/smtp) and HTTPS API
 * transports (brevo/postmark/ses).
 */
class MailTest extends Command
{
    protected $signature = 'hims:mail-test {email : Address to send the test message to}';

    protected $description = 'Send a test email to verify the configured mail transport';

    /** SMTP-based mailers whose credentials come from MAIL_USERNAME/MAIL_PASSWORD. */
    private const SMTP_MAILERS = ['smtp', 'gmail', 'outlook', 'yahoo'];

    /** API-based mailers that send over HTTPS (no SMTP port needed). */
    private const API_MAILERS = ['brevo', 'postmark', 'ses'];

    /** Consumer SMTP presets that require MAIL_FROM_ADDRESS == MAIL_USERNAME. */
    private const WEBMAIL_PRESETS = ['gmail', 'outlook', 'yahoo'];

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        $mailer = (string) config('mail.default');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("\"{$to}\" is not a valid email address.");

            return self::FAILURE;
        }

        $cfg = config("mail.mailers.{$mailer}", []);
        $from = (string) config('mail.from.address');

        $this->line('');

        if (in_array($mailer, self::API_MAILERS, true)) {
            return $this->handleApiMailer($mailer, $cfg, $from, $to);
        }

        return $this->handleSmtpMailer($mailer, $cfg, $from, $to);
    }

    /**
     * Diagnose and send via an SMTP-based mailer (gmail/outlook/yahoo/smtp).
     */
    private function handleSmtpMailer(string $mailer, array $cfg, string $from, string $to): int
    {
        $username = (string) ($cfg['username'] ?? '');

        $this->line('  Mailer .......... '.$mailer);
        $this->line('  Host ............ '.($cfg['host'] ?? '—'));
        $this->line('  Port ............ '.($cfg['port'] ?? '—'));
        $this->line('  Username ........ '.($username !== '' ? $username : 'NOT SET'));
        $this->line('  Password ........ '.(($cfg['password'] ?? '') !== '' ? 'set' : 'NOT SET'));
        $this->line('  From ............ '.($from !== '' ? $from : '—'));
        $this->line('');

        // Consumer webmail authenticates as one mailbox and refuses to send as
        // another, so a mismatch here fails at the provider, not in our code.
        if (in_array($mailer, self::WEBMAIL_PRESETS, true)
            && $username !== '' && $from !== ''
            && strcasecmp($username, $from) !== 0) {
            $this->warn("MAIL_FROM_ADDRESS ({$from}) does not match MAIL_USERNAME ({$username}).");
            $this->warn(ucfirst($mailer).' will reject or spam-file mail sent as a different address.');
            $this->line('  Set MAIL_FROM_ADDRESS="'.$username.'" in .env, then run php artisan config:clear');
            $this->line('');
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is \"{$mailer}\", so nothing will be delivered.");
            $this->warn('Password reset emails are written to storage/logs/laravel.log and never sent.');
            $this->line('');
            $this->line('  Set MAIL_MAILER to gmail, outlook, yahoo, or brevo in .env,');
            $this->line('  then run: php artisan config:clear');
            $this->line('');

            return self::FAILURE;
        }

        // Fail loudly on empty credentials rather than letting the transport
        // report a misleading "missing From header" further downstream.
        $missing = [];
        if ($username === '') {
            $missing[] = 'MAIL_USERNAME';
        }
        if (($cfg['password'] ?? '') === '') {
            $missing[] = 'MAIL_PASSWORD';
        }
        if ($from === '') {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($missing !== []) {
            $this->error('Not configured yet — missing: '.implode(', ', $missing));
            $this->line('');
            $this->line('  Add these to hims-app/.env:');
            $this->line('');
            $this->line('    MAIL_MAILER='.$mailer);
            $this->line('    MAIL_USERNAME=you@'.($mailer === 'gmail' ? 'gmail.com' : ($mailer === 'yahoo' ? 'yahoo.com' : 'outlook.com')));
            $this->line('    MAIL_PASSWORD=your-app-password');
            $this->line('    MAIL_FROM_ADDRESS=you@'.($mailer === 'gmail' ? 'gmail.com' : ($mailer === 'yahoo' ? 'yahoo.com' : 'outlook.com')));
            $this->line('');
            $this->line('  MAIL_PASSWORD must be an app password, not your account password:');
            $this->line('    Gmail    https://myaccount.google.com/apppasswords');
            $this->line('    Outlook  account.microsoft.com/security → App passwords');
            $this->line('    Yahoo    login.yahoo.com/account/security');
            $this->line('');
            $this->line('  Then run: php artisan config:clear');
            $this->line('');

            return self::FAILURE;
        }

        return $this->send($mailer, $to);
    }

    /**
     * Diagnose and send via an HTTPS API mailer (brevo/postmark/ses).
     */
    private function handleApiMailer(string $mailer, array $cfg, string $from, string $to): int
    {
        $apiKey = match ($mailer) {
            'brevo'    => (string) ($cfg['key'] ?? config('services.brevo.key')),
            'postmark' => (string) config('services.postmark.key'),
            'ses'      => (string) config('services.ses.key'),
            default    => '',
        };

        $keyEnvName = match ($mailer) {
            'brevo'    => 'BREVO_API_KEY',
            'postmark' => 'POSTMARK_API_KEY',
            'ses'      => 'AWS_ACCESS_KEY_ID',
            default    => '(unknown)',
        };

        $keyIsSet = $apiKey !== '';

        $this->line('  Mailer .......... '.$mailer.' (HTTPS API transport)');
        $this->line('  API key ......... '.($keyIsSet ? 'set ('.strlen($apiKey).' chars)' : 'NOT SET'));
        $this->line('  From ............ '.($from !== '' ? $from : '—'));
        $this->line('');

        if ($mailer === 'brevo') {
            $this->line('  ℹ  Brevo sends via HTTPS (api.brevo.com:443) — Railway SMTP port blocks do not apply.');
            $this->line('');
        }

        $missing = [];

        if (! $keyIsSet) {
            $missing[] = $keyEnvName;
        }
        if ($from === '') {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($missing !== []) {
            $this->error('Not configured yet — missing: '.implode(', ', $missing));
            $this->line('');

            if ($mailer === 'brevo') {
                $this->line('  Add these to hims-app/.env (or the Railway dashboard):');
                $this->line('');
                $this->line('    MAIL_MAILER=brevo');
                $this->line('    BREVO_API_KEY=xkeysib-xxxxxxxxx');
                $this->line('    MAIL_FROM_ADDRESS=verified-single-sender@example.com');
                $this->line('');
                $this->line('  Get your API key at https://app.brevo.com/settings/keys/api');
                $this->line('  Verify your sender email at https://app.brevo.com/senders');
            } else {
                $this->line('  Set '.$keyEnvName.' and MAIL_FROM_ADDRESS in .env.');
            }

            $this->line('');
            $this->line('  Then run: php artisan config:clear');
            $this->line('');

            return self::FAILURE;
        }

        return $this->send($mailer, $to);
    }

    /**
     * Actually send the test message and report the result.
     */
    private function send(string $mailer, string $to): int
    {
        try {
            Mail::raw(
                "This is a test message from HIMS.\n\n"
                .'If you received it, the mail transport is configured correctly '
                .'and password reset emails will be delivered.',
                fn ($message) => $message->to($to)->subject('HIMS mail configuration test')
            );
        } catch (\Throwable $e) {
            $this->line('');
            $this->error('Delivery failed: '.$e->getMessage());
            $this->line('');

            if (in_array($mailer, self::SMTP_MAILERS, true)) {
                $this->line('  Common causes:');
                $this->line('   • Wrong app password — all three providers reject your normal');
                $this->line('     account password over SMTP and need an app-specific one:');
                $this->line('       Gmail    myaccount.google.com/apppasswords  (needs 2FA on)');
                $this->line('       Outlook  account.microsoft.com/security → App passwords');
                $this->line('       Yahoo    login.yahoo.com/account/security → Generate app password');
                $this->line('   • MAIL_FROM_ADDRESS is not the mailbox you authenticated as');
                $this->line('   • Port 587 blocked by your network or PaaS host (Railway, Render)');
                $this->line('     → switch to MAIL_MAILER=brevo (HTTPS, no port block)');
                $this->line('   • Stale config cache (run php artisan config:clear)');
            } elseif ($mailer === 'brevo') {
                $this->line('  Common causes:');
                $this->line('   • Invalid or revoked BREVO_API_KEY → check at https://app.brevo.com/settings/keys/api');
                $this->line('   • MAIL_FROM_ADDRESS has not been verified in Brevo → add at https://app.brevo.com/senders');
                $this->line('   • Stale config cache (run php artisan config:clear)');
            }

            $this->line('');

            return self::FAILURE;
        }

        $this->info("Test message sent to {$to}.");
        $this->line('Check the inbox (and the spam folder) to confirm delivery.');

        return self::SUCCESS;
    }
}
