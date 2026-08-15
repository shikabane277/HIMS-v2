<?php

namespace Tests\Unit;

use App\Support\CycleStatus;
use App\Support\ReviewStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the rule that a review cycle ends on its end date whatever its column
 * says, and that the badge on screen and the freeze on the reviews turn over on
 * the same day.
 *
 * `review_cycles.status` is hand-set and nothing ever revisits it, so a cycle
 * that finished in March wore a green Active badge indefinitely — while every
 * review inside it was already frozen by ReviewStatus. Two answers to one
 * question, and the wrong one was the visible one. CycleStatus makes the date
 * the rule; this asserts the derivation, and asserts the two classes cannot
 * drift apart again.
 *
 * Touches no domain table — the query constraint is exercised against inline
 * literals through a one-row union.
 *
 * @see CycleStatus
 * @see ReviewStatusTest the same guard for the review freeze
 */
class CycleStatusTest extends TestCase
{
    /**
     * Offsets in days from today for the cycle's end date, the stored value, and
     * the effective status the pair must produce.
     */
    public static function boundaryDates(): array
    {
        return [
            'ended long ago, still says active' => [-400, CycleStatus::ACTIVE, CycleStatus::CLOSED],
            'ended long ago, still says planned' => [-400, CycleStatus::PLANNED, CycleStatus::CLOSED],
            'ended yesterday, says active' => [-1, CycleStatus::ACTIVE, CycleStatus::CLOSED],
            'ends today, says active' => [0, CycleStatus::ACTIVE, CycleStatus::ACTIVE],
            'ends today, says planned' => [0, CycleStatus::PLANNED, CycleStatus::PLANNED],
            'ends tomorrow' => [1, CycleStatus::ACTIVE, CycleStatus::ACTIVE],
            'ends next year' => [400, CycleStatus::PLANNED, CycleStatus::PLANNED],
            'closed early, before its end date' => [400, CycleStatus::CLOSED, CycleStatus::CLOSED],
        ];
    }

    #[DataProvider('boundaryDates')]
    public function test_the_effective_status_follows_the_end_date(int $offsetDays, string $stored, string $expected): void
    {
        $endDate = now()->addDays($offsetDays)->toDateString();

        $this->assertSame($expected, CycleStatus::of($stored, $endDate),
            "Stored '{$stored}' ending {$endDate} should read as '{$expected}'.");
    }

    /**
     * A cycle ending today is still open.
     *
     * The whole point of the strict comparison: the last day of a cycle is a
     * working day. `end_date` is displayed as inclusive everywhere, so closing
     * on the morning of that date would take a day off every reviewer still
     * writing — and freeze their half-written drafts with it.
     */
    public function test_a_cycle_ending_today_is_still_open(): void
    {
        $today = now()->toDateString();

        $this->assertFalse(CycleStatus::hasEnded($today));
        $this->assertSame(CycleStatus::ACTIVE, CycleStatus::of(CycleStatus::ACTIVE, $today));
        $this->assertTrue($this->notEndedInSql($today), 'SQL excluded a cycle that ends today.');
    }

    public function test_a_cycle_that_ended_yesterday_is_closed(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $this->assertTrue(CycleStatus::hasEnded($yesterday));
        $this->assertSame(CycleStatus::CLOSED, CycleStatus::of(CycleStatus::ACTIVE, $yesterday));
        $this->assertFalse($this->notEndedInSql($yesterday), 'SQL kept a cycle that ended yesterday.');
    }

    /**
     * Archiving is a decision; closing is a date. The decision is the further of
     * the two, so it is not overwritten by the calendar catching up.
     */
    public function test_archived_survives_the_end_date(): void
    {
        $this->assertSame(CycleStatus::ARCHIVED,
            CycleStatus::of(CycleStatus::ARCHIVED, now()->subYear()->toDateString()));

        $this->assertSame(CycleStatus::ARCHIVED,
            CycleStatus::of(CycleStatus::ARCHIVED, now()->addYear()->toDateString()));
    }

