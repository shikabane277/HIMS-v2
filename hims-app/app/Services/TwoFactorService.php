<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TwoFactorService
{
    public const TRUSTED_DEVICE_COOKIE = 'hims_trusted_device';
    public const TRUSTED_DEVICE_DAYS = 30;
    /**
     * Generate a 6-character code guaranteed to contain both uppercase
     * and lowercase characters (case-sensitive).
     */
    public static function generateCode(): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $uppercase.$lowercase.$digits;

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
            $maskedName = substr($name, 0, 1).'***';
        } else {
            $maskedName = substr($name, 0, 1).str_repeat('*', min(4, $length - 2)).substr($name, -1);
        }

        return $maskedName.'@'.$domain;
    }

    /**
     * Check if the incoming request is from a remembered/trusted device for the user.
     */
    public static function isDeviceTrusted(Request $request, User $user): bool
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (! $token || ! is_string($token) || strlen($token) < 32) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $trusted = DB::table('user_trusted_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($trusted) {
            DB::table('user_trusted_devices')
                ->where('id', $trusted->id)
                ->update([
                    'last_used_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        }

        return false;
    }

    /**
     * Issue a 30-day trusted device token and return the secure Cookie.
     */
    public static function trustDevice(Request $request, User $user): Cookie
    {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);
        $expiresAt = now()->addDays(self::TRUSTED_DEVICE_DAYS);

        DB::table('user_trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return cookie(
            self::TRUSTED_DEVICE_COOKIE,
            $token,
            self::TRUSTED_DEVICE_DAYS * 24 * 60, // 30 days in minutes
            '/',
            null,
            $request->isSecure(),
            true, // httpOnly
            false,
            'lax'
        );
    }

    /**
     * Invalidate trusted device cookie and remove matching record from database.
     */
    public static function forgetDevice(Request $request, ?User $user = null): Cookie
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if ($token && is_string($token)) {
            $query = DB::table('user_trusted_devices')->where('token_hash', hash('sha256', $token));
            if ($user) {
                $query->where('user_id', $user->id);
            }
            $query->delete();
        }

        return cookie()->forget(self::TRUSTED_DEVICE_COOKIE);
    }
}

