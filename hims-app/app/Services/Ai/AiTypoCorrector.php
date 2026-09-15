<?php

namespace App\Services\Ai;

use App\Support\FuzzyMatch;

/**
 * Repairs misspelt HIMS words in a chat message so a mistyped instruction still
 * reaches the machinery that would have carried it out, and reports what it read
 * so the person can see the assistant's reading of their words.
 *
 * WHY A DETERMINISTIC PASS AND NOT "the model will cope"
 *
 * The model does cope — with prose. The parts of this pipeline that decide
 * whether a message is even *worth* asking the model about do not: AiController's
 * ACTION_VERBS regex is a literal word list, so "crate a 2027 cycle" fails it,
 * the planner is never consulted, and the message falls through to a
 * conversational answer about how one might go about creating a cycle. The
 * instruction was understood by nobody and nothing said so. Every gate in front
 * of the model is spelling-sensitive by construction, so the correction has to
 * happen before them.
 *
 * WHAT IT WILL NOT TOUCH, AND WHY EACH EXCLUSION EARNS ITS PLACE
 *
 * The failure to avoid is not "a typo went uncorrected" — that is today's
 * behaviour and it is survivable. It is rewriting a word the person meant, most
 * of all a person's name in an instruction that ends in a delete. So:
 *
 *  - Fewer than 5 characters, never. Four-letter words sit one edit from half the
 *    vocabulary: "post" alone is one edit from cost, host, lost, most, past and
 *    port. At that length a correction is a coin toss.
 *  - Anything quoted stays verbatim. A title in quotes is the one part of a
 *    message the person has explicitly told you they typed on purpose.
 *  - Any run containing a digit, "@" or "_" stays verbatim: employee codes,
 *    emails, dates and 9-box labels are identifiers, and an identifier one edit
 *    from a real word is still not that word.
 *  - A capitalised word mid-sentence stays verbatim. English capitalises proper
 *    nouns there, and mangling "Reyes" into a domain term turns a resolvable name
 *    into a confusing lookup failure. The cost is a missed typo in a name-cased
 *    message, which leaves today's behaviour intact; the benefit is that surnames
 *    are out of reach. The first word of a sentence is exempt from the exemption,
 *    because that capital is grammar rather than a name.
 *  - At most three corrections in one message. A message needing four is not a
 *    mistyped instruction, it is text this class has misunderstood, and rewriting
 *    it wholesale would be a guess dressed as a reading.
 *
 * VOCABULARY AND NEVER_CORRECT DO DIFFERENT JOBS
 *
 * VOCABULARY is every word the assistant recognises — verbs in the tenses people
 * type, nouns singular and plural, statuses, module names. A token in it is
 * already right and is left alone; it is also what a misspelling is matched
 * against. That is why inflections are listed even though nobody would call them
 * distinct concepts: without "moved" in the vocabulary, "moved" is an unknown
 * word one edit from "move" and this class would helpfully break the tense.
 *
 * NEVER_CORRECT is the ordinary English that survives all of the rules above and
 * still lands within reach of a domain word — "employed" beside "employee",
 * "store" beside "score". Every entry was found by running the sweep in
 * Unit\AiTypoCorrectorTest over common English and realistic HIMS sentences, not
 * by imagination. Add to it the same way.
 */
final class AiTypoCorrector
{
    /** Shorter than this and a correction is a guess. See the class docblock. */
    private const MIN_LENGTH = 5;

    /** A message needing more than this was not understood, only rewritten. */
    private const MAX_CORRECTIONS = 3;