    /**
     * A cycle with no end date is open.
     *
     * Fails open deliberately, exactly as ReviewStatus does: the ending must be a
     * positive statement that a date has passed, never a missing value. A null
     * here is a data bug to fix, not grounds to close a live cycle and freeze
     * everything in it.
     */
    public function test_a_missing_end_date_never_closes_a_cycle(): void
    {
        $this->assertFalse(CycleStatus::hasEnded(null));
        $this->assertFalse(CycleStatus::hasEnded(''));

        $this->assertSame(CycleStatus::ACTIVE, CycleStatus::of(CycleStatus::ACTIVE, null));
        $this->assertTrue($this->notEndedInSql(null), 'SQL excluded a cycle with no end date.');
    }

    /** A stored DATETIME must read the same as the DATE it falls on. */
    public function test_a_datetime_end_date_is_truncated_to_its_date(): void
    {
        $this->assertFalse(CycleStatus::hasEnded(now()->toDateString().' 00:00:00'));
        $this->assertTrue(CycleStatus::hasEnded(now()->subDay()->toDateString().' 23:59:59'));
    }

    /** An unrecognised stored value reads as planned — the least privileged state. */
    public function test_an_unknown_stored_status_degrades_to_planned(): void
    {
        $future = now()->addMonth()->toDateString();

        foreach ([null, '', 'in_progress', 'open', 'ACTIVE'] as $stored) {
            $this->assertSame(CycleStatus::PLANNED, CycleStatus::of($stored, $future),
                'An unrecognised status must not render as itself.');
        }
    }

    /**
     * The cycle badge and the review freeze are the same event.
     *
     * This is the assertion that matters most: ReviewStatus::cycleHasEnded()
     * forwards to CycleStatus::hasEnded(), and if somebody ever gives it back its
     * own copy of the comparison, a cycle could read Active while its reviews
     * were frozen — which is the bug this work was reported for.
     */
    public function test_the_cycle_closing_and_the_reviews_freezing_are_one_event(): void
    {
        foreach ([-400, -2, -1, 0, 1, 400] as $offset) {
            $endDate = now()->addDays($offset)->toDateString();

            $this->assertSame(
                CycleStatus::hasEnded($endDate),
                ReviewStatus::cycleHasEnded($endDate),
                "CycleStatus and ReviewStatus disagree about {$endDate}. A cycle cannot be "
                .'open while its reviews are frozen, or closed while they are editable.'
            );

            $cycleClosed = CycleStatus::of(CycleStatus::ACTIVE, $endDate) === CycleStatus::CLOSED;
            $reviewFrozen = ReviewStatus::of(ReviewStatus::DRAFT, $endDate) === ReviewStatus::COMPLETED;

            $this->assertSame($cycleClosed, $reviewFrozen,
                "The badge and the lock disagree for a cycle ending {$endDate}.");
        }
    }

    /**
     * Both classes must read the hospital's clock, not the server's.
     *
     * The freeze was landing eight hours late because the app ran in UTC while
     * the hospital runs in Asia/Manila: for the first eight hours of every
     * Philippine day, now()->toDateString() still returned yesterday, so a cycle
     * that had ended locally was still accepting edits. The dates here are
     * decision boundaries, so the timezone is part of the rule.
     */
    public function test_the_app_clock_is_the_hospital_clock(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'),
            'The app timezone drives every date comparison in CycleStatus, ReviewStatus and '
            .'CredentialStatus. Under UTC a Philippine cycle stays editable until 8am the day '
            .'after it ends.');

        $this->assertSame(now()->toDateString(), CycleStatus::today());
        $this->assertSame(CycleStatus::today(), ReviewStatus::today(),
            'The two classes must agree on what day it is.');
    }

    /**
     * Runs whereNotEnded() through the database rather than re-reading it in PHP.
     *
     * The candidate date is inlined as a one-row derived table so no domain table
     * is needed — what is under test is the constraint, and it has to behave the
     * same way on sqlite as it does on MySQL.
     */
    private function notEndedInSql(?string $endDate): bool
    {
        $literal = $endDate === null ? 'NULL' : "'".$endDate."'";

        $query = DB::table(DB::raw("(SELECT {$literal} AS end_date) as c"));

        return CycleStatus::whereNotEnded($query, 'c.end_date')->exists();
    }
}
