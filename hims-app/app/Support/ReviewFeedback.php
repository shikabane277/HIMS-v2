<?php

namespace App\Support;

use App\Services\CompetencyGapAnalysisService;
use Illuminate\Support\Facades\DB;

/**
 * The one definition of "what has been written about this employee in their
 * performance reviews".
 *
 * WHY THIS EXISTS
 *
 * A performance review carries three kinds of free text: the overall
 * `strengths_text` and `improvements_text` on `performance_reviews`, and one
 * `comments` note per KPI on `review_kpi_scores`. All three were being selected
 * by CompetencyGapAnalysisService and then dropped on the floor — the gap
 * analysis prompt received three numbers and a cycle name, and was still asked
 * to report "evidence", "root_causes" and "strengths_to_leverage". The words the
 * supervisor actually wrote are the only part of a review that states a cause,
 * so withholding them was withholding the answer.
 *
 * Gathering them is a join; presenting them coherently is not, which is what
 * this class is for. It takes the rows the caller has already selected and
 * answers two questions with one shape: what does the screen show, and what does
 * the model get told.
 *
 * ONE SHAPE, TWO CONSUMERS
 *
 * group() returns the same nested array the Blade page iterates and
 * promptLines() renders. A comment cannot therefore appear on the page but not
 * in the prompt, which is exactly the drift that produced the original bug —
 * the summary the AI writes is always a summary of what the reader can see
 * underneath it.
 *
 * BOUNDED BY CONSTRUCTION
 *
 * `review_kpi_scores.comments` is a TEXT column validated at 1000 characters per
 * KPI, and a review can carry a dozen KPIs. Three cycles of those would be a
 * five-figure character count pasted into every prompt, so promptLines()
 * truncates each comment to MAX_COMMENT_CHARS. The page is not truncated — the
 * screen has room and the reader is entitled to the whole sentence.
 *
 * No database access, no Carbon, no config: pure transformation over rows the
 * caller selected, which is what keeps Unit\ReviewFeedbackTest off the database
 * even though everything it describes lives in MySQL.
 *
 * @see CompetencyGapAnalysisService
 */
final class ReviewFeedback
{
    /** Per-comment ceiling in the prompt only. The screen shows the full text. */
    public const MAX_COMMENT_CHARS = 300;

    /**
     * Fold the review rows and their per-KPI comment rows into one entry per
     * review, in whatever order the caller selected them (the service selects
     * newest cycle first).
     *
     * Reviews with nothing written on them are kept rather than filtered out. A
     * cycle that came and went without a single comment is itself a finding —
     * dropping it would let the page imply feedback was given every time.
     *
     * @param  iterable<int, object|array>  $reviews  rows carrying review_id, cycle_name, end_date, status, supervisor_rating, overall_score, strengths_text, improvements_text
     * @param  iterable<int, object|array>  $comments  rows carrying review_id, kpi_name, kpi_category, supervisor_score, comments
     * @return array<int, array<string, mixed>>
     */
    public static function group(iterable $reviews, iterable $comments): array
    {
        $byReview = [];

        foreach ($comments as $row) {
            $text = self::clean(self::field($row, 'comments'));

            if ($text === null) {
                continue;
            }

            $byReview[(string) self::field($row, 'review_id')][] = [
                'kpi_name' => (string) (self::field($row, 'kpi_name') ?? '—'),
                'kpi_category' => self::clean(self::field($row, 'kpi_category')),
                'score' => self::score(self::field($row, 'supervisor_score')),
                'comment' => $text,
            ];
        }

        $grouped = [];

        foreach ($reviews as $review) {
            $reviewId = (string) self::field($review, 'review_id');
            $strengths = self::clean(self::field($review, 'strengths_text'));
            $improvements = self::clean(self::field($review, 'improvements_text'));
            $kpiComments = $byReview[$reviewId] ?? [];

            $grouped[] = [
                'review_id' => $reviewId,
                'cycle_name' => self::clean(self::field($review, 'cycle_name')) ?? 'Review',
                'end_date' => self::clean(self::field($review, 'end_date')),
                'is_draft' => self::clean(self::field($review, 'status')) === 'draft',
                'supervisor_rating' => self::score(self::field($review, 'supervisor_rating')),
                'overall_score' => self::score(self::field($review, 'overall_score')),
                'strengths' => $strengths,
                'improvements' => $improvements,
                'kpi_comments' => $kpiComments,
                'comment_count' => count($kpiComments)
                    + ($strengths !== null ? 1 : 0)
                    + ($improvements !== null ? 1 : 0),
            ];
        }

        return $grouped;
    }

