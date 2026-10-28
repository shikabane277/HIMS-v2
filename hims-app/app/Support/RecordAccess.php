<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Hospital Record Access Security Service.
 *
 * Controls privacy curtain / blur protection on sensitive employee and clinical
 * records. Allows in-place password verification to reveal confidential data.
 */
class RecordAccess
{
    /**
     * Determine if sensitive records are currently unlocked for the current session.
     */
    public static function isUnlocked(): bool
    {
        return (bool) session('record_access_unlocked', false);
    }

    /**
     * Verify the supplied admin record access password and unlock records.
     */
    public static function verify(?string $password): bool
    {
        if (empty($password)) {
            return false;
        }

        $hash = DB::table('system_settings')->where('key', 'record_access_password_hash')->value('value');
        if (! $hash) {
            $hash = Hash::make('password');
        }

        if (Hash::check($password, $hash)) {
            session([
                'record_access_unlocked' => true,
                'record_access_unlocked_at' => time(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Lock sensitive records.
     */
    public static function lock(): void
    {
        session()->forget(['record_access_unlocked', 'record_access_unlocked_at']);
    }
}
