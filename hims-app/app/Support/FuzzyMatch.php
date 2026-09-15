<?php

namespace App\Support;

/**
 * How close two pieces of text are, and which of several candidates a typed
 * word was most likely meant to be.
 *
 * Same shape as the other one-definition helpers in this namespace — final,
 * static, no database, no Carbon — because "is this a typo of that?" is asked
 * from three unrelated places in the AI layer and all three have to answer it
 * identically.
 *
 * WHY NOT PHP'S levenshtein()
 *
 * It is C-fast and it is the wrong metric for typing. Levenshtein counts an
 * adjacent swap as two edits, so "confrim" is as far from "confirm" (2) as
 * "abcdefg" would be from a two-letter substitution — yet transposition is the
 * single most common thing a keyboard produces. This class computes the optimal
 * string alignment distance instead, which prices one swap at 1. The words being
 * compared are chat tokens and person names, so the O(mn) cost is a few hundred
 * cell writes and the candidate lists are bounded by their callers.
 *
 * levenshtein() is also byte-based, which would score "Peña" wrongly. The DP
 * here walks characters via mb_str_split().
 *
 * AMBIGUITY IS NOT A MATCH — EXCEPT WHERE THE CALLER SAYS IT IS
 *
 * closest() returns null when two different candidates tie at the best distance,
 * mirroring AiEntityResolver's rule that two matches stop an action exactly as
 * firmly as none. A suggestion that might be either of two people is not a
 * suggestion; it is a coin toss with someone's record on the other side.
 *
 * preferred() is the same search for the caller that would rather guess: a tie
 * goes to whichever candidate was listed first. AiTypoCorrector uses it because
 * "revew" really is one edit from both review and renew, refusing to read it is
 * no help to anyone, and the guess is shown to the person in the reply. Nothing
 * that resolves a record may use it.
 *
 * Before either rule applies, a tie is settled by the first character: typing
 * errors preserve the opening letter far more often than they change it, so
 * "crate" is read as create rather than rate without either caller needing to
 * know why.
 */
final class FuzzyMatch
{
    /**
     * How many edits may separate a typed term from what it was meant to be,
     * by the term's own length.
     *
     * Short words are left alone: at three characters almost every edit lands on
     * a different real word ("cat"/"car"/"can"), so a guess there is not a
     * correction, it is a substitution of the caller's meaning. The allowance
     * grows with length because a longer word has more room to be misspelt
     * without becoming anything else.
     *
     * Callers that rewrite text pass a tighter ceiling of their own; this is the
     * default for callers that only *suggest*.
     */
    public static function ceiling(string $term): int
    {
        return match (true) {
            mb_strlen($term) <= 3 => 0,
            mb_strlen($term) <= 6 => 1,
            mb_strlen($term) <= 10 => 2,
            default => 3,
        };
    }

    /**
     * Optimal string alignment distance: insertions, deletions, substitutions
     * and adjacent transpositions, each costing 1.
     *
     * Case-insensitive, because capitalisation is not a typo.
     */
    public static function distance(string $a, string $b): int
    {
        $a = mb_strtolower($a);
        $b = mb_strtolower($b);

        if ($a === $b) {
            return 0;
        }

        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $m = count($x);
        $n = count($y);

        if ($m === 0 || $n === 0) {
            return $m + $n;
        }

        // Three rows are enough: the transposition case reaches two rows back.
        $twoBack = [];
        $previous = range(0, $n);

        for ($i = 1; $i <= $m; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $n; $j++) {
                $substitute = $x[$i - 1] === $y[$j - 1] ? 0 : 1;

                $current[$j] = min(
                    $previous[$j] + 1,              // delete from $a
                    $current[$j - 1] + 1,           // insert into $a
                    $previous[$j - 1] + $substitute,
                );

                if ($i > 1 && $j > 1 && $x[$i - 1] === $y[$j - 2] && $x[$i - 2] === $y[$j - 1]) {
                    $current[$j] = min($current[$j], $twoBack[$j - 2] + 1);
                }
            }

            $twoBack = $previous;
            $previous = $current;
        }

