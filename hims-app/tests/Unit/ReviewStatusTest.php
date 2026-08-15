<?php

namespace Tests\Unit;

use App\Support\ReviewStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the one thing ReviewStatus exists to guarantee: that the PHP answer and
 * the SQL answer are the same answer.
 *
 * Like CredentialStatus, the class holds two implementations of one decision —
 * of() for PHP and caseSql() for queries — because a status derived on every
 * read has to be usable both in a Blade loop and in a GROUP BY. Two
 * implementations of one rule is exactly the shape that drifts.
 *
 * Here the stakes are not cosmetic either: `completed` is an authorization
 * boundary, not a label. A review the listing badges Completed while the save
 * handler still accepts a PUT is an edit to frozen evidence. The screen and the
 * lock read the same rule through these two methods, so the boundary days are
 * what is enumerated below rather than a comfortable date in the middle.
 *
 * Touches no domain table — the CASE is evaluated against inline literals.
 *
 * @see ReviewStatus
 * @see CredentialStatusTest the same guard for credential expiry
 */
class ReviewStatusTest extends TestCase
{
    /**
     * Offsets in days from today for the cycle's end date, the stored value, and
     * the effective status each pair must produce.
     *
     * The rule is: end date strictly in the past wins over anything stored;
     * otherwise the stored value stands, defaulting to draft.
     */
    public static function boundaryDates(): array
    {
        return [
            'ended long ago, was draft' => [-400, ReviewStatus::DRAFT, ReviewStatus::COMPLETED],
            'ended long ago, was finished' => [-400, ReviewStatus::FINISHED, ReviewStatus::COMPLETED],
            'ended yesterday' => [-1, ReviewStatus::FINISHED, ReviewStatus::COMPLETED],
            'ends today, still a draft' => [0, ReviewStatus::DRAFT, ReviewStatus::DRAFT],
            'ends today, finished' => [0, ReviewStatus::FINISHED, ReviewStatus::FINISHED],
            'ends tomorrow' => [1, ReviewStatus::DRAFT, ReviewStatus::DRAFT],
            'ends next year' => [400, ReviewStatus::FINISHED, ReviewStatus::FINISHED],
        ];
    }

    #[DataProvider('boundaryDates')]
    public function test_php_and_sql_agree_on_every_boundary_date(int $offsetDays, string $stored, string $expected): void
    {
        $endDate = now()->addDays($offsetDays)->toDateString();

        $this->assertSame($expected, ReviewStatus::of($stored, $endDate), "PHP disagreed for {$stored} ending {$endDate}");
        $this->assertSame($expected, $this->statusViaSql($stored, $endDate), "SQL disagreed for {$stored} ending {$endDate}");
    }

    /**
     * A cycle ending today is still open.
     *
     * Called out separately because it is the case a naive `end_date <= today`
     * gets wrong, and getting it wrong takes the last day of the cycle away from
     * every reviewer still writing — the day most of them are.
     */
    public function test_a_cycle_ending_today_has_not_ended(): void
    {
        $today = now()->toDateString();

        $this->assertFalse(ReviewStatus::cycleHasEnded($today));
        $this->assertSame(ReviewStatus::DRAFT, ReviewStatus::of(ReviewStatus::DRAFT, $today));
        $this->assertSame(ReviewStatus::DRAFT, $this->statusViaSql(ReviewStatus::DRAFT, $today));
    }

    public function test_a_cycle_that_ended_yesterday_has_ended(): void
    {
        $this->assertTrue(ReviewStatus::cycleHasEnded(now()->subDay()->toDateString()));
    }

