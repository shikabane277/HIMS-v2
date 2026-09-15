<?php

namespace App\Http\Controllers;

use App\Contracts\AiProvider;
use App\Services\Ai\AiAccessPolicy;
use App\Services\Ai\AiActionExecutor;
use App\Services\Ai\AiActionPlanner;
use App\Services\Ai\AiActionRegistry;
use App\Services\Ai\AiTypoCorrector;
use App\Support\FuzzyMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backs the AI assistant sidebar.
 *
 * Chat is organised into sessions (ai_chat_sessions), each holding an ordered
 * list of messages (ai_chat_messages). The sidebar lists a user's sessions,
 * starts new ones, and reopens old ones; query() replays the current session's
 * earlier turns to the model so follow-up questions carry context.
 *
 * OWNERSHIP: ai_chat_messages.session_id deliberately carries no foreign key
 * (see the 2026_08_06_000120 migration for why), so this controller is the only
 * thing standing between a user and someone else's conversation. Every session
 * lookup goes through ownedSession(), which scopes by auth()->id() and 404s
 * otherwise; no query may take a session id from the request without it.
 *
 * SUBJECT-MATTER RBAC: the AI routes carry no role: middleware — every signed-in
 * user gets the assistant — so the per-role boundary is applied here instead, by
 * AiAccessPolicy in query(). A question about a topic the asker's role cannot
 * reach is refused before the provider is called; everything else is sent with a
 * role-scoped instruction attached. See AiAccessPolicy for why it is two layers.
 *
 * ACTIONS: the assistant can also perform writes, not just describe them —
 * "create a 2027 annual review cycle" creates one. resolveAction() runs the
 * pipeline: AiActionPlanner classifies the message, AiEntityResolver turns the
 * names in it into ids, and AiActionExecutor calls the same controller method
 * the web form would have called. Permission comes from AiActionRegistry, which
 * reads the `role:` middleware off the real route, so an action the person could
 * not perform through the UI is refused here too. Destructive actions are not
 * executed on the spot: the target is resolved, named back, and parked in
 * ai_chat_sessions.pending_action until the next message confirms it.
 *
 * SPELLING: every gate in front of the model is a literal string test —
 * ACTION_VERBS is a word list, AiAccessPolicy::TOPICS is a pattern list, and
 * AiEntityResolver is substring LIKE — so a single mistyped letter used to make
 * a message invisible to all three at once. "crate a 2027 cycle" was answered
 * with advice about creating cycles and nothing said the instruction had not been
 * understood. AiTypoCorrector therefore reads the message once at the top of
 * query() and the corrected text is what the gates and the planner see; the raw
 * text is what is stored, replayed, audited and sent to the provider.
 *
 * Three parts of that split are load-bearing:
 *
 *  - The topic gate runs on the raw text first, exactly as before, and then again
 *    on the corrected text when the reading differed. It can only ever add a
 *    refusal — which closes a real hole, because "who is next in line for
 *    succesion?" walked straight past a pattern list spelt correctly.
 *  - The confirmation keyword for a destructive action is matched against the raw
 *    message and is never fuzzy-matched. A mistyped "confrim" must not fire a
 *    delete; it cancels, and says how to try again.
 *  - The stored transcript keeps the person's own words. What the assistant read
 *    them as is stated in the reply instead, so the record shows both.
 */
class AiController extends Controller
{
    /** Hard ceiling on messages read back for one session. */
    private const HISTORY_LIMIT = 200;

    /**
     * Only a message that reads like an instruction is worth classifying. A
     * question ("how do I enrol?") skips the planner entirely and costs one AI
     * call as it always did; a command costs two.
     */
    private const ACTION_VERBS = '/\b(create|creating|add|adding|new|delete|deleting|remove|removing|'
        .'update|updating|change|changing|edit|editing|set|assign|nominate|withdraw|verify|approve|'
        .'enrol|enroll|register|log|record|post|schedule|check ?in|score|rate|submit|rename|close|open|'
        .'reactivate|reactivating|activate|activating|deactivate|deactivating|restore|restoring|'
        .'reinstate|suspend|suspending|terminate|terminating|promote|transfer|move|mark|make)\b/i';

    /** How long a pending destructive action stays confirmable. */
    private const CONFIRM_TTL_MINUTES = 5;

    public function __construct(
        private AiProvider $ai,
        private AiAccessPolicy $policy,
        private AiActionPlanner $planner,
        private AiActionExecutor $executor,
        private AiTypoCorrector $typos,
    ) {}

    /* ───────────────────────────── sessions ───────────────────────────── */

