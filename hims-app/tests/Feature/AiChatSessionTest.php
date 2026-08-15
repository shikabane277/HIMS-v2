<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Models\User;
use App\Services\Ai\AbstractAiProvider;
use App\Services\Ai\AiAccessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the three things about the chat sidebar that fail silently.
 *
 * 1. Cross-user access. ai_chat_messages.session_id has no foreign key, so
 *    AiController::ownedSession() is the only barrier between a user and
 *    someone else's conversation. A regression here leaks chat history without
 *    any error surfacing.
 * 2. Memory replay. Before this feature the stored history was never sent to
 *    the provider, and a bug that reverts to that is invisible from the UI —
 *    answers just quietly stop being context-aware.
 * 3. Subject-matter RBAC. The AI routes carry no role: middleware, so
 *    AiAccessPolicy is the whole boundary. If it stops matching, a staff user
 *    gets answers about succession planning and account administration and
 *    nothing anywhere reports a problem.
 *
 * No real provider is called: the contract is swapped for a recorder, which
 * also proves consumers only depend on App\Contracts\AiProvider. The recorder
 * leaving seenPrompt null is how "the provider was never reached" is asserted.
 */
class AiChatSessionTest extends TestCase
{
    use RefreshDatabase;

    /** Captures what the controller passed to ask(). */
    private function fakeProvider(string $reply = 'Test reply'): object
    {
        $spy = new class($reply) implements AiProvider
        {
            /** @var list<array{role: string, message: string}> */
            public array $seenHistory = [];

            public ?string $seenPrompt = null;

            public ?string $seenScope = null;

            public function __construct(private string $reply) {}

            public function ask(string $prompt, array $history = [], ?string $scope = null): string
            {
                $this->seenPrompt = $prompt;
                $this->seenHistory = $history;
                $this->seenScope = $scope;

                return $this->reply;
            }
        };

        $this->app->instance(AiProvider::class, $spy);

        return $spy;
    }