        return $previous[$n];
    }

    /**
     * The candidate $needle was most likely meant to be, or null when nothing is
     * close enough or two different candidates are equally close.
     *
     * @param  iterable<string>  $candidates
     * @param  int|null  $max  Edit ceiling; defaults to ceiling($needle)
     */
    public static function closest(string $needle, iterable $candidates, ?int $max = null): ?string
    {
        return self::closestOf($needle, self::identity($candidates), $max);
    }

    /**
     * closest() where each searchable string carries a label to return instead
     * of itself — an employee has a name, a surname and a code, and all three
     * should answer with the same person.
     *
     * Two aliases of the *same* label are therefore not an ambiguity; two
     * different labels at the same distance are, and return null.
     *
     * @param  array<string, string>  $labelled  alias => what to return
     */
    public static function closestOf(string $needle, array $labelled, ?int $max = null): ?string
    {
        $tied = self::rank($needle, $labelled, $max);

        return count($tied) === 1 ? reset($tied) : null;
    }

    /**
     * closest() for a caller that would rather guess than say nothing: a tie
     * that survives the first-character preference goes to whichever candidate
     * appeared earliest in $candidates.
     *
     * Only for callers whose guess is visible and reversible. Read the class
     * docblock before adding one.
     *
     * @param  iterable<string>  $candidates  Most likely meaning first
     */
    public static function preferred(string $needle, iterable $candidates, ?int $max = null): ?string
    {
        $tied = self::rank($needle, self::identity($candidates), $max);

        return $tied ? reset($tied) : null;
    }

    /**
     * Labels at the smallest edit distance within the ceiling, in the order
     * their aliases were supplied.
     *
     * A tie is first thinned by the opening character — a misspelling keeps its
     * first letter far more often than not — and only what survives that is
     * handed back as genuinely tied.
     *
     * @param  array<string, string>  $labelled
     * @return list<string>
     */
    private static function rank(string $needle, array $labelled, ?int $max = null): array
    {
        $needle = trim($needle);

        if ($needle === '') {
            return [];
        }

        $max ??= self::ceiling($needle);

        if ($max < 1) {
            return [];
        }

        $length = mb_strlen($needle);
        // Starts at the ceiling and only shrinks, so it doubles as the "close
        // enough at all" test and the "best so far" mark.
        $best = $max;
        $hits = [];

        foreach ($labelled as $alias => $label) {
            $alias = (string) $alias;

            // Every edit changes length by at most one, so a candidate whose
            // length differs by more than the ceiling cannot possibly be within
            // it. Cheap, and it keeps the DP off most of a long candidate list.
            if (abs(mb_strlen($alias) - $length) > $max) {
                continue;
            }

            $distance = self::distance($needle, $alias);

            if ($distance > $best) {
                continue;
            }

            if ($distance < $best) {
                $best = $distance;
                $hits = [];
            }

            $hits[] = ['alias' => $alias, 'label' => $label];
        }

        if (count($hits) > 1) {
            $first = mb_strtolower(mb_substr($needle, 0, 1));
            $sameInitial = array_values(array_filter(
                $hits,
                fn (array $hit) => mb_strtolower(mb_substr($hit['alias'], 0, 1)) === $first,
            ));

            if ($sameInitial) {
                $hits = $sameInitial;
            }
        }

        $labels = [];

        foreach ($hits as $hit) {
            $labels[$hit['label']] = $hit['label'];
        }

        return array_values($labels);
    }

    /**
     * @param  iterable<string>  $candidates
     * @return array<string, string>
     */
    private static function identity(iterable $candidates): array
    {
        $labelled = [];

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            $labelled[$candidate] = $candidate;
        }

        return $labelled;
    }
}
