<?php

namespace Tests\Unit;

use App\Support\FuzzyMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The edit-distance primitive the AI layer's spelling tolerance is built on.
 *
 * Two things here are worth a test rather than a reading. The metric has to price
 * a transposition at one edit, because PHP's own levenshtein() charges two and
 * swapped letters are the most common thing a keyboard produces — get that wrong
 * and "confrim" is as far from "confirm" as an unrelated word. And the ambiguity
 * rules have to differ per caller: a suggestion that could name either of two
 * people must produce nothing, while the typo corrector is allowed to guess. Both
 * behaviours come out of one private ranking function, so they can drift.
 */
class FuzzyMatchTest extends TestCase
{
    /* ─────────────────────────────── distance ─────────────────────────────── */

    public static function distances(): array
    {
        return [
            'identical' => ['confirm', 'confirm', 0],
            'case only' => ['Confirm', 'confirm', 0],
            'one substitution' => ['confirn', 'confirm', 1],
            'one deletion' => ['confrm', 'confirm', 1],
            'one insertion' => ['conffirm', 'confirm', 1],
            // The reason this class exists: levenshtein() scores this 2.
            'one transposition' => ['confrim', 'confirm', 1],
            'two transpositions' => ['ocnfrim', 'confirm', 2],
            'empty against a word' => ['', 'confirm', 7],
            'both empty' => ['', '', 0],
            'nothing in common' => ['zzz', 'abcd', 4],
        ];
    }

    #[DataProvider('distances')]
    public function test_it_counts_edits(string $a, string $b, int $expected): void
    {
        $this->assertSame($expected, FuzzyMatch::distance($a, $b));
        // The metric is symmetric, and half the callers pass the arguments the
        // other way round.
        $this->assertSame($expected, FuzzyMatch::distance($b, $a));
    }

    public function test_a_transposition_costs_less_here_than_in_levenshtein(): void
    {
        $this->assertSame(2, levenshtein('confrim', 'confirm'));
        $this->assertSame(1, FuzzyMatch::distance('confrim', 'confirm'));
    }

    public function test_it_counts_characters_not_bytes(): void
    {
        // "Peña" is 5 bytes and 4 characters. Byte-based counting reads the
        // accented letter as two edits from anything, which would put every
        // Spanish surname in this hospital out of reach of a suggestion.
        $this->assertSame(1, FuzzyMatch::distance('Pena', 'Peña'));
        $this->assertSame(1, FuzzyMatch::distance('Peñs', 'Peña'));
    }

    /* ─────────────────────────────── ceiling ──────────────────────────────── */

    public function test_short_terms_get_no_allowance_at_all(): void
    {
        // At three characters almost every edit lands on another real word, so
        // the ceiling is zero and rank() bails before comparing anything.
        $this->assertSame(0, FuzzyMatch::ceiling('cat'));
        $this->assertNull(FuzzyMatch::closest('cat', ['car', 'can', 'cap']));
        $this->assertNull(FuzzyMatch::preferred('cat', ['car', 'can', 'cap']));
    }

    public function test_the_allowance_grows_with_length(): void
    {
        $this->assertSame(1, FuzzyMatch::ceiling('cycle'));
        $this->assertSame(2, FuzzyMatch::ceiling('competency'));
        $this->assertSame(3, FuzzyMatch::ceiling('recognition'));
    }

    /* ─────────────────────────────── closest ──────────────────────────────── */

    public function test_it_finds_the_intended_candidate(): void
    {
        $this->assertSame('succession', FuzzyMatch::closest('sucession', ['learning', 'succession', 'recognition']));
    }

    public function test_nothing_within_the_ceiling_is_not_a_match(): void
    {
        $this->assertNull(FuzzyMatch::closest('bicycle', ['succession', 'recognition']));
    }

    public function test_an_explicit_ceiling_overrides_the_length_default(): void
    {
        // Two dropped letters. "succession" would allow two edits by default, so
        // the ceiling is what decides this either way.
        $this->assertSame(2, FuzzyMatch::distance('sucesion', 'succession'));
        $this->assertSame('succession', FuzzyMatch::closest('sucesion', ['succession'], 2));
        $this->assertNull(FuzzyMatch::closest('sucesion', ['succession'], 1));
    }