    /** The current user's conversations, most recently used first. */
    public function sessions(Request $request)
    {
        $sessions = DB::table('ai_chat_sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Start a new conversation.
     *
     * Created empty and untitled — the title is derived from the first question
     * in query(), so an abandoned "New chat" never gets a misleading name.
     */
    public function storeSession(Request $request)
    {
        $session = $this->createSession(auth()->id());

        return response()->json(['session' => $session], 201);
    }

    /** Messages of one conversation, oldest first, for rendering the transcript. */
    public function sessionMessages(Request $request, string $session)
    {
        $owned = $this->ownedSession($session);

        return response()->json([
            'session' => $owned,
            'messages' => $this->transcript($owned->id),
        ]);
    }

    /** Rename a conversation from the sidebar. */
    public function updateSession(Request $request, string $session)
    {
        $owned = $this->ownedSession($session);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
        ]);

        DB::table('ai_chat_sessions')
            ->where('id', $owned->id)
            ->update([
                'title' => trim($validated['title']),
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true, 'title' => trim($validated['title'])]);
    }

    /**
     * Delete a conversation and its messages.
     *
     * The messages are removed explicitly because session_id has no FK, so
     * there is no ON DELETE CASCADE to rely on. Wrapped in a transaction so a
     * failure halfway cannot strand messages pointing at a session that is gone.
     */
    public function destroySession(Request $request, string $session)
    {
        $owned = $this->ownedSession($session);

        DB::transaction(function () use ($owned) {
            DB::table('ai_chat_messages')
                ->where('user_id', auth()->id())
                ->where('session_id', $owned->id)
                ->delete();

            DB::table('ai_chat_sessions')
                ->where('id', $owned->id)
                ->where('user_id', auth()->id())
                ->delete();
        });

        return response()->json(['ok' => true]);
    }

    /* ───────────────────────────── messages ───────────────────────────── */

    /**
     * Transcript of the most recent conversation.
     *
     * Kept for the plain /ai/history route the sidebar falls back to when it
     * has no session selected yet.
     */
    public function history(Request $request)
    {
        $latest = DB::table('ai_chat_sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->first(['id', 'title']);

        return response()->json([
            'session' => $latest,
            'messages' => $latest ? $this->transcript($latest->id) : [],
        ]);
    }

    /**
     * Handle a new question: save it, answer it with the conversation so far,
     * save the reply.
     *
     * A question outside the asker's role is refused here, before any provider
     * call. The refusal is stored like any other reply so the transcript stays
     * an honest record of the exchange — and so a reload does not make the
     * question look unanswered.
     */
    public function query(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:1000',
            'session_id' => 'nullable|string|max:36',
        ]);

        $prompt = $validated['query'];
        $user = auth()->user();
        $userId = auth()->id();

        // How the assistant reads the message, which is not necessarily how it
        // was typed. Computed once: the gates, the planner and the note below all
        // have to agree on one reading.
        $reading = $this->typos->correct($prompt);

        // An unknown or someone else's session id must not silently open a new
        // chat under this user — ownedSession() 404s instead.
        $session = isset($validated['session_id']) && $validated['session_id'] !== ''
            ? $this->ownedSession($validated['session_id'])
            : $this->createSession($userId);

        // Read the prior turns BEFORE inserting this question, so the prompt is
        // not also present in the replayed history.
        $history = $this->transcript($session->id);

        $seq = (int) DB::table('ai_chat_messages')
            ->where('session_id', $session->id)
            ->max('seq');