    /**
     * A review with no cycle end date is never completed.
     *
     * Fails open on purpose: the freeze must be a positive statement that a date
     * has passed, never the absence of information. A missing join is a bug to
     * fix, not a reason to lock a live review.
     */
    public function test_a_missing_end_date_never_completes_in_either_path(): void
    {
        $this->assertFalse(ReviewStatus::cycleHasEnded(null));
        $this->assertFalse(ReviewStatus::cycleHasEnded(''));

        $this->assertSame(ReviewStatus::DRAFT, ReviewStatus::of(ReviewStatus::DRAFT, null));
        $this->assertSame(ReviewStatus::FINISHED, ReviewStatus::of(ReviewStatus::FINISHED, null));
        $this->assertSame(ReviewStatus::FINISHED, $this->statusViaSql(ReviewStatus::FINISHED, null));
    }

    /** A stored DATETIME end date must read the same as the DATE it falls on. */
    public function test_a_datetime_end_date_is_truncated_to_its_date(): void
    {
        $this->assertFalse(ReviewStatus::cycleHasEnded(now()->toDateString().' 00:00:00'));
        $this->assertTrue(ReviewStatus::cycleHasEnded(now()->subDay()->toDateString().' 23:59:59'));
    }

    /**
     * An unrecognised stored value reads as a draft, not as itself.
     *
     * The old statuses (`self_assessment`, `supervisor_review`, `calibration`)
     * have no successor, and migration ..._000170 purged every row that held one.
     * Should one ever reappear — a restored dump, a hand-written insert — it must
     * degrade to the least privileged state rather than render as a stage the
     * system no longer has.
     */
    public function test_an_unknown_stored_status_degrades_to_draft(): void
    {
        $future = now()->addMonth()->toDateString();

        foreach ([null, '', 'supervisor_review', 'self_assessment', 'calibration', ReviewStatus::COMPLETED] as $stored) {
            $this->assertSame(ReviewStatus::DRAFT, ReviewStatus::of($stored, $future));
        }
    }

    /**
     * `completed` is not settable, and SETTABLE is what the validation rule is
     * built from — so this assertion is what keeps a hand-crafted POST from
     * writing the frozen state into the column.
     */
    public function test_completed_is_not_a_settable_status(): void
    {
        $this->assertSame([ReviewStatus::DRAFT, ReviewStatus::FINISHED], ReviewStatus::SETTABLE);
        $this->assertNotContains(ReviewStatus::COMPLETED, ReviewStatus::SETTABLE);
    }

    /** Every state must have a label and a badge colour; none may fall through to a blank. */
    public function test_every_state_has_a_distinct_label_and_badge_class(): void
    {
        $states = [ReviewStatus::DRAFT, ReviewStatus::FINISHED, ReviewStatus::COMPLETED];

        foreach ($states as $state) {
            $this->assertNotSame('', ReviewStatus::label($state));
            $this->assertNotSame('', ReviewStatus::badgeClass($state));
        }

        // Finished and completed must not share a colour: both appear on the same
        // listing, and "done" and "frozen" are different things to a reviewer.
        $this->assertNotSame(
            ReviewStatus::badgeClass(ReviewStatus::FINISHED),
            ReviewStatus::badgeClass(ReviewStatus::COMPLETED),
            'Finished and Completed render identically, so a list cannot tell them apart.'
        );

        $this->assertSame('Finished', ReviewStatus::label(ReviewStatus::FINISHED));
        $this->assertSame('Completed', ReviewStatus::label(ReviewStatus::COMPLETED));
    }

    /**
     * Runs caseSql() through the database rather than re-reading it in PHP.
     *
     * The values are inlined as the "column" expressions so no table is needed —
     * what is under test is the CASE itself, and its binding still arrives from
     * caseBindings() in the order the SQL expects it.
     */
    private function statusViaSql(?string $stored, ?string $endDate): string
    {
        $statusColumn = $stored === null ? 'NULL' : "'".$stored."'";
        $endColumn = $endDate === null ? 'NULL' : "'".$endDate."'";

        return DB::selectOne(
            'SELECT '.ReviewStatus::caseSql($statusColumn, $endColumn).' AS status',
            ReviewStatus::caseBindings()
        )->status;
    }
}
