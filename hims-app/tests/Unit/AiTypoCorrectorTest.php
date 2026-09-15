<?php

namespace Tests\Unit;

use App\Http\Controllers\AiController;
use App\Services\Ai\AiTypoCorrector;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * The spelling pass that runs on every chat message before any gate sees it.
 *
 * THE TEST THAT MATTERS IS THE ONE THAT ASSERTS NOTHING HAPPENED
 *
 * A missed typo leaves today's behaviour intact. A wrongly "corrected" word
 * changes what the assistant was asked to do, and the worst case is a person's
 * name in a message that ends in a delete. So the bulk of this file is a corpus:
 * four hundred common English words, realistic HIMS sentences, Filipino given
 * names and surnames, and the words that arm a destructive confirmation — every
 * one of which must come through untouched.
 *
 * The corpus is not decoration. Six vocabulary entries and four NEVER_CORRECT
 * entries exist *because* a sweep over this exact text found them: "manage" was
 * being read as "manager", "ending" as "pending", "deletion" as "deleting". The
 * only way to add safely to either list is to widen the corpus and re-run.
 *
 * Pure string work, no database, so the whole file stays on the sqlite suite.
 */
class AiTypoCorrectorTest extends TestCase
{
    private AiTypoCorrector $corrector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corrector = new AiTypoCorrector;
    }

    /* ══════════════════════ what must never be touched ══════════════════════ */

    /**
     * Common English, one word per case. Any correction here is a false positive.
     *
     * @return array<string, array{string}>
     */
    public static function englishWords(): array
    {
        $words = <<<'WORDS'
        about above across after again against almost alone along already also
        although always among amount another answer anyone anything appear around
        arrive asked away back become before begin behind being believe below
        beside better between beyond both bring build called cannot carry cause
        change chart check child children choose clear close coming common company
        complete consider continue could country course create cross current decide
        decision deep design detail develop difference different difficult direct
        doing during early effect either enough enter entire equal even evening
        event every example except exist expect explain family father feeling field
        figure finally find first follow force forward found friend front further
        future general given going great group grown guess happen hard head health
        hear heart help high history hold home hope house however human hundred
        idea important include increase indeed inside instead interest issue itself
        join keep kind knew know large last late later laugh lead learn least leave
        left less letter level light like likely line listen little live local long
        look lose love machine main major make manage manner many market matter
        maybe mean measure meet member mention middle might mind minute miss moment
        money month more morning most mother move movement much music must myself
        name nation nature near need never next night none normal north note
        nothing notice number object observe obtain occur offer office often once
        only open operate opinion order other ought outside over overall paper
        parent part particular party pass past pattern people perhaps period person
        picture piece place plan play please point policy political poor popular
        position possible power practice prepare present pretty prevent price
        probably problem produce program project proper provide public pull purpose
        push quality question quickly quite rather reach read ready real reason
        receive recent record reduce refer reflect regard region relate remain
        remember report represent require research resource respond rest result
        return reveal right rise road role room round rule safe same save saying
        school science season second section security seem sell send sense separate
        serious serve service several shall share short should show side sign
        similar simple since single sister site situation size skill small social
        society some someone something sometimes soon sort sound source south space
        speak special specific spend spring staff stage stand standard star start
        state statement stay step still stop store story straight strategy street
        strong structure student study stuff subject succeed success such sudden
        suffer suggest summer support suppose sure surface system table take talk
        teach team tell term test than thank that their theme then theory there
        these thing think third this those though thought three through throw thus
        time today together told tomorrow tonight took total touch toward town
        trade tradition train travel treat tree trial trip trouble true trust
        truth turn twice type under understand union unit until upon usually value
        various very view visit voice wait walk wall want watch water wear week
        weight welcome well went were what when where whether which while white
        whole whom whose wide wife will wind window wish with within without woman
        wonder word work world worry would write wrong yard year yesterday young
        WORDS;

        $cases = [];

        foreach (preg_split('/\s+/', trim($words)) as $word) {
            if ($word !== '') {
                $cases[$word] = [$word];
            }
        }

        return $cases;
    }

    #[DataProvider('englishWords')]
    public function test_it_leaves_ordinary_english_alone(string $word): void
    {
        $reading = $this->corrector->correct($word);

        $this->assertSame([], $reading['changes'], "\"{$word}\" was rewritten and should not have been");
        $this->assertSame($word, $reading['text']);
    }

    /**
     * Filipino given names and surnames as a person would type them in a hurry —
     * lowercase, so the proper-noun exemption does not save them and the word
     * lists have to.
     *
     * @return array<string, array{string}>
     */
    public static function lowercaseNames(): array
    {
        $names = <<<'NAMES'
        santos reyes cruz bautista ocampo garcia mendoza torres ramos gonzales
        lopez flores villanueva fernandez francisco rivera aquino navarro salvador
        castillo domingo alvarez mercado pascual dizon guerrero espinosa manalo
        javier soriano magno tolentino valdez cordero abad andrada arceo
        maria josefa juan pedro ricardo elena carmen luzviminda rosario teresita
        angelo miguel rafael antonio benigno corazon imelda dolores marilou
        NAMES;

        $cases = [];

        foreach (preg_split('/\s+/', trim($names)) as $name) {
            if ($name !== '') {
                $cases[$name] = [$name];
            }
        }

        return $cases;
    }

    #[DataProvider('lowercaseNames')]
    public function test_it_leaves_a_lowercase_person_name_alone(string $name): void
    {
        $this->assertSame([], $this->corrector->correct($name)['changes'],
            "\"{$name}\" is a name and was rewritten");
    }

    /**
     * Whole messages of the kind this hospital's staff actually send. None of
     * them is misspelt, so none of them may change by a single character.
     *
     * @return array<string, array{string}>
     */
    public static function cleanMessages(): array
    {
        return array_map(fn (string $m) => [$m], [
            'How do I record a course completion for one of my direct reports?',
            'Where can I see the credentials that are expiring this month?',
            'Please show me the outstanding required training for the Nursing department.',
            'What is the difference between the supervisor rating and the final score?',
            'Who has not acknowledged their performance review yet?',
            'I would like to know which competencies my team is below requirement on.',
            'Can you explain how the renewal cycle counts my verified CPD hours?',
            'Create a 2027 annual review cycle starting 2027-01-01 and ending 2027-12-31.',
            'Delete the login account for admin@hospital.ph please.',
            'Assign the Infection Control course to every nurse in the ICU.',
            'Nominate Maria Santos as a candidate for the Chief Nurse position.',
            'Log 3 CPD hours for the IV Therapy workshop I attended last week.',
            'Mark employee EMP-0042 as resigned effective today.',
            'Register me for the upcoming Basic Life Support session on 2026-09-01.',
            'My phone number changed, how do I update my own profile?',
            'The report says my hours are wrong and I want to check the audit trail.',
            'Is there a way to see every department at once, or only mine?',
            'Set the status of the Q3 cycle to finished.',
            'Withdraw the nomination for Juan Dela Cruz.',
            'Who manages the succession plan for the Chief Nurse position?',
            'Confirm the deletion of that account.',
            'thanks, that worked',
            'what about the other one',
            'who else reports to me',
            'i think the date is wrong on that record',
            'please close it and let me know',
        ]);
    }

    #[DataProvider('cleanMessages')]
    public function test_it_leaves_a_correctly_spelt_message_alone(string $message): void
    {
        $reading = $this->corrector->correct($message);

        $this->assertSame([], $reading['changes'],
            'rewrote: '.json_encode($reading['changes'], JSON_UNESCAPED_UNICODE));
        $this->assertSame($message, $reading['text']);
    }

    /**
     * The words that arm a destructive action. AiController matches these against
     * the raw message, but they are pinned here too: if a future vocabulary entry
     * pulled "confirm" into something else, the corrected text would disagree with
     * the raw text about whether consent had been given.
     *
     * @return array<string, array{string}>
     */
    public static function confirmationWords(): array
    {
        return array_map(fn (string $w) => [$w], [
            'confirm', 'confirms', 'confirmed', 'confirmation',
            'proceed', 'go ahead', 'do it', 'yes', 'cancel', 'cancelled',
            'confirm the deletion', 'proceed with it', 'no do not',
        ]);
    }

    #[DataProvider('confirmationWords')]
    public function test_it_never_reshapes_a_confirmation(string $phrase): void
    {
        $this->assertSame([], $this->corrector->correct($phrase)['changes']);
    }

    /* ═══════════════════════ what must be corrected ═════════════════════════ */

    /**
     * message => the word the reading must contain.
     *
     * Every verb here is one AiController::ACTION_VERBS gates on, so before this
     * class existed each of these messages was answered as a question about how
     * one might do the thing, and nothing said the instruction had been missed.
     *
     * @return array<string, array{string, string}>
     */
    public static function misspeltMessages(): array
    {
        $cases = [
            'crate a 2027 annual review cycle' => 'create',
            'delet the user bob' => 'delete',
            'plese asign the infection control course to the nurses' => 'assign',
            'nomiate maria santos for the chief nurse position' => 'nominate',
            'updaet my profile phone number' => 'update',
            'wtihdraw the nomination for juan' => 'withdraw',
            'reactivat the account for the new nurse' => 'reactivate',
            'schedual a training session for next week' => 'schedule',
            'creat a new revew cycle' => 'create',
            'aprove the cpd record' => 'approve',
            'termiate the employee' => 'terminate',
            'rgister me for the session' => 'register',
            'deacitvate that account' => 'deactivate',
            'show me the compentency gaps' => 'competency',
            'where are the credentails' => 'credentials',
            'open the sucession module' => 'succession',
            'i need the complaince report' => 'compliance',
            'list the traning sessions' => 'training',
            'my cpd huors are wrong' => 'hours',
            'the performace review is locked' => 'performance',
        ];

        $out = [];

        foreach ($cases as $message => $expected) {
            $out[$message] = [$message, $expected];
        }

        return $out;
    }

    #[DataProvider('misspeltMessages')]
    public function test_it_repairs_a_misspelt_instruction(string $message, string $expected): void
    {
        $reading = $this->corrector->correct($message);

        $this->assertContains($expected, $reading['changes'],
            "expected \"{$expected}\" in the reading of \"{$message}\", got "
            .json_encode($reading['changes'], JSON_UNESCAPED_UNICODE));

        $this->assertStringContainsString($expected, $reading['text']);
    }

    public function test_a_transposition_is_repaired_like_any_other_slip(): void
    {
        // The whole reason FuzzyMatch does not use levenshtein(): at the default
        // ceiling a swap priced at two edits would be out of reach.
        $this->assertSame(['comeptency' => 'competency'],
            $this->corrector->correct('comeptency gaps')['changes']);
    }

    /* ══════════════════════════ the exemptions ══════════════════════════════ */

    public function test_a_quoted_span_is_copied_through_verbatim(): void
    {
        // A title in quotes is the one part of a message the person has said
        // outright that they typed on purpose.
        $message = 'Rename the cycle to "Q1 Reveiw Cyle" exactly as written';

        $this->assertSame([], $this->corrector->correct($message)['changes']);
    }

    public function test_an_identifier_is_copied_through_verbatim(): void
    {
        foreach ([
            'the employee code is EMP-0042 and the email is j.reyes@hospital.ph',
            'set the date to 2026-09-01',
            'his 9-box label is 3B',
            'the column is employee_frist_name',
        ] as $message) {
            $this->assertSame([], $this->corrector->correct($message)['changes'],
                "identifier altered in: {$message}");
        }
    }

    public function test_a_capitalised_word_mid_sentence_is_left_alone(): void
    {
        // Two surnames one edit from a domain word. Reading "Cruze" as "course"
        // would turn a resolvable name into a baffling lookup failure.
        $this->assertSame([], $this->corrector->correct('nominate Reyes for the position')['changes']);
        $this->assertSame([], $this->corrector->correct('delete Cruze from the roster')['changes']);
    }

    public function test_a_capital_that_opens_a_sentence_is_still_corrected(): void
    {
        // That capital is grammar, not a name, so the exemption does not apply —
        // and the correction keeps the shape the person typed.
        $this->assertSame(['Crate' => 'Create'], $this->corrector->correct('Crate a cycle')['changes']);
        $this->assertSame('Create a cycle', $this->corrector->correct('Crate a cycle')['text']);
    }

    public function test_case_is_restored_from_the_original_token(): void
    {
        $this->assertSame('CREATE', $this->corrector->correct('CRATE a cycle')['changes']['CRATE']);
        $this->assertSame('create', $this->corrector->correct('crate a cycle')['changes']['crate']);
    }

    public function test_a_word_below_the_minimum_length_is_never_corrected(): void
    {
        // "cyle" is plainly "cycle" to a reader, and just as plainly one edit from
        // cycle, mile, tile, tale and file to this class. Guessing at four
        // characters is a coin toss, and the message still reaches the model.
        $this->assertSame([], $this->corrector->correct('a new cyle')['changes']);
    }

    public function test_it_stops_after_three_corrections(): void
    {
        $reading = $this->corrector->correct('crate a new revew cyle for the compentency asessment progam');

        $this->assertCount(3, $reading['changes']);
        // The first three are applied and the rest of the message is untouched —
        // a message needing four corrections was misunderstood, not mistyped.
        $this->assertStringContainsString('asessment progam', $reading['text']);
    }

    /* ═════════════════════════════ the note ═════════════════════════════════ */

    public function test_no_note_when_nothing_was_read_differently(): void
    {
        $this->assertSame('', $this->corrector->note([]));
    }

    public function test_the_note_names_every_word_it_re_read(): void
    {
        $this->assertSame('Read “crate” as “create”.',
            $this->corrector->note(['crate' => 'create']));

        $this->assertSame('Read “crate” as “create” and “revew” as “review”.',
            $this->corrector->note(['crate' => 'create', 'revew' => 'review']));

        $this->assertSame('Read “a” as “b”, “c” as “d” and “e” as “f”.',
            $this->corrector->note(['a' => 'b', 'c' => 'd', 'e' => 'f']));
    }

    public function test_the_note_carries_no_markdown(): void
    {
        // The AI rail writes replies with textContent, so an asterisk renders as
        // an asterisk. Typographic quotes are the emphasis available here.
        $note = $this->corrector->note(['crate' => 'create']);

        $this->assertStringNotContainsString('*', $note);
        $this->assertStringNotContainsString('_', $note);
    }

    /* ═══════════════════════════ the contract ═══════════════════════════════ */

    public function test_every_verb_the_controller_gates_on_is_in_the_vocabulary(): void
    {
        // The point of the whole class: ACTION_VERBS decides whether a message is
        // worth classifying, so a verb it tests for that this class has never
        // heard of is a verb no misspelling of which will ever be repaired. The
        // regex is read off the controller rather than copied, so the two cannot
        // drift apart silently.
        $constants = (new ReflectionClass(AiController::class))->getConstants();

        $this->assertArrayHasKey('ACTION_VERBS', $constants,
            'AiController::ACTION_VERBS was renamed; this contract needs updating');

        preg_match_all('/[a-z]{3,}/', $constants['ACTION_VERBS'], $matches);

        $verbs = array_unique($matches[0]);

        // A regex that stopped matching would pass an empty scan silently.
        $this->assertGreaterThanOrEqual(40, count($verbs),
            'the verb scan found almost nothing — the regex shape changed');

        $vocabulary = AiTypoCorrector::vocabulary();
        $missing = array_values(array_diff($verbs, $vocabulary));

        $this->assertSame([], $missing,
            'AiController gates on these verbs but AiTypoCorrector does not know them: '
            .implode(', ', $missing));
    }

    public function test_the_vocabulary_and_the_stop_list_do_not_overlap(): void
    {
        // A word in both is a contradiction: VOCABULARY says "already correct and
        // also a target for near-misses", NEVER_CORRECT says "never a target".
        // known() is consulted first, so the stop-list entry would be dead text
        // and the next reader would trust it.
        $reflection = new ReflectionClass(AiTypoCorrector::class);
        $stopList = $reflection->getConstant('NEVER_CORRECT');

        $overlap = array_values(array_intersect(AiTypoCorrector::vocabulary(), $stopList));

        $this->assertSame([], $overlap,
            'listed as both known and never-correctable: '.implode(', ', $overlap));
    }

    public function test_the_reading_is_the_message_when_nothing_changed(): void
    {
        // Callers compare $reading['text'] !== $prompt to decide whether to
        // re-run the topic gate, so an untouched message must be identical and
        // not merely equivalent.
        $message = 'Please show me the outstanding required training.';

        $this->assertSame($message, $this->corrector->correct($message)['text']);
    }
}
