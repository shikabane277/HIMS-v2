<?php

namespace App\Support;

/**
 * The one definition of what a performance review's status means.
 *
 * THREE STATES, ONLY TWO OF THEM SETTABLE
 *
 *   draft      the reviewer is still working on it
 *   finished   the reviewer says it is done — still editable, and signed
 *   completed  the review cycle's end date has passed; frozen for good
 *
 * A person can only ever set the first two. `completed` is not a value anybody
 * chooses and it is deliberately NOT stored: it is a fact about the review's
 * cycle, derived from `review_cycles.end_date`, so it becomes true the moment
 * the date rolls over on every server, in every environment, with nothing
 * scheduled and nothing to go wrong.
 *
 * WHY DERIVED RATHER THAN WRITTEN BY A NIGHTLY JOB
 *
 * The freeze is an authorization rule — a completed review refuses edits — and
 * an authorization rule that depends on `schedule:run` being wired is an
 * authorization rule that fails open on any box where it is not. `config/hims.php`
 * says as much about the credential sweep ("without that the command still works
 * when run by hand"), which is fine for an alert and not fine for a lock. So the
 * date is the rule and there is no second copy of it.
 *
 * The cost, stated plainly: `performance_reviews.status` only ever contains
 * 'draft' or 'finished'. A raw query that reads the column alone cannot see that
 * a review is completed — it has to join `review_cycles` and go through of() or
 * caseSql(). Every read path in PerformanceController does.
 *
 * A cycle ends at the close of its end date, so a review freezes the day AFTER —
 * a supervisor still has the whole of the last day to finish writing.
 *
 * Comparisons bind dates as strings rather than baking in CURDATE(), which is
 * what keeps the SQL running on the sqlite connection phpunit uses. Same reason,
 * same shape as CredentialStatus.
 *
 * @see CredentialStatus the same pattern for credential expiry
 */
final class ReviewStatus
{
    /** The reviewer is still working on it. */
    public const DRAFT = 'draft';

    /** The reviewer has signed it off. Still editable while the cycle runs. */
    public const FINISHED = 'finished';

    /** The cycle has ended. Derived, never stored, never editable. */
    public const COMPLETED = 'completed';

    /** The only two values a human may write to the column. */
    public const SETTABLE = [self::DRAFT, self::FINISHED];

    /**
     * The effective status of one review.
     *
     * Kept deliberately in step with caseSql() below — if you change one, change
     * both. ReviewStatusTest asserts the two agree on every boundary date, so a
     * drift fails the suite rather than the hospital.
     */
    public static function of(?string $stored, ?string $cycleEndDate): string
    {
        if (self::cycleHasEnded($cycleEndDate)) {
            return self::COMPLETED;
        }

        return in_array($stored, self::SETTABLE, true) ? $stored : self::DRAFT;
    }

    /**
     * Has the cycle's end date passed?
     *
     * Forwards to CycleStatus, which owns the definition of a cycle ending — the
     * cycle badge and the review freeze must turn over on the same day, and two
     * copies of one date comparison is exactly how they would stop doing that.
     */
    public static function cycleHasEnded(?string $cycleEndDate): bool
    {
        return CycleStatus::hasEnded($cycleEndDate);
    }

    /**
     * The same decision as of(), as a SQL CASE expression.
     *
     * Use with the bindings from caseBindings():
     *   ->selectRaw(ReviewStatus::caseSql('pr.status', 'rc.end_date').' as effective_status',
     *               ReviewStatus::caseBindings())
     */
    public static function caseSql(string $statusColumn, string $endDateColumn): string
    {
        return "CASE
            WHEN {$endDateColumn} IS NOT NULL AND {$endDateColumn} < ? THEN '".self::COMPLETED."'
            WHEN {$statusColumn} = '".self::FINISHED."' THEN '".self::FINISHED."'
            ELSE '".self::DRAFT."'
        END";
    }

    /** @return array<int, string> */
    public static function caseBindings(): array
    {
        return [self::today()];
    }

    /** Human label for a status key. */
    public static function label(string $status): string
    {
        return match ($status) {
            self::COMPLETED => 'Completed',
            self::FINISHED => 'Finished',
            default => 'Draft',
        };
    }

    /**
     * Matching hims-badge colour modifier.
     *
     * Completed is green because it is the end of the road; finished is blue
     * rather than green so the two are not mistaken for each other on a list
     * where both appear.
     */
    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::COMPLETED => 'green',
            self::FINISHED => 'blue',
            default => 'gray',
        };
    }

    /** The hospital's today, as a Y-m-d string. See config/app.php's timezone. */
    public static function today(): string
    {
        return CycleStatus::today();
    }
}