    /**
     * Every word the assistant recognises.
     *
     * Grouped for reading, flattened for use. Lowercase throughout: matching
     * folds case, and capitalisation is restored from the original token.
     *
     * @var array<string, list<string>>
     */
    private const VOCABULARY = [
        // The verbs AiController::ACTION_VERBS tests for, plus the tenses people
        // actually type. Unit\AiTypoCorrectorTest asserts none of that regex's
        // words is missing here.
        'verbs' => [
            'create', 'creating', 'created', 'creates',
            'add', 'adding', 'added', 'adds', 'new',
            'delete', 'deleting', 'deleted', 'deletes',
            'remove', 'removing', 'removed', 'removes',
            'update', 'updating', 'updated', 'updates',
            'change', 'changing', 'changed', 'changes',
            'edit', 'editing', 'edited', 'edits',
            'set', 'sets', 'setting', 'settings',
            'assign', 'assigning', 'assigned', 'assigns',
            'nominate', 'nominating', 'nominated', 'nomination', 'nominations',
            'withdraw', 'withdrawing', 'withdrawn', 'withdraws',
            'verify', 'verifying', 'verified', 'verifies', 'verification',
            'approve', 'approving', 'approved', 'approves', 'approval',
            'enrol', 'enroll', 'enrolling', 'enrolled', 'enrolment', 'enrollment',
            'register', 'registering', 'registered', 'registration', 'registers',
            'log', 'logging', 'logged', 'logs',
            'record', 'recording', 'recorded', 'records',
            'post', 'posting', 'posted', 'posts',
            'schedule', 'scheduling', 'scheduled', 'schedules',
            'score', 'scoring', 'scored', 'scores',
            'rate', 'rating', 'rated', 'rates',
            'submit', 'submitting', 'submitted', 'submits', 'submission',
            'rename', 'renaming', 'renamed', 'renames',
            'close', 'closing', 'closed', 'closes',
            'open', 'opening', 'opened', 'opens',
            'activate', 'activating', 'activated',
            'reactivate', 'reactivating', 'reactivated',
            'deactivate', 'deactivating', 'deactivated',
            'restore', 'restoring', 'restored', 'restores',
            'reinstate', 'reinstating', 'reinstated',
            'suspend', 'suspending', 'suspends',
            'terminate', 'terminating', 'terminates', 'termination',
            'promote', 'promoting', 'promoted', 'promotion',
            'transfer', 'transferring', 'transferred', 'transfers',
            'move', 'moving', 'moved', 'moves',
            'mark', 'marking', 'marked', 'marks',
            'make', 'making', 'makes',
            'check', 'checking', 'checked', 'checkin', 'checkins',
            'show', 'showing', 'list', 'lists', 'listing', 'find',
            'search', 'searches', 'searching',
            'manage', 'manages', 'managing', 'management',
            'require', 'requires', 'requiring',
            'start', 'starting', 'started', 'starts', 'end', 'ending', 'ends',
            // Noun forms, because a verb's noun is an unknown word one edit from
            // its own participle: "deletion" was read as "deleting" until it was
            // listed here.
            'deletion', 'deletions', 'creation', 'removal', 'addition', 'closure',
        ],

        // Records and the modules that own them.
        'records' => [
            'employee', 'employees', 'department', 'departments',
            'performance', 'review', 'reviews', 'cycle', 'cycles',
            'competency', 'competencies', 'assessment', 'assessments',
            'credential', 'credentials', 'certificate', 'certificates',
            'certification', 'licence', 'license', 'accreditation',
            'course', 'courses', 'session', 'sessions', 'training', 'train',
            'trains', 'program', 'programs', 'workshop', 'workshops',
            'learning', 'compliance', 'renewal', 'renewals',
            'pathway', 'pathways', 'assignment', 'assignments',
            'recognition', 'badge', 'badges', 'venue', 'venues',
            'feedback', 'roster', 'succession', 'candidate', 'candidates',
            'milestone', 'milestones', 'position', 'positions',
            'notification', 'notifications', 'dashboard', 'report', 'reports',
            'account', 'accounts', 'login', 'password', 'user', 'users',
            'role', 'roles', 'supervisor', 'supervisors', 'manager', 'managers',
            'profile', 'profiles', 'audit', 'trail', 'history',
            'analysis', 'matrix', 'domain', 'domains', 'rule', 'rules',
            'progression', 'timeline', 'readiness', 'potential',
            'mentor', 'mentors', 'nominee', 'nominees',
        ],

        // Fields and the values they hold.
        'fields' => [
            'name', 'names', 'title', 'titles', 'code', 'codes',
            'date', 'dates', 'start', 'end',
            'description', 'type', 'types', 'status', 'email', 'phone',
            'note', 'notes', 'comment', 'comments', 'reason', 'reasons', 'basis',
            'response', 'responses', 'weight', 'weighting', 'weighted',
            'target', 'targets', 'subject', 'subjects', 'level', 'levels',
            'hour', 'hours', 'total', 'average', 'overall', 'final',
            'required', 'requirement', 'requirements', 'deadline',
            'active', 'inactive', 'probationary', 'probation', 'regular',
            'resigned', 'terminated', 'suspended',
            'annual', 'quarterly', 'midyear', 'draft', 'finished', 'completed',
            'complete', 'pending', 'ongoing', 'cancelled', 'attended', 'absent',
            'internal', 'external', 'public', 'private',
            'expired', 'expiring', 'expires', 'expiry', 'renew', 'renewing',
            'overdue', 'outstanding', 'upcoming', 'critical', 'ready',
            'developing', 'immediate', 'high', 'medium', 'department',
        ],

        // Words that only mean anything inside this hospital's system.
        'hims' => [
            'hims', 'jci', 'cpd', 'kpi', 'kpis', 'hospital', 'staff',
            'nurse', 'nurses', 'doctor', 'doctors', 'admin', 'administrator',
            'gap', 'gaps', 'attrition', 'turnover', 'headcount', 'payroll',
            'salary', 'standard', 'standards', 'acknowledge', 'acknowledged',
        ],
    ];

