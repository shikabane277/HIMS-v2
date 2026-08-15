<?php

namespace App\Support;

/**
 * The one definition of where a review cycle is in its life, and the one place
 * that answers "has this cycle's end date passed?".
 *
 * THE DATE OWNS THE ENDING
 *
 * `review_cycles.status` is a hand-set column, and for a long time that was the
 * whole story: an admin picked 'active' when they opened the cycle and nothing
 * ever changed it again. So a cycle whose end date was six months gone still
 * wore a green Active badge, sat in the "Active Cycles" count, and offered
 * itself in the new-review dropdown — while every review inside it was already
 * frozen by ReviewStatus. The screen and the lock disagreed, and the screen was
 * the one people believed.
 *
 * Now the end date wins: once it has passed the cycle reads 'closed' no matter
 * what the column says. The stored value is the admin's intent and still decides
 * everything the calendar cannot — whether a cycle is 'planned' or open for
 * business, and whether it has been filed away.
 *
 * ARCHIVED OUTRANKS THE DATE
 *
 * 'archived' is the one state a date cannot express: somebody deliberately put
 * this cycle out of sight. Closing is what the calendar does; archiving is what
 * a person does, and it is the further of the two, so it survives.
 *
 * DERIVED, NOT WRITTEN BY A JOB — same reasoning as ReviewStatus, and it matters
 * more here than it looks. This is not just a badge: the create-review dropdown
 * and the supervisor-unavailable exception path both read "is this cycle open?",
 * so a nightly job that failed to run would let a reviewer open a brand-new
 * review inside a dead cycle — a review born frozen, editable by nobody.
 * Deriving it means there is nothing to fail.
 *
 * The cost, stated plainly, is the same one ReviewStatus pays: the column can
 * say 'active' while the cycle is closed. Nothing may read `status` raw — go
 * through of() for a value, or whereNotEnded() for a query.
 *
 * @see ReviewStatus which derives a review's freeze from the same end date
 * @see CredentialStatus the same derived-status pattern for credential expiry
 */
final class CycleStatus
{
    /** Set up, not yet open to reviewers. */
    public const PLANNED = 'planned';

    /** Open: reviews can be created and scored. */
    public const ACTIVE = 'active';

    /** Over. Every review inside it is frozen. Forced by the end date. */
    public const CLOSED = 'closed';

    /** Filed away by a person. Outranks the end date. */
    public const ARCHIVED = 'archived';

    /**
     * What a human may write to the column.
     *
     * 'closed' stays settable so a cycle can be abandoned before its end date;
     * it simply cannot be avoided once that date passes.
     */
    public const SETTABLE = [self::PLANNED, self::ACTIVE, self::CLOSED, self::ARCHIVED];

    /** Cycles a new review may still be opened against. */
    public const OPEN_FOR_REVIEWS = [self::PLANNED, self::ACTIVE];

    /**
     * The effective status of one cycle.
     *
     * Kept deliberately in step with whereNotEnded() below — the two encode the
     * same date rule, one for PHP and one for SQL, and CycleStatusTest asserts
     * they agree on every boundary date.
     */
    public static function of(?string $stored, ?string $endDate): string
    {
        if ($stored === self::ARCHIVED) {
            return self::ARCHIVED;
        }

        if (self::hasEnded($endDate)) {
            return self::CLOSED;
        }

        return in_array($stored, self::SETTABLE, true) ? $stored : self::PLANNED;
    }

    /**
     * Has the cycle's end date passed?
     *
     * Strictly before today, so a cycle ending today is still open for the whole
     * of that day — the last day of a cycle is a working day for every reviewer
     * still writing, and `end_date` is inclusive everywhere it is displayed.
     *
     * This is the single definition of the ending. ReviewStatus::cycleHasEnded()
     * forwards here rather than keeping a second copy.
     */
    public static function hasEnded(?string $endDate): bool
    {
        if ($endDate === null || $endDate === '') {
            return false;
        }

        return substr($endDate, 0, 10) < self::today();
    }

    /**
     * The same date rule as hasEnded(), as a query constraint.
     *
     * Binds today as a string rather than reaching for CURDATE() so the SQL runs
     * on the sqlite connection phpunit uses, and so PHP and the database cannot
     * disagree about what day it is.
     *
     * A cycle with no end date is treated as not ended: the freeze has to be a
     * positive statement that a date has passed, never the absence of one.
     */
    public static function whereNotEnded($query, string $endDateColumn = 'end_date')
    {
        return $query->where(function ($q) use ($endDateColumn) {
            $q->whereNull($endDateColumn)->orWhere($endDateColumn, '>=', self::today());
        });
    }

    /** Human label for a status key. */
    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'Active',
            self::CLOSED => 'Closed',
            self::ARCHIVED => 'Archived',
            default => 'Planned',
        };
    }

    /**
     * Matching hims-badge colour modifier.
     *
     * Only a live cycle is green. Closed and archived are both grey — the
     * difference between them is not something a reviewer needs to act on — and
     * a planned cycle is yellow because it is waiting on somebody.
     */
    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'green',
            self::CLOSED, self::ARCHIVED => 'gray',
            default => 'yellow',
        };
    }

    /** Whether the status dot next to the badge should read as live. */
    public static function isLive(string $status): bool
    {
        return $status === self::ACTIVE;
    }

    /** The hospital's today, as a Y-m-d string. See config/app.php's timezone. */
    public static function today(): string
    {
        return now()->toDateString();
    }
}