    /**
     * How many individual pieces of written feedback the employee has received
     * across the grouped reviews.
     *
     * Each of strengths, improvements and every KPI note counts as one, because
     * each is one thing somebody sat down and typed.
     *
     * @param  array<int, array<string, mixed>>  $grouped
     */
    public static function count(array $grouped): int
    {
        return (int) array_sum(array_column($grouped, 'comment_count'));
    }

    /**
     * The same set rendered as prompt text, newest cycle first.
     *
     * Returns an explicit "none recorded" sentence rather than an empty string
     * when there is nothing to show. An empty section in a prompt reads as an
     * omission the model is free to fill in; a stated absence does not.
     *
     * @param  array<int, array<string, mixed>>  $grouped
     */
    public static function promptLines(array $grouped, ?string $employeeName = null): string
    {
        try {
            $includeComments = DB::table('system_settings')->where('key', 'ai_include_comments')->value('value');
            if ($includeComments === '0') {
                return '- Written supervisor review comments are excluded from AI gap-analysis per hospital privacy settings.';
            }

            $redactNames = DB::table('system_settings')->where('key', 'ai_redact_names')->value('value') !== '0';
        } catch (\Throwable $e) {
            $redactNames = true;
        }

        if ($grouped === []) {
            return '- No performance reviews on record, so no written feedback exists.';
        }

        if (self::count($grouped) === 0) {
            return '- Reviews exist but not one of them carries any written comment.';
        }

        $lines = [];

        foreach ($grouped as $review) {
            $header = sprintf(
                'Cycle "%s"%s%s — supervisor rating %s, final score %s',
                $review['cycle_name'],
                $review['end_date'] ? ' (ended '.$review['end_date'].')' : '',
                $review['is_draft'] ? ' [still a draft]' : '',
                self::formatScore($review['supervisor_rating']),
                self::formatScore($review['overall_score'])
            );

            if ($review['comment_count'] === 0) {
                $lines[] = $header."\n  (no written comment recorded in this cycle)";

                continue;
            }

            $body = [];

            if ($review['strengths'] !== null) {
                $strengths = self::truncate($review['strengths']);
                if ($redactNames) {
                    $strengths = self::redactPii($strengths, $employeeName);
                }
                $body[] = '  Overall strengths: "'.$strengths.'"';
            }

            if ($review['improvements'] !== null) {
                $improvements = self::truncate($review['improvements']);
                if ($redactNames) {
                    $improvements = self::redactPii($improvements, $employeeName);
                }
                $body[] = '  Areas to improve: "'.$improvements.'"';
            }

            foreach ($review['kpi_comments'] as $comment) {
                $text = self::truncate($comment['comment']);
                if ($redactNames) {
                    $text = self::redactPii($text, $employeeName);
                }
                $body[] = sprintf(
                    '  On %s%s, rated %s: "%s"',
                    $comment['kpi_name'],
                    $comment['kpi_category'] ? ' ('.$comment['kpi_category'].')' : '',
                    self::formatScore($comment['score']),
                    $text
                );
            }

            $lines[] = $header."\n".implode("\n", $body);
        }

        return implode("\n", $lines);
    }

    public static function redactPii(string $text, ?string $employeeName = null): string
    {
        if ($employeeName) {
            $parts = array_filter(explode(' ', $employeeName), fn ($p) => mb_strlen($p) > 2);
            foreach ($parts as $part) {
                $text = preg_replace('/\b'.preg_quote($part, '/').'\b/i', '[REDACTED]', $text);
            }
        }

        // Redact patient IDs or names following Patient / Patient # / Pt.
        $text = preg_replace('/\b(Patient|Pt\.|Patient #)\s*[A-Z0-9-]+\b/i', '[REDACTED_PATIENT]', $text);

        return $text;
    }

    public static function formatScore(?float $score): string
    {
        return $score === null ? 'not scored' : number_format($score, 2).'/5';
    }

    private static function field(object|array $row, string $key): mixed
    {
        return is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
    }

    private static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function score(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    private static function truncate(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > self::MAX_COMMENT_CHARS
            ? mb_substr($text, 0, self::MAX_COMMENT_CHARS).'…'
            : $text;
    }
}