    /**
     * Ordinary English that reaches a vocabulary word and must not be pulled
     * into it. Derived by sweep, not by guesswork — see the class docblock.
     *
     * @var list<string>
     */
    private const NEVER_CORRECT = [
        // The words that arm a destructive action. Deliberately here rather than
        // in VOCABULARY: listing them there would leave them untouched too, but
        // it would also make them targets, so "confrim" would be read as
        // "confirm" and a mistyped word would fire a delete. AiController tests
        // the raw message for these anyway — this is the belt to that braces, so
        // no future vocabulary entry can reshape one of them.
        'confirm', 'confirms', 'confirmed', 'confirmation', 'proceed', 'ahead',
        'cancel', 'cancels',
        // one edit from "employee"
        'employed', 'employer', 'employers',
        // one edit from "score" / "store" / "restore" / "search"
        'store', 'stored', 'stores', 'scope', 'scopes', 'snore',
        'research', 'searing', 'seared', 'stuff',
        // one edit from "rate" / "date" / "later"
        'later', 'rather', 'grate', 'irate',
        // one edit from "close" / "closed"
        'clone', 'cloned', 'clones', 'clothes', 'chose', 'those', 'whose',
        // one edit from "open" / "opens"
        'oxen', 'omen', 'often',
        // one edit from "mark" / "make" / "move" / "marked"
        'made', 'maker', 'march', 'marsh', 'movie', 'movies', 'mover',
        'market', 'markets',
        // one edit from "post" / "posts" / "posted"
        'past', 'pasted', 'hosted', 'costs', 'hosts', 'posit', 'ports',
        // one edit from "note" / "notes" / "nominee"
        'noted', 'nope', 'money', 'monies',
        // one edit from "role" / "roles" / "rules" / "rule"
        'rolled', 'ruler', 'holes', 'poles', 'moles', 'roads',
        // one edit from "level" / "levels"
        'lever', 'levers', 'seven',
        // one edit from "list" / "least" / "last"
        'last', 'lost', 'fist',
        // one edit from "check" / "checked"
        'cheek', 'cheeks', 'chick', 'chunk',
        // one edit from "type" / "title"
        'typed', 'tithe', 'tile', 'tiles', 'idle',
        // one edit from "start" / "status" / "state"
        'state', 'states', 'stars', 'smart', 'chart', 'apart',
        // one edit from "final" / "trail" / "total"
        'trial', 'trails', 'trait', 'tonal', 'vital', 'fatal', 'petal',
        // one edit from "hours" / "hour" / "your"
        'yours', 'house', 'houses', 'four', 'sour', 'tour', 'pour', 'hound',
        // one edit from "high"
        'sigh', 'thigh', 'night', 'light', 'might', 'right', 'sight', 'tight',
        // one edit from "ready" / "readiness"
        'reads', 'realm', 'bread', 'dread', 'treat', 'great',
        // one edit from "name" / "names"
        'game', 'games', 'fame', 'lame', 'tame', 'nape',
        // one edit from "code" / "coded" / "mode"
        'mode', 'modes', 'coded', 'cove', 'core', 'cord', 'cold',
        // one edit from "reason" / "season"
        'season', 'seasons', 'region', 'regions',
        // ordinary sentence furniture that happens to land near something
        'these', 'there', 'their', 'where', 'while', 'which', 'other', 'under',
        'about', 'after', 'again', 'below', 'could', 'would', 'should', 'shall',
        'still', 'thing', 'things', 'think', 'thanks', 'please', 'sorry',
        'wrong', 'first', 'second', 'third', 'today', 'tomorrow',
        'yesterday', 'week', 'weeks', 'month', 'months', 'year', 'years',
        'someone', 'anyone', 'everyone', 'nobody', 'myself', 'himself',
        'herself', 'themselves', 'because', 'before', 'between', 'during',
        'without', 'within', 'since', 'until', 'unless', 'whether',
    ];

