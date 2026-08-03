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
 * real message once SMTP is configured.
 */
class MailTest extends Command
{
    protected $signature = 'hims:mail-test {email : Address to send the test message to}';

    protected $description = 'Send a test email to verify the configured mail transport';

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        $mailer = (string) config('mail.default');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("\"{$to}\" is not a valid email address.");

            return self::FAILURE;
        }

        // Read the mailer actually in use, not a hardcoded "smtp" block, so the
        // gmail/outlook/yahoo presets report their real host and credentials.
        $cfg = config("mail.mailers.{$mailer}", []);
        $username = (string) ($cfg['username'] ?? '');
        $from = (string) config('mail.from.address');

        $this->line('');
        $this->line('  Mailer .......... '.$mailer);
        $this->line('  Host ............ '.($cfg['host'] ?? '—'));
        $this->line('  Port ............ '.($cfg['port'] ?? '—'));
        $this->line('  Username ........ '.($username !== '' ? $username : 'NOT SET'));
        $this->line('  Password ........ '.(($cfg['password'] ?? '') !== '' ? 'set' : 'NOT SET'));
        $this->line('  From ............ '.($from !== '' ? $from : '—'));
        $this->line('');

        // Consumer webmail authenticates as one mailbox and refuses to send as
        // another, so a mismatch here fails at the provider, not in our code.
        if (in_array($mailer, ['gmail', 'outlook', 'yahoo'], true)
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
            $this->line('  Set MAIL_MAILER to gmail, outlook, or yahoo in .env,');
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
            $this->line('  Common causes:');
            $this->line('   • Wrong app password — all three providers reject your normal');
            $this->line('     account password over SMTP and need an app-specific one:');
            $this->line('       Gmail    myaccount.google.com/apppasswords  (needs 2FA on)');
            $this->line('       Outlook  account.microsoft.com/security → App passwords');
            $this->line('       Yahoo    login.yahoo.com/account/security → Generate app password');
            $this->line('   • MAIL_FROM_ADDRESS is not the mailbox you authenticated as');
            $this->line('   • Port 587 blocked by your network, or a stale config cache');
            $this->line('     (run php artisan config:clear)');
            $this->line('');

            return self::FAILURE;
        }

        $this->info("Test message sent to {$to}.");
        $this->line('Check the inbox (and the spam folder) to confirm delivery.');

        return self::SUCCESS;
    }
}
