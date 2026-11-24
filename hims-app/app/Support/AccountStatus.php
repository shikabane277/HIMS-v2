<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One place that decides what a login account's state is, so the sign-in
 * page, the user list, the employee directory and the employee profile all
 * say the same thing.
 *
 *   deactivated — is_active = false (by an admin, or automatically because
 *                 the employee resigned / was terminated / suspended)
 *   locked      — too many failed sign-ins; locked_until is in the future
 *   active      — can sign in
 *   none        — the employee has no login account
 */
class AccountStatus
{
    /** Employment statuses that must not keep a working login. */
    public const BLOCKING_EMPLOYMENT = ['terminated', 'resigned', 'suspended'];

    public static function of(?object $user): array
    {
        if (! $user) {
            return ['key' => 'none', 'label' => 'No login account', 'badge' => 'gray', 'icon' => 'bi-person-dash', 'detail' => null];
        }

        $active = (bool) ($user->is_active ?? true);
        $lockedUntil = ! empty($user->locked_until) ? Carbon::parse($user->locked_until) : null;

        $expires = ! empty($user->access_expires_at) ? Carbon::parse($user->access_expires_at) : null;

        if ($active && $expires && $expires->isPast()) {
            return ['key' => 'expired', 'label' => 'Access ended', 'badge' => 'gray', 'icon' => 'bi-calendar-x', 'detail' => 'Access ended on '.$expires->format('M j, Y').'. Extend the end date to let them sign in again.'];
        }

        if (! $active) {
            return ['key' => 'deactivated', 'label' => 'Deactivated', 'badge' => 'red', 'icon' => 'bi-person-x', 'detail' => 'Cannot sign in. An administrator can reactivate the account.'];
        }

        if ($lockedUntil && $lockedUntil->isFuture()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($lockedUntil) / 60));

            return ['key' => 'locked', 'label' => 'Locked', 'badge' => 'yellow', 'icon' => 'bi-lock-fill', 'detail' => "Locked after repeated failed sign-ins — unlocks automatically in {$minutes} min, or an administrator can unlock it now.", 'minutes' => $minutes];
        }

        return ['key' => 'active', 'label' => 'Active', 'badge' => 'green', 'icon' => 'bi-person-check',
            'detail' => $expires ? 'Access until '.$expires->format('M j, Y').'.' : null];
    }

    /**
     * Turn off every login linked to an employee and revoke remembered
     * devices, so the change takes effect on the very next request.
     */
    public static function deactivateForEmployee(string $employeeId, string $reason): int
    {
        $userIds = DB::table('users')->where('employee_id', $employeeId)->where('is_active', true)->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        DB::table('users')->whereIn('id', $userIds)->update(['is_active' => false, 'updated_at' => now()]);
        self::revokeTrustedDevices($userIds->all());

        foreach ($userIds as $userId) {
            AuditTrail::record('user_account_auto_deactivated', 'users', (string) $userId, afterState: [
                'reason' => $reason,
                'employee_id' => $employeeId,
            ]);
        }

        return $userIds->count();
    }

    /** "Remember this device" must not outlive a deactivation. */
    public static function revokeTrustedDevices(array $userIds): void
    {
        if ($userIds && Schema::hasTable('user_trusted_devices')) {
            DB::table('user_trusted_devices')->whereIn('user_id', $userIds)->delete();
        }
    }
}