    /**
     * Read the message with its misspellings repaired.
     *
     * `text` is the message as this class read it — identical to the input when
     * nothing was corrected. `changes` maps each original token to what it was
     * read as, in the order they appeared, and is empty in the common case.
     *
     * @return array{text: string, changes: array<string, string>}
     */
    public function correct(string $prompt): array
    {
        $changes = [];
        $spans = $this->verbatimSpans($prompt);

        $text = preg_replace_callback(
            '/\p{L}+/u',
            function (array $match) use ($prompt, $spans, &$changes): string {
                [$token, $offset] = $match[0];

                if (count($changes) >= self::MAX_CORRECTIONS) {
                    return $token;
                }

                $correction = $this->correctToken($token, $offset, $prompt, $spans);

                if ($correction === null) {
                    return $token;
                }

                $changes[$token] = $correction;

                return $correction;
            },
            $prompt,
            -1,
            $count,
            PREG_OFFSET_CAPTURE,
        );

        // preg_replace_callback returns null only on a PCRE failure (a backtrack
        // limit on a pathological message). Reading the message as typed is the
        // right answer then, not an exception on the chat endpoint.
        return ['text' => $text ?? $prompt, 'changes' => $text === null ? [] : $changes];
    }

    /**
     * The sentence shown above a reply when the reading differed from the words.
     *
     * Plain quotes and no markdown: the rail writes replies with textContent, so
     * asterisks would render as asterisks.
     *
     * @param  array<string, string>  $changes  From correct()
     */
    public function note(array $changes): string
    {
        if (! $changes) {
            return '';
        }

        $pairs = [];

        foreach ($changes as $from => $to) {
            $pairs[] = "“{$from}” as “{$to}”";
        }

        $last = array_pop($pairs);
        $list = $pairs ? implode(', ', $pairs).' and '.$last : $last;

        return "Read {$list}.";
    }

