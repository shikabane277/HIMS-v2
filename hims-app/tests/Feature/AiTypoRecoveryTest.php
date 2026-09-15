<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A mistyped message reaching the assistant, end to end through /ai/query.
 *
 * WHAT WENT WRONG BEFORE THIS EXISTED
 *
 * Every gate in front of the model is a literal string test: ACTION_VERBS is a
 * word list, AiAccessPolicy::TOPICS is a pattern list, AiEntityResolver is a
 * substring LIKE. One wrong letter made a message invisible to all three at
 * once, and the failure was silent in the worst possible way — "crate a 2027
 * cycle" came back with a helpful paragraph about how one creates cycles, so the
 * person had every reason to believe the cycle existed.
 *
 * THE THREE SPLITS THESE TESTS PIN
 *
 * The corrected reading and the raw message are used in different places on
 * purpose, and each split is load-bearing:
 *
 *  - The gates and the planner see the corrected text. That is what makes a
 *    typo'd instruction work, and — because the topic gate re-runs — it is also
 *    what stops "who is next in line for succesion?" walking past a restriction
 *    it would have hit spelt correctly.
 *  - The provider, the transcript and the audit see the raw text. A model reads
 *    around a typo natively, and the record has to hold the person's own words.
 *  - The destructive confirmation sees only the raw text and is never
 *    fuzzy-matched. "confrim" cancels.
 *
 * Portability: cycles and users are plain inserts and cycle name resolution is a
 * plain LIKE, so this whole class stays on the sqlite suite. Employee resolution
 * uses CONCAT and is deliberately not exercised here — AiActionTest makes the
 * same call for the same reason.
 */
class AiTypoRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A provider returning a canned plan, recording what it was asked. lastPrompt
     * is the assertion that matters here: it is the only way to see which of the
     * two readings actually left the controller.
     */
    private function planningProvider(array|string $plan): object
    {
        $spy = new class(is_array($plan) ? json_encode($plan) : $plan) implements AiProvider
        {
            public int $calls = 0;

            public ?string $lastPrompt = null;

            public function __construct(private string $reply) {}

            public function ask(string $prompt, array $history = [], ?string $scope = null): string
            {
                $this->calls++;
                $this->lastPrompt = $prompt;

                return $this->reply;
            }
        };

        $this->app->instance(AiProvider::class, $spy);

        return $spy;
    }

    /** An account linked to an employee profile, which review_cycles.created_by needs. */
    private function linkedUser(string $role, string $email): User
    {
        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId, 'name' => 'Nursing '.Str::random(4),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId, 'role_name' => 'Nurse '.Str::random(4),
            'role_slug' => 'nurse-'.Str::lower(Str::random(6)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId, 'employee_code' => 'EMP-'.Str::upper(Str::random(5)),
            'first_name' => 'Test', 'last_name' => 'Person', 'email' => $email,
            'department_id' => $deptId, 'role_id' => $roleId,
            'employment_status' => 'active', 'hire_date' => now()->subYear()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create([
            'email' => $email,
            'role' => $role,
            'employee_id' => $empId,
        ]);
    }

    private function cyclePlan(string $name = '2027 Annual Performance Review'): array
    {
        return [
            'action' => 'performance.cycle.create',
            'params' => [
                'cycle_name' => $name,
                'cycle_type' => 'annual',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ],
            'missing' => [],
            'summary' => "Create the review cycle {$name}.",
        ];
    }

    /** An existing cycle for the resolver to find, or fail to find, by name. */
    private function seedCycle(User $owner, string $name): void
    {
        DB::table('review_cycles')->insert([
            'cycle_id' => (string) Str::uuid(),
            'cycle_name' => $name,
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            // NOT NULL, and the only reason these tests need a linked account.
            'created_by' => $owner->employee_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ─────────────────── a typo'd instruction still runs ─────────────────── */

    public function test_a_misspelt_verb_still_reaches_the_planner_and_the_action_runs(): void
    {
        $spy = $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Crate a 2027 annual review cycle'])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok');

        // The classifier was handed the corrected reading — without this the verb
        // gate above it would have returned null and no plan would exist at all.
        $this->assertStringContainsString('Create', $spy->lastPrompt);
        $this->assertStringNotContainsString('Crate', $spy->lastPrompt);

        $this->assertDatabaseHas('review_cycles', ['cycle_name' => '2027 Annual Performance Review']);

        // And the person is told how their message was read, so a wrong guess is
        // visible rather than mysterious.
        $this->assertStringStartsWith('Read “Crate” as “Create”.', $response->json('response'));
    }

    public function test_the_reading_is_stated_in_the_stored_reply_not_only_the_response(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $sessionId = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Crate a 2027 annual review cycle'])
            ->json('session_id');

        $turns = DB::table('ai_chat_messages')
            ->where('session_id', $sessionId)
            ->orderBy('seq')
            ->get(['role', 'message']);

        // The question keeps the person's own spelling; the reading is stated in
        // the answer beside it. A reload shows both halves of what happened.
        $this->assertSame('Crate a 2027 annual review cycle', $turns[0]->message);
        $this->assertStringContainsString('Read “Crate” as “Create”', $turns[1]->message);
    }

    public function test_the_audit_keeps_both_the_words_typed_and_the_reading_acted_on(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Crate a 2027 annual review cycle'])
            ->assertOk();

        $metadata = json_decode(
            (string) DB::table('audit_trails')->where('action', 'ai_create')->value('metadata'),
            true
        );

        $this->assertSame('Crate a 2027 annual review cycle', $metadata['prompt']);
        $this->assertSame('Create a 2027 annual review cycle', $metadata['prompt_corrected']);
    }

    public function test_a_correctly_spelt_instruction_records_no_correction(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok');

        // No note, and no prompt_corrected key — an auditor reading a row with
        // that field set must be able to trust that something really was re-read.
        $this->assertStringNotContainsString('Read “', $response->json('response'));

        $metadata = json_decode(
            (string) DB::table('audit_trails')->where('action', 'ai_create')->value('metadata'),
            true
        );

        $this->assertNull($metadata['prompt_corrected']);
    }

    /* ──────────────── the conversational path is left as typed ───────────── */

    public function test_a_question_reaches_the_provider_exactly_as_it_was_typed(): void
    {
        $spy = $this->planningProvider('The credentials page lists them.');
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'where are the credentails'])
            ->assertOk()
            ->assertJsonPath('action_status', null);

        // A model reads around a typo without help, and a note describing a
        // correction the answer never used would be a claim about work that did
        // not happen.
        $this->assertSame('where are the credentails', $spy->lastPrompt);
        $this->assertStringNotContainsString('Read “', $response->json('response'));
    }

    /* ─────────────────── the topic gate closes, never opens ──────────────── */

    /**
     * TOPICS is a list of mostly multi-word phrases, which a single wrong letter
     * defeats outright. This message is a succession question and succession is
     * closed to the employee role — spelt correctly it was refused, spelt this way
     * it was answered.
     *
     * The wording matters: the topic has to rest on the misspelt word alone. Ask
     * "who is next in line for succesion?" and a *different* pattern in the same
     * topic catches it regardless, which proves nothing about the re-check.
     */
    public function test_a_typo_cannot_walk_a_restricted_topic_past_the_gate(): void
    {
        $spy = $this->planningProvider('Here is the succession plan.');
        $staff = $this->linkedUser('employee', 'nurse@hospital.test');

        $response = $this->actingAs($staff)
            ->postJson('/ai/query', ['query' => 'tell me about the succesion plan'])
            ->assertOk();

        $this->assertSame(0, $spy->calls, 'the provider was called for a topic this role cannot discuss');
        $this->assertStringContainsString('succession planning', $response->json('response'));

        // The refusal says why it now applies, so the person is not left thinking
        // the same question worked a moment ago.
        $this->assertStringContainsString('Read “succesion” as “succession”', $response->json('response'));
    }

    public function test_the_re_check_only_adds_refusals_and_never_removes_one(): void
    {
        // Same message, a role that may discuss succession. The corrected reading
        // is tested against the gate too, and passing it must leave the answer
        // exactly where an unmisspelt question would have been.
        $spy = $this->planningProvider('Here is the succession plan.');
        $hr = $this->linkedUser('hr_manager', 'hr@hospital.test');

        $response = $this->actingAs($hr)
            ->postJson('/ai/query', ['query' => 'tell me about the succesion plan'])
            ->assertOk();

        $this->assertSame(1, $spy->calls);
        $this->assertSame('Here is the succession plan.', $response->json('response'));
    }

    /* ──────────────────── a wrong name gets a suggestion ─────────────────── */

    /**
     * The resolver is a substring LIKE, so a misspelt name returns nothing — and
     * "no cycle matching …" reads identically whether the record is missing, out
     * of reach, or simply mistyped. The suggestion is what tells those apart.
     *
     * It runs only after the real query found nothing, so it cannot change which
     * row resolves; the action still stops here.
     */
    public function test_an_unresolvable_name_is_answered_with_the_nearest_real_record(): void
    {
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $this->seedCycle($admin, '2026 Annual Performance Review');

        $this->planningProvider([
            'action' => 'performance.cycle.update',
            'params' => ['id' => '2026 Anual Performance Review', 'status' => 'finished'],
            'missing' => [],
            'summary' => 'Close the 2026 cycle.',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Close the 2026 Anual Performance Review'])
            ->assertOk()
            ->assertJsonPath('action_status', 'error');

        // Straight quotes, not typographic: the suffix is appended to the
        // resolver's own "no cycle matching …" phrase, so it matches that
        // sentence rather than the note's house style.
        $this->assertStringContainsString('did you mean "2026 Annual Performance Review"', $response->json('response'));

        // Suggesting is not resolving: the cycle is untouched.
        $this->assertDatabaseHas('review_cycles', [
            'cycle_name' => '2026 Annual Performance Review',
            'status' => 'active',
        ]);
    }

    public function test_nothing_close_enough_yields_no_suggestion_rather_than_a_wrong_one(): void
    {
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $this->seedCycle($admin, '2026 Annual Performance Review');

        $this->planningProvider([
            'action' => 'performance.cycle.update',
            'params' => ['id' => 'Probationary Onboarding Checkpoint', 'status' => 'finished'],
            'missing' => [],
            'summary' => 'Close a cycle.',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Close the Probationary Onboarding Checkpoint'])
            ->assertOk()
            ->assertJsonPath('action_status', 'error');

        // A guess that names an unrelated record is worse than no guess: it reads
        // as though the assistant found something.
        $this->assertStringNotContainsString('did you mean', $response->json('response'));
    }

    /* ───────────────────── the confirmation is never guessed ─────────────── */

    /**
     * The one place where reading a typo generously would be the dangerous
     * choice. A near-miss of "confirm" cancels — and says so, so nobody is left
     * wondering why the delete they thought they approved never happened.
     */
    public function test_a_misspelt_confirmation_cancels_and_explains_itself(): void
    {
        $adminOne = User::factory()->create(['role' => 'admin', 'email' => 'one@hospital.test']);
        $bob = User::factory()->create(['email' => 'bob@hospital.test', 'name' => 'Bob']);

        $this->planningProvider([
            'action' => 'user.delete',
            'params' => ['user' => 'bob@hospital.test'],
            'missing' => [],
            'summary' => 'Delete user bob@hospital.test.',
        ]);

        $sessionId = $this->actingAs($adminOne)
            ->postJson('/ai/query', ['query' => 'Delete the user bob'])
            ->assertOk()
            ->assertJsonPath('pending_confirm', true)
            ->json('session_id');

        $response = $this->actingAs($adminOne)
            ->postJson('/ai/query', ['query' => 'confrim', 'session_id' => $sessionId])
            ->assertOk();

        $this->assertStringContainsString('Cancelled', $response->json('response'));
        $this->assertStringContainsString('If you meant “confirm”', $response->json('response'));

        $this->assertDatabaseHas('users', ['id' => $bob->id]);
        $this->assertDatabaseCount('audit_trails', 0);

        // The offer is spent either way, so a later stray "confirm" cannot fire it.
        $this->assertNull(
            DB::table('ai_chat_sessions')->where('id', $sessionId)->value('pending_action')
        );
    }

    public function test_an_ordinary_cancellation_gets_no_confirmation_hint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'bob@hospital.test', 'name' => 'Bob']);

        $this->planningProvider([
            'action' => 'user.delete',
            'params' => ['user' => 'bob@hospital.test'],
            'missing' => [],
            'summary' => 'Delete user bob@hospital.test.',
        ]);

        $sessionId = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Delete the user bob'])
            ->json('session_id');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'no wait', 'session_id' => $sessionId])
            ->assertOk();

        // "no" is not a misspelling of anything — the hint would be noise.
        $this->assertStringContainsString('Cancelled', $response->json('response'));
        $this->assertStringNotContainsString('If you meant', $response->json('response'));
    }

    /**
     * The confirmation words are the one vocabulary the corrector is forbidden to
     * touch. If a future entry pulled "confirm" toward something else, the raw
     * text and the corrected text would disagree about whether consent was given
     * — and the raw text is the one that arms the delete.
     */
    public function test_a_correct_confirmation_is_not_reshaped_on_its_way_through(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bob = User::factory()->create(['email' => 'bob@hospital.test', 'name' => 'Bob']);

        $this->planningProvider([
            'action' => 'user.delete',
            'params' => ['user' => 'bob@hospital.test'],
            'missing' => [],
            'summary' => 'Delete user bob@hospital.test.',
        ]);

        $sessionId = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Delete the user bob'])
            ->json('session_id');

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'confirm', 'session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok');

        $this->assertDatabaseMissing('users', ['id' => $bob->id]);
    }
}