        DB::table('ai_chat_messages')->insert([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'session_id' => $session->id,
            'role' => 'user',
            'message' => $prompt,
            'seq' => ++$seq,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The hard RBAC gate. Deliberately ahead of ask(): a refusal must not
        // depend on the model choosing to comply, and a blocked question should
        // cost nothing. It also stands ahead of the action pipeline — a topic
        // this role cannot discuss is one it certainly cannot act on.
        $denied = $this->policy->deniedTopic($user, $prompt);

        // TOPICS is a list of mostly multi-word phrases, so a misspelling walks
        // through it: "who is next in line for succesion?" was not a succession
        // question as far as the gate was concerned. Re-testing the corrected
        // reading can only add a refusal, never remove one, which is the only
        // direction this is allowed to move.
        $readingMattered = false;

        if ($denied === null && $reading['text'] !== $prompt) {
            $denied = $this->policy->deniedTopic($user, $reading['text']);
            $readingMattered = $denied !== null;
        }

        $action = null;

        if ($denied !== null) {
            $response = $this->policy->refusal($denied);
        } else {
            $action = $this->resolveAction($request, $session, $user, $prompt, $reading);

            $readingMattered = $action !== null;

            // The provider gets the words as typed. It reads around a typo
            // natively, and a note beside an answer that ignored the correction
            // would describe something that did not happen.
            $response = $action
                ? $action['message']
                : $this->ai->ask($prompt, $history, $this->policy->scopeFor($user));
        }

        // Only stated where the reading changed the outcome: an action that ran,
        // or a topic refusal the raw spelling would have missed.
        if ($readingMattered && ($note = $this->typos->note($reading['changes'])) !== '') {
            $response = $note.' '.$response;
        }

        DB::table('ai_chat_messages')->insert([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'session_id' => $session->id,
            'role' => 'ai',
            'message' => $response,
            'seq' => ++$seq,
            // now() again, not the question's timestamp: the reply genuinely
            // arrives later, and the two must not tie.
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $title = $session->title ?: $this->deriveTitle($prompt);

        DB::table('ai_chat_sessions')
            ->where('id', $session->id)
            ->update(['title' => $title, 'updated_at' => now()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'response' => $response,
                'session_id' => $session->id,
                'title' => $title,
                // Lets the rail tint the bubble and hint at the confirm step.
                // Null on a conversational answer, so the UI is unchanged there.
                'action_status' => $action['status'] ?? null,
                'pending_confirm' => (bool) ($action['pending'] ?? false),
            ]);
        }

        // Normal form POST fallback
        return back()->with('ai_response', $response);
    }

    /**
     * Clear chat history.
     *
     * With a session id, empties that one conversation and removes it. Without
     * one, wipes every conversation this user has — which is what the old
     * "Clear chat" button did, and what the sidebar's "Clear all" still does.
     */
    public function clearHistory(Request $request)
    {
        $userId = auth()->id();
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            $this->destroySession($request, (string) $sessionId);

            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($userId) {
            DB::table('ai_chat_messages')->where('user_id', $userId)->delete();
            DB::table('ai_chat_sessions')->where('user_id', $userId)->delete();
        });

        return response()->json(['ok' => true]);
    }

    /* ────────────────────────────── actions ───────────────────────────── */

    /**
     * Decide whether this message performs an action, and perform it.
     *
     * Returns null when the message is not a command, which is the signal to
     * answer it conversationally instead. Every branch that returns non-null has
     * already produced the exact text the user should see.
     *
     * $prompt is what the person typed and $reading is AiTypoCorrector's reading
     * of it. The corrected text drives the verb gate and the planner, because
     * those are the string tests a typo defeats; the raw text arms the destructive
     * confirmation and is what gets audited.
     *
     * @param  array{text: string, changes: array<string, string>}  $reading
     * @return array{message: string, status: ?string, pending: bool}|null
     */
    private function resolveAction(Request $request, object $session, $user, string $prompt, array $reading): ?array
    {
        // A destructive action already offered and awaiting a yes/no takes
        // priority: "confirm" means that, not a fresh instruction.
        if ($pending = $this->pendingAction($session)) {
            $this->clearPending($session);

            // The RAW message, never the corrected one, and never a fuzzy match.
            // A near-miss of "confirm" is not consent to delete a record — the
            // only safe reading of an unclear answer here is no.
            if (! preg_match('/^\s*(confirm|confirmed|yes|proceed|do it|go ahead)\b/i', $prompt)) {
                return $this->reply('Cancelled — nothing was changed.'.$this->confirmHint($prompt), null);
            }

            $result = $this->executor->execute($pending, $user, $request);

            return $this->reply($result['message'], $result['ok'] ? 'ok' : 'error');
        }

        $corrected = $reading['text'];

        if (! preg_match(self::ACTION_VERBS, $corrected)) {
            return null;
        }

        $plan = $this->planner->plan($corrected, $user);

        if (! $plan) {
            return null;
        }

        // The model was asked to report anything it could not fill rather than
        // invent it. Ask, do not guess.
        if ($plan['missing']) {
            return $this->reply(
                'I can do that, but I need '.$this->listWords($plan['missing']).' first.',
                null
            );
        }

        // The audit keeps the words the person typed. The reading is recorded
        // beside it only when it differed, so an auditor can see that "delet the
        // uesr bob" was carried out as a delete of the user bob.
        $plan['prompt'] = $prompt;
        $plan['session_id'] = $session->id;

        if ($corrected !== $prompt) {
            $plan['prompt_corrected'] = $corrected;
        }

        $spec = AiActionRegistry::get($plan['action'], $user);

        if (! $spec) {
            return null;
        }

        // Destructive: resolve the target now so the confirmation names the real
        // record, then stop and wait. Resolving first also means an ambiguous or
        // missing target is reported before anyone is asked to confirm anything.
        if ($spec['destructive'] ?? false) {
            $prepared = $this->executor->prepare($spec, $plan, $user);

            if (! $prepared['ok']) {
                return $this->reply($prepared['message'], 'error');
            }

            $this->storePending($session, $plan);

            return $this->reply(
                "⚠️ {$spec['label']}: **{$prepared['label']}**. This cannot be undone. "
                .'Reply **confirm** to proceed, or anything else to cancel.',
                null,
                true
            );
        }

        $result = $this->executor->execute($plan, $user, $request);

        return $this->reply($result['message'], $result['ok'] ? 'ok' : 'error');
    }

    /** @return array{message: string, status: ?string, pending: bool} */
    private function reply(string $message, ?string $status, bool $pending = false): array
    {
        return ['message' => $message, 'status' => $status, 'pending' => $pending];
    }

    /**
     * The sentence appended when a cancellation looks like a mistyped "confirm".
     *
     * The cancel itself stands — this reports the near-miss rather than acting on
     * it, which is the whole point: the destructive step is the one place where
     * reading a typo generously would be the dangerous choice. Telling the person
     * what happened costs nothing and saves them wondering why the delete they
     * thought they approved did not happen.
     */
    private function confirmHint(string $prompt): string
    {
        if (! preg_match('/^\s*(\p{L}+)/u', $prompt, $match)) {
            return '';
        }

        $near = FuzzyMatch::closest($match[1], ['confirm', 'proceed'], 2);

        return $near === null
            ? ''
            : " If you meant “{$near}”, send the instruction again — a misspelt confirmation is always read as no.";
    }

    /**
     * The stored plan awaiting confirmation, or null if there is none or it has
     * gone stale. An expired offer must not fire against a "yes" that was
     * answering some later question.
     */
    private function pendingAction(object $session): ?array
    {
        $row = DB::table('ai_chat_sessions')
            ->where('id', $session->id)
            ->first(['pending_action', 'pending_action_at']);

        if (! $row || ! $row->pending_action) {
            return null;
        }

        if (! $row->pending_action_at
            || now()->diffInMinutes($row->pending_action_at, true) > self::CONFIRM_TTL_MINUTES) {
            $this->clearPending($session);

            return null;
        }

        $plan = json_decode((string) $row->pending_action, true);

        return is_array($plan) ? $plan : null;
    }

    private function storePending(object $session, array $plan): void
    {
        DB::table('ai_chat_sessions')->where('id', $session->id)->update([
            'pending_action' => json_encode($plan),
            'pending_action_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function clearPending(object $session): void
    {
        DB::table('ai_chat_sessions')->where('id', $session->id)->update([
            'pending_action' => null,
            'pending_action_at' => null,
        ]);
    }

    /** "a name, a date and an email" — reads better than a bare list. */
    private function listWords(array $items): string
    {
        $items = array_map(fn ($i) => str_replace('_', ' ', (string) $i), $items);

        if (count($items) === 1) {
            return 'the '.$items[0];
        }

        $last = array_pop($items);

        return 'the '.implode(', ', $items).' and '.$last;
    }

    /* ───────────────────────────── internals ──────────────────────────── */

    /**
     * Fetch a session that belongs to the signed-in user, or 404.
     *
     * The user_id predicate is the access control for the whole chat feature:
     * without it a guessed or leaked uuid would read and write another user's
     * conversation. 404 rather than 403 so the response does not confirm that
     * an id exists.
     */
    private function ownedSession(string $sessionId): object
    {
        $session = DB::table('ai_chat_sessions')
            ->where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->first(['id', 'title', 'created_at', 'updated_at']);

        abort_if($session === null, 404);

        return $session;
    }

    /** Create and return an empty conversation for a user. */
    private function createSession(int $userId): object
    {
        $session = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'title' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ai_chat_sessions')->insert($session);

        return (object) [
            'id' => $session['id'],
            'title' => null,
            'created_at' => $session['created_at'],
            'updated_at' => $session['updated_at'],
        ];
    }

    /**
     * One conversation's messages, oldest first.
     *
     * Ordered by seq, with created_at as the tiebreaker for messages written
     * before seq existed (the migration backfills session_id but leaves their
     * seq null, since nothing recorded their true order at the time).
     *
     * The limit takes the NEWEST messages, not the oldest: this feeds both the
     * transcript and the model's memory, and a conversation past the limit must
     * carry its recent context, not its opening.
     *
     * @return list<array{role: string, message: string, created_at: mixed}>
     */
    private function transcript(string $sessionId): array
    {
        $rows = DB::table('ai_chat_messages')
            ->where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->orderByDesc('seq')
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'message', 'created_at']);

        return array_values(array_reverse($rows->map(fn ($row) => [
            'role' => $row->role,
            'message' => $row->message,
            'created_at' => $row->created_at,
        ])->all()));
    }

    /** First line of the opening question, trimmed to fit the sidebar list. */
    private function deriveTitle(string $prompt): string
    {
        $firstLine = trim(strtok(trim($prompt), "\n") ?: $prompt);

        return Str::limit($firstLine, 60, '…') ?: 'New chat';
    }
}
