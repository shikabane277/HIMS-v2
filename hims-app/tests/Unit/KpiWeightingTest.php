<?php

namespace Tests\Unit;

use App\Http\Controllers\PerformanceController;
use App\Support\KpiWeighting;
use Tests\TestCase;

/**
 * Guards the rule that the share a review screen prints against a KPI is the
 * same coefficient the stored final score was calculated with.
 *
 * The review pages used to print `weight 0.60` — a number with no meaning outside
 * the formula — beside a "Weighted" column that showed the plain rating under a
 * heading claiming otherwise. Both are gone; what replaced them is a percentage,
 * and a percentage is a claim about arithmetic that can be wrong. So it is
 * asserted here rather than eyeballed on the page.
 *
 * Touches no database: the class takes rows the caller already selected.
 *
 * @see KpiWeighting
 * @see PerformanceController::recalculateReviewTotals() the write side
 */
class KpiWeightingTest extends TestCase
{
    /** @param  array<int, array{0: string, 1: float|null, 2: float|string|null}>  $spec */
    private function rows(array $spec): array
    {
        return array_map(
            fn (array $r) => (object) ['score_id' => $r[0], 'weight' => $r[1], 'supervisor_score' => $r[2]],
            $spec
        );
    }

    public function test_equal_weights_split_evenly(): void
    {
        $shares = KpiWeighting::shares($this->rows([
            ['a', 1.00, 4], ['b', 1.00, 3], ['c', 1.00, 5], ['d', 1.00, 2],
        ]));

        foreach (['a', 'b', 'c', 'd'] as $id) {
            $this->assertEqualsWithDelta(25.0, $shares[$id], 0.001);
        }
    }

    /**
     * The seeded library's real spread. A heavier KPI must come out ahead of a
     * lighter one, and the set must account for the whole score and no more.
     */
    public function test_shares_are_proportional_and_total_one_hundred(): void
    {
        $shares = KpiWeighting::shares($this->rows([
            ['heavy', 1.00, 4], ['mid', 0.80, 4], ['light', 0.60, 4],
        ]));

        $this->assertEqualsWithDelta(100.0, array_sum($shares), 0.001);
        $this->assertGreaterThan($shares['mid'], $shares['heavy']);
        $this->assertGreaterThan($shares['light'], $shares['mid']);
        $this->assertEqualsWithDelta(1.00 / 2.40 * 100, $shares['heavy'], 0.001);
    }

    /**
     * The whole reason the share cannot be computed from the attached rows: an
     * unrated KPI leaves the denominator, so the rated ones grow to fill it. A
     * share computed over attached rows would disagree with the score on screen.
     */
    public function test_unrated_rows_are_excluded_and_the_rest_grow_to_fill_the_gap(): void
    {
        $rated = $this->rows([['a', 1.00, 4], ['b', 1.00, 3]]);
        $withBlank = $this->rows([['a', 1.00, 4], ['b', 1.00, 3], ['c', 1.00, null]]);

        $this->assertEqualsWithDelta(50.0, KpiWeighting::shares($rated)['a'], 0.001);

        $shares = KpiWeighting::shares($withBlank);
        $this->assertArrayNotHasKey('c', $shares, 'an unrated KPI has no share, not a zero share');
        $this->assertEqualsWithDelta(50.0, $shares['a'], 0.001);
        $this->assertEqualsWithDelta(100.0, array_sum($shares), 0.001);
    }

    /** A cleared box is a withdrawn answer, exactly as cleanScore() treats it. */
    public function test_empty_string_counts_as_unrated(): void
    {
        $this->assertSame([], KpiWeighting::shares($this->rows([['a', 1.00, '']])));
        $this->assertSame(0, KpiWeighting::ratedCount($this->rows([['a', 1.00, '']])));
    }

    public function test_a_sheet_with_nothing_rated_has_no_shares(): void
    {
        $this->assertSame([], KpiWeighting::shares($this->rows([['a', 1.00, null], ['b', 0.80, null]])));
    }

