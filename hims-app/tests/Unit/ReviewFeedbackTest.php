<?php

namespace Tests\Unit;

use App\Support\ReviewFeedback;
use Tests\TestCase;

/**
 * The written half of a performance review — strengths, improvements, and the
 * per-KPI notes — folded into one shape that both the gap-analysis page and the
 * AI prompt read from.
 *
 * THE PROPERTY THAT MATTERS: the page and the prompt are built from the same
 * array. The bug this class was written to close was a comment being selected
 * out of MySQL and then silently dropped before it reached either consumer, so
 * every test here asserts that a piece of text somebody typed survives the trip.
 *
 * No database and no Carbon — the helper transforms rows the caller already
 * selected, so the rows here are plain stdClass and arrays.
 */
class ReviewFeedbackTest extends TestCase
{
    /** A review row shaped like the service's select. */
    private function review(array $overrides = []): object
    {
        return (object) array_merge([
            'review_id' => 'rev-1',
            'cycle_name' => '2026 Annual',
            'end_date' => '2026-06-30',
            'status' => 'finished',
            'supervisor_rating' => '3.40',
            'overall_score' => '3.28',
            'strengths_text' => null,
            'improvements_text' => null,
        ], $overrides);
    }

    /** A per-KPI comment row shaped like the service's select. */
    private function comment(array $overrides = []): object
    {
        return (object) array_merge([
            'review_id' => 'rev-1',
            'kpi_name' => 'Medication Administration',
            'kpi_category' => 'clinical',
            'supervisor_score' => '2.50',
            'comments' => 'Struggles with the new infusion pump interface, not with dosing.',
        ], $overrides);
    }

    public function test_a_kpi_comment_is_grouped_under_its_own_review(): void
    {
        $grouped = ReviewFeedback::group(
            [$this->review(['review_id' => 'rev-1']), $this->review(['review_id' => 'rev-2', 'cycle_name' => '2025 Annual'])],
            [
                $this->comment(['review_id' => 'rev-2', 'kpi_name' => 'Documentation']),
                $this->comment(['review_id' => 'rev-1']),
            ]
        );

        $this->assertCount(2, $grouped);
        $this->assertSame('rev-1', $grouped[0]['review_id']);
        $this->assertSame('Medication Administration', $grouped[0]['kpi_comments'][0]['kpi_name']);
        $this->assertSame('Documentation', $grouped[1]['kpi_comments'][0]['kpi_name']);
    }

    public function test_review_order_is_the_order_the_caller_selected(): void
    {
        $grouped = ReviewFeedback::group([
            $this->review(['review_id' => 'newest', 'cycle_name' => '2026 Annual']),
            $this->review(['review_id' => 'oldest', 'cycle_name' => '2024 Annual']),
        ], []);

        $this->assertSame(['2026 Annual', '2024 Annual'], array_column($grouped, 'cycle_name'));
    }

    public function test_a_review_with_nothing_written_on_it_is_kept_and_counted_as_zero(): void
    {
        // A cycle that passed without a single comment is itself a finding.
        // Filtering it out would let the page imply feedback was always given.
        $grouped = ReviewFeedback::group([$this->review()], []);

        $this->assertCount(1, $grouped);
        $this->assertSame(0, $grouped[0]['comment_count']);
        $this->assertSame(0, ReviewFeedback::count($grouped));
    }

    public function test_blank_and_whitespace_only_narrative_is_dropped(): void
    {
        $grouped = ReviewFeedback::group([
            $this->review(['strengths_text' => '   ', 'improvements_text' => '']),
        ], []);

        $this->assertNull($grouped[0]['strengths']);
        $this->assertNull($grouped[0]['improvements']);
        $this->assertSame(0, $grouped[0]['comment_count']);
    }

    public function test_every_piece_of_written_feedback_counts_as_one(): void
    {
        $grouped = ReviewFeedback::group(
            [$this->review(['strengths_text' => 'Calm under pressure.', 'improvements_text' => 'Slow on charting.'])],
            [$this->comment(), $this->comment(['kpi_name' => 'Teamwork', 'comments' => 'Well liked on shift.'])]
        );

        // strengths + improvements + two KPI notes
        $this->assertSame(4, $grouped[0]['comment_count']);
        $this->assertSame(4, ReviewFeedback::count($grouped));
    }

    public function test_a_blank_comment_row_never_becomes_a_comment(): void
    {
        $grouped = ReviewFeedback::group([$this->review()], [$this->comment(['comments' => '  '])]);

        $this->assertSame([], $grouped[0]['kpi_comments']);
        $this->assertSame(0, ReviewFeedback::count($grouped));
    }

