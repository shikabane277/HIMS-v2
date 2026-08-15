<?php

namespace App\Support;

use App\Console\Commands\ScanCredentialExpiry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * The single definition of what a credential's expiry status means.
 *
 * Before this class the answer was recomputed inline wherever it was needed —
 * five times in CompetencyController, four more in DashboardController — and
 * the copies disagreed. Two ways:
 *
 *   1. Some used whereBetween('expiry_date', [now(), now()->addDays(30)]),
 *      which compares a DATE column against a DATETIME. A credential expiring
 *      today has an implied 00:00 timestamp, so it sorted as already past and
 *      dropped out of "expiring soon" partway through the morning.
 *   2. "Valid" counted expiry_date >= now(), which includes the whole expiring
 *      window, so the same credential was counted as both valid and expiring
 *      and the two figures on one page did not add up.
 *
 * That was tolerable while the status was only ever printed. It stops being
 * tolerable once alerts fire off it: a nightly job and the dashboard beside it
 * must agree on who is expiring, or people are warned about credentials the
 * screen calls fine — or worse, not warned about ones it calls expiring.
 *
 * Everything here compares dates to dates and the four states are mutually
 * exclusive. Bindings are passed as parameters rather than baked in with
 * CURDATE() so the SQL runs on the sqlite connection phpunit uses, and so the
 * "today" of a scan is fixed at the moment it started rather than drifting
 * across a run that straddles midnight.
 *
 * @see ScanCredentialExpiry the alerting consumer
 */
final class CredentialStatus
{
    /** How far ahead counts as "expiring soon". */
    public const WINDOW_DAYS = 30;

    public const ACTIVE = 'active';

    public const EXPIRING = 'expiring_soon';

    public const EXPIRED = 'expired';

    public const NO_EXPIRY = 'no_expiry';

    /**
     * Status of a single expiry date.
     *
     * Kept deliberately in step with caseSql() below — if you change one,
     * change both. CredentialStatusTest asserts the two agree on every
     * boundary date, so a drift fails the suite rather than the hospital.
     */
    public static function of(?string $expiryDate): string
    {
        if ($expiryDate === null || $expiryDate === '') {
            return self::NO_EXPIRY;
        }

        $date = substr($expiryDate, 0, 10);

        if ($date < self::today()) {
            return self::EXPIRED;
        }

        return $date <= self::windowEnd() ? self::EXPIRING : self::ACTIVE;
    }

    /**
     * The same decision as of(), as a SQL CASE expression.
     *
     * Use with the bindings from caseBindings():
     *   ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status',
     *               CredentialStatus::caseBindings())
     */
    public static function caseSql(string $column): string
    {
        return "CASE
            WHEN {$column} IS NULL THEN '".self::NO_EXPIRY."'
            WHEN {$column} < ? THEN '".self::EXPIRED."'
            WHEN {$column} <= ? THEN '".self::EXPIRING."'
            ELSE '".self::ACTIVE."'
        END";
    }

    /** @return array<int, string> */
    public static function caseBindings(): array
    {
        return [self::today(), self::windowEnd()];
    }

    /**
     * Credentials inside the warning window, today inclusive.
     *
     * @param  Builder  $query
     */
    public static function whereExpiring($query, string $column = 'expiry_date')
    {
        return $query->whereNotNull($column)
            ->whereDate($column, '>=', self::today())
            ->whereDate($column, '<=', self::windowEnd());
    }

    /**
     * Credentials whose expiry date has passed. Strictly before today, so a
     * credential expiring today is "expiring", not yet "expired".
     *
     * @param  Builder  $query
     */
    public static function whereExpired($query, string $column = 'expiry_date')
    {
        return $query->whereNotNull($column)
            ->whereDate($column, '<', self::today());
    }

    /**
     * Credentials comfortably in date — beyond the warning window.
     *
     * Note this deliberately EXCLUDES the expiring window, unlike the old
     * inline "valid" counts. Active + expiring + expired + no-expiry now sum
     * to the total instead of overlapping.
     *
     * @param  Builder  $query
     */
    public static function whereActive($query, string $column = 'expiry_date')
    {
        return $query->whereNotNull($column)
            ->whereDate($column, '>', self::windowEnd());
    }

    /** Human label for a status key. */
    public static function label(string $status): string
    {
        return match ($status) {
            self::EXPIRED => 'Expired',
            self::EXPIRING => 'Expiring soon',
            self::ACTIVE => 'Active',
            default => 'No expiry',
        };
    }

    /** Matching hims-badge colour modifier. */
    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::EXPIRED => 'red',
            self::EXPIRING => 'yellow',
            self::ACTIVE => 'green',
            default => 'gray',
        };
    }

    /** Days until expiry; negative once past. Null when there is no expiry. */
    public static function daysRemaining(?string $expiryDate): ?int
    {
        if ($expiryDate === null || $expiryDate === '') {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse(substr($expiryDate, 0, 10))->startOfDay(),
            false
        );
    }

    public static function today(): string
    {
        return now()->toDateString();
    }

    public static function windowEnd(): string
    {
        return now()->addDays(self::WINDOW_DAYS)->toDateString();
    }
}