    public function test_zero_and_null_weights_count_as_one(): void
    {
        $this->assertSame(1.0, KpiWeighting::effectiveWeight(null));
        $this->assertSame(1.0, KpiWeighting::effectiveWeight(0));
        $this->assertSame(1.0, KpiWeighting::effectiveWeight('0.00'));
        $this->assertSame(0.6, KpiWeighting::effectiveWeight('0.60'));

        // An unset weight must not silently drop the KPI out of the score.
        $shares = KpiWeighting::shares($this->rows([['a', null, 4], ['b', 1.00, 4]]));
        $this->assertEqualsWithDelta(50.0, $shares['a'], 0.001);
    }

    public function test_rated_count_counts_only_rated_rows(): void
    {
        $this->assertSame(2, KpiWeighting::ratedCount($this->rows([
            ['a', 1.00, 4], ['b', 1.00, null], ['c', 0.60, 2.5],
        ])));
    }

    /**
     * The defect that only showed up in a browser: seven KPIs whose exact shares
     * each round down produce a column a reviewer adds up to 102%.
     *
     * These are the live weights on Maria Santos' review, in the order the screen
     * lists them, so the figures asserted here are the ones actually printed.
     */
    public function test_displayed_shares_always_total_exactly_one_hundred(): void
    {
        $live = $this->rows([
            ['a', 1.00, 4], ['b', 0.90, 4], ['c', 1.00, 5], ['d', 0.80, 4],
            ['e', 0.80, 4], ['f', 1.00, 4], ['g', 0.90, 4],
        ]);

        $independent = array_map(fn (float $s) => (int) round($s), KpiWeighting::shares($live));
        $this->assertSame(102, array_sum($independent), 'the bug: rounding each share alone overshoots');

        $this->assertSame(100, array_sum(KpiWeighting::displayShares($live)));
    }

    /**
     * Apportionment must not distort the ranking it is rounding: a heavier KPI can
     * never be printed as counting less than a lighter one on the same review.
     */
    public function test_displayed_shares_keep_the_weight_order(): void
    {
        $shares = KpiWeighting::displayShares($this->rows([
            ['heavy', 1.00, 4], ['mid', 0.80, 4], ['light', 0.60, 4],
        ]));

        $this->assertSame(100, array_sum($shares));
        $this->assertGreaterThanOrEqual($shares['mid'], $shares['heavy']);
        $this->assertGreaterThanOrEqual($shares['light'], $shares['mid']);
    }

    /** Three equal KPIs cannot each be 33% and total 100 — one row carries the point. */
    public function test_the_leftover_point_goes_to_the_first_row_on_a_tie(): void
    {
        $shares = KpiWeighting::displayShares($this->rows([
            ['first', 1.00, 4], ['second', 1.00, 4], ['third', 1.00, 4],
        ]));

        $this->assertSame(['first' => 34, 'second' => 33, 'third' => 33], $shares);
    }

    public function test_displayed_shares_are_empty_when_nothing_is_rated(): void
    {
        $this->assertSame([], KpiWeighting::displayShares($this->rows([['a', 1.00, null]])));
    }

    /**
     * The contract that makes the percentage honest: applying each share to its
     * rating must reproduce the weighted mean the controller stores in
     * `performance_reviews.overall_score`.
     */
    public function test_shares_reproduce_the_stored_overall_score(): void
    {
        $rows = $this->rows([['a', 1.00, 3], ['b', 0.80, 5], ['c', 0.60, 5], ['d', 0.90, null]]);

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        foreach ($rows as $row) {
            if (KpiWeighting::isRated($row)) {
                $w = KpiWeighting::effectiveWeight($row->weight);
                $weightedSum += (float) $row->supervisor_score * $w;
                $weightTotal += $w;
            }
        }
        $overall = round($weightedSum / $weightTotal, 2);

        $shares = KpiWeighting::shares($rows);
        $fromShares = 0.0;
        foreach ($rows as $row) {
            if (isset($shares[$row->score_id])) {
                $fromShares += (float) $row->supervisor_score * $shares[$row->score_id] / 100;
            }
        }

        $this->assertEqualsWithDelta($overall, round($fromShares, 2), 0.01);
        $this->assertSame(4.17, $overall, 'the worked example: 3/5/5 at 1.00/0.80/0.60');
    }
}