    public function test_array_rows_are_read_the_same_as_objects(): void
    {
        $grouped = ReviewFeedback::group(
            [['review_id' => 'rev-1', 'cycle_name' => 'Q1', 'strengths_text' => 'Reliable.']],
            [['review_id' => 'rev-1', 'kpi_name' => 'Hygiene', 'kpi_category' => 'safety', 'supervisor_score' => '4.00', 'comments' => 'Consistently correct.']]
        );

        $this->assertSame('Reliable.', $grouped[0]['strengths']);
        $this->assertSame('Consistently correct.', $grouped[0]['kpi_comments'][0]['comment']);
        $this->assertSame(4.0, $grouped[0]['kpi_comments'][0]['score']);
    }

    public function test_an_unscored_kpi_reports_no_score_rather_than_zero(): void
    {
        $grouped = ReviewFeedback::group([$this->review(['supervisor_rating' => null])], [
            $this->comment(['supervisor_score' => null]),
        ]);

        $this->assertNull($grouped[0]['supervisor_rating']);
        $this->assertNull($grouped[0]['kpi_comments'][0]['score']);

        $lines = ReviewFeedback::promptLines($grouped);
        $this->assertStringContainsString('not scored', $lines);
        $this->assertStringNotContainsString('0.00/5', $lines);
    }

    public function test_the_prompt_carries_the_words_the_supervisor_typed(): void
    {
        $lines = ReviewFeedback::promptLines(ReviewFeedback::group(
            [$this->review(['strengths_text' => 'Calm under pressure.', 'improvements_text' => 'Slow on charting.'])],
            [$this->comment()]
        ));

        $this->assertStringContainsString('2026 Annual', $lines);
        $this->assertStringContainsString('ended 2026-06-30', $lines);
        $this->assertStringContainsString('Calm under pressure.', $lines);
        $this->assertStringContainsString('Slow on charting.', $lines);
        $this->assertStringContainsString('Medication Administration', $lines);
        $this->assertStringContainsString('infusion pump interface', $lines);
        $this->assertStringContainsString('rated 2.50/5', $lines);
    }

    public function test_an_unfinished_review_is_labelled_in_the_prompt(): void
    {
        $lines = ReviewFeedback::promptLines(ReviewFeedback::group(
            [$this->review(['status' => 'draft', 'strengths_text' => 'Early signs are good.'])],
            []
        ));

        $this->assertStringContainsString('[still a draft]', $lines);
    }

    public function test_absence_of_feedback_is_stated_rather_than_left_empty(): void
    {
        // An empty section in a prompt reads as an omission the model may fill
        // in; a stated absence does not.
        $this->assertStringContainsString(
            'No performance reviews on record',
            ReviewFeedback::promptLines([])
        );

        $this->assertStringContainsString(
            'not one of them carries any written comment',
            ReviewFeedback::promptLines(ReviewFeedback::group([$this->review()], []))
        );
    }

    public function test_a_cycle_with_no_comment_is_named_among_cycles_that_have_one(): void
    {
        $lines = ReviewFeedback::promptLines(ReviewFeedback::group(
            [
                $this->review(['review_id' => 'rev-1', 'cycle_name' => '2026 Annual']),
                $this->review(['review_id' => 'rev-2', 'cycle_name' => '2025 Annual']),
            ],
            [$this->comment(['review_id' => 'rev-2'])]
        ));

        $this->assertStringContainsString('no written comment recorded in this cycle', $lines);
        $this->assertStringContainsString('2025 Annual', $lines);
    }

    public function test_a_long_comment_is_truncated_in_the_prompt_only(): void
    {
        $long = str_repeat('a', ReviewFeedback::MAX_COMMENT_CHARS + 120);

        $grouped = ReviewFeedback::group([$this->review()], [$this->comment(['comments' => $long])]);

        // The page gets the whole sentence; the prompt gets a bounded one.
        $this->assertSame($long, $grouped[0]['kpi_comments'][0]['comment']);

        $lines = ReviewFeedback::promptLines($grouped);
        $this->assertStringContainsString(str_repeat('a', ReviewFeedback::MAX_COMMENT_CHARS).'…', $lines);
        $this->assertStringNotContainsString(str_repeat('a', ReviewFeedback::MAX_COMMENT_CHARS + 1), $lines);
    }

    public function test_line_breaks_inside_a_comment_do_not_break_the_prompt_shape(): void
    {
        // The comment box is a textarea, so a note can arrive with newlines in
        // it — one per line is what the prompt format depends on.
        $lines = ReviewFeedback::promptLines(ReviewFeedback::group(
            [$this->review()],
            [$this->comment(['comments' => "Needs support.\n\nEspecially at night.", 'kpi_name' => 'Triage'])]
        ));

        $this->assertStringContainsString('Needs support. Especially at night.', $lines);
        $this->assertCount(2, explode("\n", $lines));
    }
}
