<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TwoFactorService
{
    /**
     * Generate a 6-character code guaranteed to contain both uppercase
     * and lowercase characters (case-sensitive).
     */
    public static function generateCode(): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $uppercase . $lowercase . $digits;

        do {
            // Guarantee at least 1 uppercase, 1 lowercase, and 1 digit
            $chars = [
                $uppercase[random_int(0, strlen($uppercase) - 1)],
                $lowercase[random_int(0, strlen($lowercase) - 1)],
                $digits[random_int(0, strlen($digits) - 1)],
            ];

            // Fill remaining 3 characters from combined set
            for ($i = 0; $i < 3; $i++) {
                $chars[] = $all[random_int(0, strlen($all) - 1)];
            }

            // Cryptographically shuffle the array
            for ($i = count($chars) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                $tmp = $chars[$i];
                $chars[$i] = $chars[$j];
                $chars[$j] = $tmp;
            }

            $code = implode('', $chars);
        } while (! preg_match('/[A-Z]/', $code) || ! preg_match('/[a-z]/', $code));

        return $code;
    }

    /**
     * Send the 6-character two-factor code to the user via email.
     */
    public static function sendCode(User $user, string $code): bool
    {
        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail($code, $user->name ?? 'User'));
            return true;
        } catch (\Throwable $e) {
            Log::error('Two-factor authentication email delivery failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'exception' => $e->getMessage(),
            ]);

            if (app()->environment('local')) {
                session()->flash(
                    'dev_code_notice',
                    "Notice: Email delivery failed ({$e->getMessage()}). For local testing, your verification code is: {$code}"
                );
            }

            return false;
        }
    }

    /**
     * Mask an email address for privacy on the 2FA challenge screen.
     * e.g., user@example.com -> u***r@example.com
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $length = strlen($name);
        if ($length <= 2) {
            $maskedName = substr($name, 0, 1) . '***';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $length - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
