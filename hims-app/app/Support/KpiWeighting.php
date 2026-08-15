<?php

namespace App\Support;

use App\Http\Controllers\PerformanceController;

/**
 * The one definition of how a KPI's weight turns into influence over a review's
 * final score.
 *
 * A WEIGHT MEANS NOTHING ON ITS OWN
 *
 * `kpi_library.weight` is a decimal(3,2) — the seeded values run 1.00, 0.90,
 * 0.80, 0.70, 0.60. Printed raw on a review screen, "weight 0.60" tells a
 * supervisor nothing: it is not a multiplier they can apply to their rating, and
 * it is not a percentage. It only acquires meaning next to the weights of the
 * other KPIs on the same review, because overall_score is a weighted mean and
 * every weight there is divided by the total.
 *
 * So the honest thing to show is the quotient itself: share() answers "how much
 * of the final score does this one KPI decide?", which is exactly the coefficient
 * the arithmetic gives it and the only form a person can act on.
 *
 * THE DENOMINATOR IS THE RATED ROWS, NOT THE ATTACHED ONES
 *
 * An unrated KPI is absent from the roll-up, not a zero — see
 * PerformanceController::cleanScore(). Its weight drops out of the denominator
 * too, so the remaining KPIs' shares grow to fill the gap. Any share computed
 * over *attached* rows would therefore disagree with the score on display the
 * moment one box was left blank, which is the same class of quietly-wrong number
 * this class exists to remove. shares() counts only rated rows, and reports the
 * unrated ones as having no share yet, because that is what they have.
 *
 * ONE COPY, USED BY BOTH THE SCREEN AND THE WRITE
 *
 * PerformanceController::recalculateReviewTotals() computes overall_score through
 * effectiveWeight() and the review screens render shares through shares(). Same
 * rules, one implementation: a KPI cannot be shown as deciding 13% of a score
 * that was calculated as though it decided something else.
 *
 * No database access and no Carbon — pure arithmetic over rows the caller has
 * already selected, which is what keeps Unit\KpiWeightingTest off the DB.
 *
 * @see PerformanceController::recalculateReviewTotals()
 */
final class KpiWeighting
{
    /**
     * The weight actually used in the arithmetic.
     *
     * A missing, null or zero weight counts as 1.00 rather than removing the KPI
     * from the score: a KPI attached to a review is something the reviewer was
     * asked to judge, and a blank column in the library is an unset field, not an
     * instruction to ignore their answer. Mirrors the `?: 1` the roll-up has
     * always applied — stated here once so both sides cannot drift.
     */
    public static function effectiveWeight($weight): float
    {
        $weight = (float) ($weight ?? 0);

        return $weight > 0 ? $weight : 1.0;
    }

    /**
     * Has the reviewer put a rating on this row?
     *
     * Reads `supervisor_score`, the one field the form writes, because that is
     * what the reviewer actually did. Empty string counts as unrated for the same
     * reason cleanScore() nulls it: a cleared box is a withdrawn answer.
     */
    public static function isRated($row): bool
    {
        $score = is_array($row) ? ($row['supervisor_score'] ?? null) : ($row->supervisor_score ?? null);

        return $score !== null && $score !== '';
    }

    /**
     * Each rated row's share of the final score, keyed by `score_id`, as a
     * percentage.
     *
     * Unrated rows are deliberately absent from the returned array rather than
     * present with a 0 — the caller has to decide what to say about a KPI that is
     * not counted yet, and a 0% would read as "counted, and worthless".
     *
     * @param  iterable<int, object|array>  $rows  rows carrying score_id, supervisor_score, weight
     * @return array<string, float>
     */
    public static function shares(iterable $rows): array
    {
        $rated = [];
        $total = 0.0;

        foreach ($rows as $row) {
            if (! self::isRated($row)) {
                continue;
            }

            $id = (string) (is_array($row) ? ($row['score_id'] ?? '') : ($row->score_id ?? ''));
            $weight = self::effectiveWeight(is_array($row) ? ($row['weight'] ?? null) : ($row->weight ?? null));

            $rated[$id] = $weight;
            $total += $weight;
        }

        if ($total <= 0) {
            return [];
        }

        return array_map(fn (float $weight) => $weight / $total * 100, $rated);
    }

    /**
     * The same shares rounded to whole percentages that still total exactly 100.
     *
     * Rounding each share on its own does not work: seven KPIs of similar weight
     * round to 16/14/16/13/13/16/14, which a reviewer reading down the column adds
     * up to 102%. A column of percentages that does not total 100 is precisely the
     * quietly-wrong number this class exists to remove, so the rounding is
     * apportioned rather than independent — largest remainder, the standard
     * method: floor everything, then hand the leftover points to the rows with the
     * largest fractional parts.
     *
     * Ties break on the order the rows arrived, which is the order the screen
     * lists them, so the same review always shows the same figures.
     *
     * @param  iterable<int, object|array>  $rows
     * @return array<string, int>
     */
    public static function displayShares(iterable $rows): array
    {
        $exact = self::shares($rows);

        if ($exact === []) {
            return [];
        }

        $floors = array_map('intval', $exact);
        $remainder = 100 - array_sum($floors);

        if ($remainder > 0) {
            $fractions = [];
            foreach ($exact as $id => $share) {
                $fractions[$id] = $share - (int) $share;
            }

            // arsort keeps insertion order among equal fractions, so a tie goes to
            // the KPI listed first rather than to whichever PHP felt like.
            arsort($fractions);

            foreach (array_slice(array_keys($fractions), 0, $remainder) as $id) {
                $floors[$id]++;
            }
        }

        return $floors;
    }

    /** How many of the attached KPIs carry a rating. */
    public static function ratedCount(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (self::isRated($row)) {
                $count++;
            }
        }

        return $count;
    }
}