    public function test_two_different_candidates_at_the_same_distance_are_not_a_match(): void
    {
        // Both are one edit away and both start with the same letter, so nothing
        // separates them. Guessing between two people is the mistake this rule
        // exists to prevent.
        $this->assertNull(FuzzyMatch::closest('santoz', ['santos', 'santon']));
    }

    public function test_a_nearer_candidate_beats_a_tie_further_out(): void
    {
        $this->assertSame(
            'competency',
            FuzzyMatch::closest('competancy', ['competency', 'consistency', 'compliancy'], 3),
        );
    }

    public function test_an_empty_needle_matches_nothing(): void
    {
        $this->assertNull(FuzzyMatch::closest('   ', ['cycle', 'course']));
        $this->assertNull(FuzzyMatch::closest('', ['cycle', 'course']));
    }

    public function test_an_empty_candidate_list_matches_nothing(): void
    {
        $this->assertNull(FuzzyMatch::closest('cycle', []));
        $this->assertNull(FuzzyMatch::preferred('cycle', []));
        $this->assertNull(FuzzyMatch::closestOf('cycle', []));
    }

    /* ────────────────────────────── closestOf ─────────────────────────────── */

    public function test_several_aliases_of_one_label_are_not_an_ambiguity(): void
    {
        // An employee is reachable by full name, surname and code. All three
        // point at the same person, so two of them tying is not a tie at all.
        $labelled = [
            'Juan Dela Cruz' => 'Juan Dela Cruz (EMP-0001)',
            'Dela Cruz' => 'Juan Dela Cruz (EMP-0001)',
            'Juan' => 'Juan Dela Cruz (EMP-0001)',
        ];

        $this->assertSame('Juan Dela Cruz (EMP-0001)', FuzzyMatch::closestOf('Dela Cruze', $labelled));
    }

    public function test_two_different_labels_at_the_same_distance_return_nothing(): void
    {
        $labelled = [
            'Reyes' => 'Ana Reyes (EMP-0002)',
            'Reyez' => 'Ben Reyez (EMP-0003)',
        ];

        $this->assertNull(FuzzyMatch::closestOf('Reyer', $labelled));
    }

    /* ────────────────────────────── preferred ─────────────────────────────── */

    public function test_preferred_settles_a_tie_by_the_order_given(): void
    {
        // The corrector's licence to guess: "revew" really is one edit from both,
        // and reading it as neither helps nobody.
        $this->assertSame('review', FuzzyMatch::preferred('revew', ['review', 'renew']));
        $this->assertSame('renew', FuzzyMatch::preferred('revew', ['renew', 'review']));

        // The strict rule on the same input is the whole difference between them.
        $this->assertNull(FuzzyMatch::closest('revew', ['review', 'renew']));
    }

    public function test_preferred_still_respects_the_ceiling(): void
    {
        // Ordering candidates does not lower the bar for being close at all.
        $this->assertNull(FuzzyMatch::preferred('bicycle', ['succession', 'recognition']));
    }

    /* ──────────────────────── first-character preference ──────────────────── */

    public function test_a_tie_is_thinned_by_the_opening_letter(): void
    {
        // "crate" is one edit from both, but a typist keeps the first letter far
        // more often than not — so this is unambiguous even under the strict rule.
        $this->assertSame('create', FuzzyMatch::closest('crate', ['create', 'rate'], 1));
    }

    public function test_the_preference_does_not_invent_a_match_out_of_reach(): void
    {
        // Sharing a first letter is a tie-break, never a substitute for being
        // close: nothing here is within one edit.
        $this->assertNull(FuzzyMatch::closest('crocodile', ['create', 'course'], 1));
    }

    public function test_the_preference_yields_when_no_candidate_shares_the_initial(): void
    {
        // Thinning to nothing would turn a perfectly good single match into a
        // miss, so the filter only applies when something survives it.
        $this->assertSame('rate', FuzzyMatch::closest('bate', ['rate'], 1));
    }
}