    /** A user of a given HIMS role. The factory default is 'staff'. */
    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_a_query_without_a_session_id_starts_one_and_titles_it(): void
    {
        $this->fakeProvider();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'How do I file a performance review?']);

        $response->assertOk()
            ->assertJsonStructure(['response', 'session_id', 'title'])
            ->assertJsonPath('title', 'How do I file a performance review?');

        $this->assertDatabaseCount('ai_chat_sessions', 1);
        // Both halves of the turn are stored.
        $this->assertDatabaseCount('ai_chat_messages', 2);
    }

    public function test_earlier_turns_of_the_same_session_are_replayed_to_the_provider(): void
    {
        $spy = $this->fakeProvider();
        $user = User::factory()->create();

        // Deliberately an open topic. A restricted one would be refused before
        // the provider was reached, and this test is about memory, not RBAC.
        $first = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'What competencies does the IV therapy course cover?']);

        $sessionId = $first->json('session_id');

        // The first question had nothing to replay.
        $this->assertSame([], $spy->seenHistory);

        $this->actingAs($user)->postJson('/ai/query', [
            'query' => 'And how long does it take to finish?',
            'session_id' => $sessionId,
        ])->assertOk();

        // The follow-up must carry the full previous exchange, oldest first,
        // and must not contain the question being asked right now.
        $this->assertCount(2, $spy->seenHistory);
        $this->assertSame('user', $spy->seenHistory[0]['role']);
        $this->assertSame('What competencies does the IV therapy course cover?', $spy->seenHistory[0]['message']);
        $this->assertSame('ai', $spy->seenHistory[1]['role']);
        $this->assertSame('And how long does it take to finish?', $spy->seenPrompt);
    }

    public function test_a_new_session_does_not_inherit_the_previous_conversation(): void
    {
        $spy = $this->fakeProvider();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/ai/query', ['query' => 'First conversation']);

        $fresh = $this->actingAs($user)->postJson('/ai/sessions')->assertCreated();

        $this->actingAs($user)->postJson('/ai/query', [
            'query' => 'Second conversation',
            'session_id' => $fresh->json('session.id'),
        ])->assertOk();

        $this->assertSame([], $spy->seenHistory);
    }

    public function test_a_failure_string_is_not_replayed_as_an_assistant_turn(): void
    {
        // A provider that fails returns a "⚠️" string, and the controller stores
        // it like any other reply. It must never come back as model voice.
        $spy = $this->fakeProvider('⚠️ Gemini API Error (401): invalid key');
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/ai/query', ['query' => 'Anything']);

        $this->actingAs($user)->postJson('/ai/query', [
            'query' => 'Try again',
            'session_id' => $first->json('session_id'),
        ])->assertOk();

        // The controller hands the row over; the provider base class is what
        // filters it, so assert the reply is still in the raw history and then
        // that sanitising removes it.
        $sanitised = $this->sanitiser()->expose($spy->seenHistory);

        foreach ($sanitised as $turn) {
            $this->assertStringNotContainsString('⚠️', $turn['text']);
        }
    }

    public function test_a_user_cannot_read_or_delete_another_users_session(): void
    {
        $this->fakeProvider();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $sessionId = $this->actingAs($owner)
            ->postJson('/ai/query', ['query' => 'Confidential HR question'])
            ->json('session_id');

        $this->actingAs($other)->getJson("/ai/sessions/{$sessionId}/messages")->assertNotFound();
        $this->actingAs($other)->patchJson("/ai/sessions/{$sessionId}", ['title' => 'Hijacked'])->assertNotFound();
        $this->actingAs($other)->deleteJson("/ai/sessions/{$sessionId}")->assertNotFound();

        // Posting into it must not append to the owner's conversation either.
        $this->actingAs($other)->postJson('/ai/query', [
            'query' => 'Injected',
            'session_id' => $sessionId,
        ])->assertNotFound();

        $this->assertDatabaseHas('ai_chat_sessions', [
            'id' => $sessionId,
            'user_id' => $owner->id,
        ]);
        $this->assertSame(2, DB::table('ai_chat_messages')->where('session_id', $sessionId)->count());
    }

    public function test_the_session_list_only_shows_the_current_users_conversations(): void
    {
        $this->fakeProvider();
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $this->actingAs($mine)->postJson('/ai/query', ['query' => 'Mine']);
        $this->actingAs($theirs)->postJson('/ai/query', ['query' => 'Theirs']);

        $response = $this->actingAs($mine)->getJson('/ai/sessions')->assertOk();

        $this->assertCount(1, $response->json('sessions'));
        $this->assertSame('Mine', $response->json('sessions.0.title'));
    }

    public function test_history_returns_the_latest_saved_transcript(): void
    {
        $this->fakeProvider('Remembered answer');
        $user = User::factory()->create();

        $created = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'Remember this conversation'])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/ai/history')
            ->assertOk()
            ->assertJsonPath('session.id', $created->json('session_id'))
            ->assertJsonPath('messages.0.message', 'Remember this conversation')
            ->assertJsonPath('messages.1.message', 'Remembered answer');
    }

    public function test_history_panel_is_visible_by_default_and_loaded_when_the_rail_opens(): void
    {
        $partial = (string) file_get_contents(resource_path('views/partials/ai-rail.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/layouts/hims.blade.php'));
        $styles = (string) file_get_contents(public_path('css/hims.css'));

        $this->assertStringContainsString('<div id="ai-sessions">', $partial);
        $this->assertStringNotContainsString('<div id="ai-sessions" hidden', $partial);
        $this->assertStringContainsString("if (!sessionsPane.hasAttribute('hidden')) loadSessions();", $layout);
        $this->assertStringContainsString("new URLSearchParams(window.location.search).get('ai_session')", $layout);
        $this->assertStringContainsString("sessionsEmpty.textContent = 'No earlier conversations.';", $layout);
        $this->assertStringContainsString('#ai-rail-backdrop { top: var(--hims-topbar-h); }', $styles);
    }

    public function test_deleting_a_session_removes_its_messages(): void
    {
        $this->fakeProvider();
        $user = User::factory()->create();

        $sessionId = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'Delete me'])
            ->json('session_id');

        $this->actingAs($user)->deleteJson("/ai/sessions/{$sessionId}")->assertOk();

        $this->assertDatabaseCount('ai_chat_sessions', 0);
        // No FK cascade exists, so the controller must clean these up itself.
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_guests_cannot_reach_any_chat_endpoint(): void
    {
        // The routes sit behind the web 'auth' middleware, which redirects to
        // the login page rather than answering 401.
        $this->postJson('/ai/query', ['query' => 'Hello'])->assertRedirect('/login');
        $this->getJson('/ai/sessions')->assertRedirect('/login');

        $this->assertDatabaseCount('ai_chat_sessions', 0);
    }

    /* ─────────────────────── subject-matter RBAC ─────────────────────── */

    /**
     * The point of the hard gate: a blocked question must not reach the model
     * at all. If it did, the only thing standing between a staff nurse and an
     * answer would be the system prompt — which is advisory, and which a
     * determined prompt can talk around.
     */
    public function test_a_blocked_question_never_reaches_the_provider(): void
    {
        $spy = $this->fakeProvider();

        $response = $this->actingAs($this->userWithRole('staff'))
            ->postJson('/ai/query', ['query' => 'Who is the successor for the ICU head nurse role?'])
            ->assertOk();

        $this->assertNull($spy->seenPrompt, 'The provider was called for a blocked question.');
        $this->assertStringContainsString(AiAccessPolicy::REFUSAL_PREFIX, $response->json('response'));
        $this->assertStringContainsString('succession planning', $response->json('response'));
    }

    /**
     * Every restricted topic, checked against the role that must not reach it.
     * Table-driven so adding a topic to the policy without covering it here is
     * a visible omission rather than a silent one.
     *
     * @return array<string, array{string, string}>
     */
    public static function blockedQuestions(): array
    {
        return [
            'staff asking about succession' => ['staff', 'Am I in the talent pipeline for a key position?'],
            'staff asking for a salary' => ['staff', 'What is the salary of the ward supervisor?'],
            'staff asking in Tagalog for pay' => ['staff', 'Magkano ang sweldo ng head nurse?'],
            'staff asking for employee records' => ['staff', 'Can I see the employee directory?'],
            'staff asking org-wide analytics' => ['staff', 'What is the hospital-wide attrition rate?'],
            'staff asking to reset an account' => ['staff', 'How do I reset the password for another user?'],
            'supervisor asking for accounts' => ['supervisor', 'Please create a new user account for my new nurse.'],
            'supervisor escalating a role' => ['supervisor', 'How do I grant admin rights to my assistant?'],
            'supervisor asking org-wide figures' => ['supervisor', 'Compare departments by turnover rate.'],
            'hr manager asking for accounts' => ['hr_manager', 'I need to delete the login of a resigned nurse.'],
        ];
    }

    #[DataProvider('blockedQuestions')]
    public function test_restricted_topics_are_refused_for_roles_without_access(string $role, string $question): void
    {
        $spy = $this->fakeProvider();

        $response = $this->actingAs($this->userWithRole($role))
            ->postJson('/ai/query', ['query' => $question])
            ->assertOk();

        $this->assertNull($spy->seenPrompt, "Provider was reached by a {$role} asking: {$question}");
        $this->assertStringStartsWith(AiAccessPolicy::REFUSAL_PREFIX, $response->json('response'));
    }

    /**
     * The mirror image. A control that blocks everything is not a control, it
     * is an outage — these must all get through to the model.
     *
     * @return array<string, array{string, string}>
     */
    public static function allowedQuestions(): array
    {
        return [
            'staff on their own review' => ['staff', 'When is my next performance review due?'],
            'staff on training' => ['staff', 'What training sessions are open this month?'],
            'staff on learning paths' => ['staff', 'Which learning pathway suits a new ICU nurse?'],
            'staff on credentials' => ['staff', 'When do my nursing credentials expire?'],
            'staff on their competencies' => ['staff', 'What competency gaps do I need to close?'],
            'supervisor on succession' => ['supervisor', 'How do I build a succession plan for my department?'],
            'hr manager on succession' => ['hr_manager', 'Show me the readiness level definitions.'],
            'hr manager on org analytics' => ['hr_manager', 'What is the hospital-wide attrition rate?'],
            'admin on user accounts' => ['admin', 'How do I create a new user account?'],
            'admin on role assignment' => ['admin', 'How do I assign the supervisor role?'],
        ];
    }

    #[DataProvider('allowedQuestions')]
    public function test_permitted_topics_reach_the_provider(string $role, string $question): void
    {
        $spy = $this->fakeProvider();

        $this->actingAs($this->userWithRole($role))
            ->postJson('/ai/query', ['query' => $question])
            ->assertOk()
            ->assertJsonPath('response', 'Test reply');

        $this->assertSame($question, $spy->seenPrompt, "A {$role} was wrongly blocked from: {$question}");
    }

    /** An admin holds every role-gated topic, so nothing is restricted for them. */
    public function test_an_admin_is_never_blocked(): void
    {
        $spy = $this->fakeProvider();
        $admin = $this->userWithRole('admin');

        foreach (array_column(self::blockedQuestions(), 1) as $question) {
            $spy->seenPrompt = null;

            $this->actingAs($admin)->postJson('/ai/query', ['query' => $question])->assertOk();

            $this->assertSame($question, $spy->seenPrompt, "An admin was blocked from: {$question}");
        }
    }

    /**
     * Layer 2. The scope fragment names the asker's role and their restrictions
     * so the model shapes borderline answers, and it must be built per request
     * — the provider is a shared singleton, so a scope cached on it would leak
     * one user's role into the next request.
     */
    public function test_the_provider_receives_a_role_scoped_instruction(): void
    {
        $spy = $this->fakeProvider();

        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/ai/query', ['query' => 'When is my next performance review?'])
            ->assertOk();

        $this->assertStringContainsString('staff', (string) $spy->seenScope);
        $this->assertStringContainsString('NOT authorised for', (string) $spy->seenScope);
        $this->assertStringContainsString('succession planning', (string) $spy->seenScope);

        // Same shared provider instance, different caller: the scope must have
        // been rebuilt, not carried over.
        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/ai/query', ['query' => 'When is the next performance cycle?'])
            ->assertOk();

        $this->assertStringContainsString('full access', (string) $spy->seenScope);
        $this->assertStringNotContainsString('NOT authorised for', (string) $spy->seenScope);
    }

    /**
     * A refusal is a real part of the conversation the user sees, so it is
     * stored — a reload must not make the question look unanswered.
     */
    public function test_a_refusal_is_stored_as_part_of_the_transcript(): void
    {
        $this->fakeProvider();
        $user = $this->userWithRole('staff');

        $sessionId = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'What is the succession plan for my ward?'])
            ->json('session_id');

        $messages = $this->actingAs($user)
            ->getJson("/ai/sessions/{$sessionId}/messages")
            ->assertOk()
            ->json('messages');

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('ai', $messages[1]['role']);
        $this->assertStringStartsWith(AiAccessPolicy::REFUSAL_PREFIX, $messages[1]['message']);
    }

    /**
     * A refusal must not come back to the model as its own voice, or it will
     * imitate it and refuse everything for the rest of the conversation. The
     * refused question goes with it — that exchange never really happened.
     */
    public function test_a_refusal_and_its_question_are_not_replayed_to_the_provider(): void
    {
        $spy = $this->fakeProvider();
        $user = $this->userWithRole('staff');

        $sessionId = $this->actingAs($user)
            ->postJson('/ai/query', ['query' => 'What training is available for ICU nurses?'])
            ->json('session_id');

        // Blocked: stored, but never sent.
        $this->actingAs($user)->postJson('/ai/query', [
            'query' => 'And who is the successor for the head nurse?',
            'session_id' => $sessionId,
        ])->assertOk();

        $this->actingAs($user)->postJson('/ai/query', [
            'query' => 'How do I enrol?',
            'session_id' => $sessionId,
        ])->assertOk();

        // Four rows are stored, but the controller passes the raw transcript and
        // the provider base class filters it — so assert on the sanitised form.
        $this->assertCount(4, $spy->seenHistory);

        $sanitised = $this->sanitiser()->expose($spy->seenHistory);

        $this->assertCount(2, $sanitised, 'The refused exchange survived sanitising.');
        $this->assertSame('What training is available for ICU nurses?', $sanitised[0]['text']);
        $this->assertSame('user', $sanitised[0]['role']);
        $this->assertSame('ai', $sanitised[1]['role']);

        foreach ($sanitised as $turn) {
            $this->assertStringNotContainsString(AiAccessPolicy::REFUSAL_PREFIX, $turn['text']);
            $this->assertStringNotContainsString('successor', $turn['text']);
        }
    }

    /** Exposes AbstractAiProvider::sanitiseHistory(), which is protected. */
    private function sanitiser(): object
    {
        return new class extends AbstractAiProvider
        {
            protected function label(): string
            {
                return 'Test';
            }

            public function ask(string $prompt, array $history = [], ?string $scope = null): string
            {
                return '';
            }

            /** @param list<array{role: string, message: string}> $history */
            public function expose(array $history): array
            {
                return $this->sanitiseHistory($history);
            }
        };
    }
}