    /**
     * Every word the assistant recognises, flattened and deduplicated.
     *
     * Public so Unit\AiTypoCorrectorTest can hold it against
     * AiController::ACTION_VERBS: a verb the controller gates on and this class
     * has never heard of is a verb no typo of which will ever be repaired.
     *
     * @return list<string>
     */
    public static function vocabulary(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::VOCABULARY))));
    }

    /** What this token should be read as, or null to leave it alone. */
    private function correctToken(string $token, int $offset, string $prompt, array $spans): ?string
    {
        if (mb_strlen($token) < self::MIN_LENGTH) {
            return null;
        }

        if ($this->isVerbatim($offset, strlen($token), $spans)) {
            return null;
        }

        if ($this->isProperNoun($token, $offset, $prompt)) {
            return null;
        }

        $lower = mb_strtolower($token);

        if (isset(self::known()[$lower]) || isset(self::protectedWords()[$lower])) {
            return null;
        }

        // Tighter than FuzzyMatch's default ceiling: this rewrites text rather
        // than offering a suggestion, so two edits are only allowed once a word
        // is long enough that two edits cannot reach a different word.
        $limit = mb_strlen($token) >= 8 ? 2 : 1;

        // preferred(), not closest(): a tie that survives the first-character
        // preference is settled by vocabulary order — verbs, then records, then
        // fields — because "revew" is genuinely one edit from both review and
        // renew and reading it as neither helps nobody. The guess is shown to the
        // person in the reply and only reshapes text, which is the whole of what
        // licenses it. See FuzzyMatch's docblock.
        $match = FuzzyMatch::preferred($lower, array_keys(self::known()), $limit);

        if ($match === null || $match === $lower) {
            return null;
        }

        return $this->matchCase($token, $match);
    }

    /**
     * Byte ranges to copy through untouched: quoted spans, and any
     * whitespace-delimited run carrying a digit, "@" or "_".
     *
     * @return list<array{int, int}> [start, end) pairs
     */
    private function verbatimSpans(string $prompt): array
    {
        $spans = [];

        foreach (['/"[^"]*"|\'[^\']*\'|“[^”]*”|‘[^’]*’/u', '/\S*[@\d_]\S*/u'] as $pattern) {
            if (preg_match_all($pattern, $prompt, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$text, $start]) {
                    if ($text !== '') {
                        $spans[] = [$start, $start + strlen($text)];
                    }
                }
            }
        }

        return $spans;
    }

    /** @param  list<array{int, int}>  $spans */
    private function isVerbatim(int $offset, int $length, array $spans): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($offset < $end && $offset + $length > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * A capitalised word that is not opening a sentence — so, in ordinary
     * English, a name. See the class docblock for why those are out of reach.
     */
    private function isProperNoun(string $token, int $offset, string $prompt): bool
    {
        if (mb_strtolower($token) === $token) {
            return false;
        }

        $before = substr($prompt, 0, $offset);

        // Nothing but whitespace, or a sentence ending, or an opening bracket:
        // the capital there is grammar, not a name.
        return ! (bool) preg_match('/(^|[.?!:;\n\r(\[])\s*$/u', $before);
    }

    /** Give the correction the shape the person typed. */
    private function matchCase(string $token, string $correction): string
    {
        if (mb_strtoupper($token) === $token) {
            return mb_strtoupper($correction);
        }

        if (mb_substr($token, 0, 1) === mb_strtoupper(mb_substr($token, 0, 1))) {
            return mb_strtoupper(mb_substr($correction, 0, 1)).mb_substr($correction, 1);
        }

        return $correction;
    }

    /**
     * Vocabulary as a lookup, built once.
     *
     * @return array<string, int>
     */
    private static function known(): array
    {
        static $known = null;

        return $known ??= array_flip(self::vocabulary());
    }

    /** @return array<string, int> */
    private static function protectedWords(): array
    {
        static $words = null;

        return $words ??= array_flip(self::NEVER_CORRECT);
    }
}
