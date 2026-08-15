<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The assistant performing actions, end to end through /ai/query.
 *
 * WHAT THESE TESTS ARE GUARDING
 *
 * Executing an action calls the controller method directly, which means the
 * `role:` middleware that normally guards the route never runs. AiActionRegistry
 * re-derives the requirement from the route instead — so the thing that must be
 * pinned is that a role which cannot reach a route through the UI cannot reach it
 * through chat either. A regression here is silent: the write simply succeeds.
 *
 * The second concern is the confirmation step. A destructive action is parked in
 * ai_chat_sessions.pending_action and only runs on an explicit "confirm". Two
 * ways for that to fail badly: the delete firing without a confirm, or a stale
 * offer firing against a "yes" that was answering some later question.
 *
 * The provider is always a fake returning a canned JSON plan, so no model is
 * called and the classifier's own accuracy is not under test — the pipeline
 * after it is.
 *
 * Portability: review_cycles and users are plain inserts, so this stays on the
 * sqlite suite. Employee name resolution uses CONCAT and is deliberately not
 * exercised here.
 */
class AiActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A provider whose reply is a fixed action plan, as though the classifier
     * had understood the message. Also records how many times it was asked, so
     * "the planner was never consulted" is assertable.
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

    /** The plan the fake classifier returns for "create a 2027 annual cycle". */
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

    /* ───────────────────────────── creating ───────────────────────────── */

    public function test_an_admin_can_create_a_review_cycle_by_asking(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle for the whole year'])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok')
            ->assertJsonPath('pending_confirm', false);

        // The controller's own flash message is what the user is shown, not a
        // sentence the assistant made up.
        $this->assertStringContainsString('Review cycle created successfully', $response->json('response'));

        $this->assertDatabaseHas('review_cycles', [
            'cycle_name' => '2027 Annual Performance Review',
            'cycle_type' => 'annual',
            'status' => 'planned',
        ]);

        // created_by is filled from the signed-in profile by the controller —
        // proof the real method ran rather than a reimplementation.
        $this->assertSame(
            $admin->employee_id,
            DB::table('review_cycles')->value('created_by')
        );
    }

    /** The action is recorded in audit_trails, which was dead until this feature. */
    public function test_a_performed_action_writes_an_audit_row(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->assertOk();

        // The controller records the domain change and the executor records the
        // AI prompt context. Select the integration event explicitly rather than
        // depending on insertion order between two valid audit records.
        $row = DB::table('audit_trails')->where('action', 'ai_create')->first();

        $this->assertNotNull($row, 'No audit row was written for an AI action.');
        $this->assertSame('ai_create', $row->action);
        $this->assertSame('review_cycles', $row->resource_type);
        $this->assertSame((string) $admin->id, $row->user_id);
        $this->assertSame($admin->employee_id, $row->employee_id);
        $this->assertNotEmpty($row->ip_address, 'ip_address is NOT NULL and must always be populated.');
        $this->assertSame('/ai/query', $row->request_path);

        // The created row is identified where it can be, and the prompt that
        // caused it is kept so the entry can be read back in context.
        $this->assertSame(DB::table('review_cycles')->value('cycle_id'), $row->resource_id);
        $this->assertNull($row->before_state, 'A create has no prior state.');

        $metadata = json_decode((string) $row->metadata, true);
        $this->assertSame('performance.cycle.create', $metadata['action_key']);
        $this->assertSame('Create a 2027 annual review cycle', $metadata['prompt']);

        // Chaining is documented as not implemented; half-building it would be
        // worse than leaving it out.
        $this->assertNull($row->chain_hash);
    }

    /** The exchange is stored like any other, so a reload shows what happened. */
    public function test_the_action_result_is_part_of_the_transcript(): void
    {
        $this->planningProvider($this->cyclePlan());
        $admin = $this->linkedUser('admin', 'admin@hospital.test');

        $sessionId = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->json('session_id');

        $messages = $this->actingAs($admin)
            ->getJson("/ai/sessions/{$sessionId}/messages")
            ->assertOk()
            ->json('messages');

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Review cycle created', $messages[1]['message']);
    }

    /**
     * The controller's own validation runs, because the controller's own method
     * is what runs. Nothing is written and the error is reported as chat.
     */
    public function test_a_plan_that_fails_the_controllers_validation_changes_nothing(): void
    {
        $plan = $this->cyclePlan();
        $plan['params']['end_date'] = '2026-01-01';   // before start_date

        $this->planningProvider($plan);

        $response = $this->actingAs($this->linkedUser('admin', 'admin@hospital.test'))
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->assertOk()
            ->assertJsonPath('action_status', 'error');

        $this->assertStringContainsString('rejected', $response->json('response'));
        $this->assertDatabaseCount('review_cycles', 0);
        $this->assertDatabaseCount('audit_trails', 0);
    }

    /** A detail the model could not fill is asked for, never invented. */
    public function test_a_missing_parameter_is_asked_for_rather_than_guessed(): void
    {
        $this->planningProvider([
            'action' => 'performance.cycle.create',
            'params' => ['cycle_type' => 'annual'],
            'missing' => ['cycle_name', 'start_date'],
            'summary' => 'Create a review cycle.',
        ]);

        $response = $this->actingAs($this->linkedUser('admin', 'admin@hospital.test'))
            ->postJson('/ai/query', ['query' => 'Create a new review cycle'])
            ->assertOk()
            ->assertJsonPath('action_status', null);

        $this->assertStringContainsString('cycle name', $response->json('response'));
        $this->assertStringContainsString('start date', $response->json('response'));
        $this->assertDatabaseCount('review_cycles', 0);
    }

    /**
     * UserController::store validates password with `confirmed`, which needs a
     * matching password_confirmation field. That rule guards against a typo
     * across two form inputs; chat has one value and no second input, so the
     * registry's `mirror` copies it and the rule stops being an unsatisfiable
     * barrier. Found in the browser: without this the reply was "That was
     * rejected: The password field confirmation does not match."
     */
    public function test_a_password_confirmation_is_mirrored_so_account_creation_succeeds(): void
    {
        $this->planningProvider([
            'action' => 'user.create',
            'params' => [
                'name' => 'Temp Tester',
                'email' => 'temp.tester@hospital.test',
                'password' => 'Temp12345!',
                'role' => 'staff',
            ],
            'missing' => [],
            'summary' => 'Create a staff login for Temp Tester.',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/ai/query', ['query' => 'Create a staff account for Temp Tester'])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok');

        $this->assertStringNotContainsString('confirmation', $response->json('response'));
        $this->assertDatabaseHas('users', [
            'email' => 'temp.tester@hospital.test',
            'role' => 'staff',
        ]);

        // The mirrored field is a validation artefact, never a stored column.
        $this->assertNotSame(
            'Temp12345!',
            DB::table('users')->where('email', 'temp.tester@hospital.test')->value('password'),
            'The password was stored in the clear.'
        );
    }

    /* ────────────────────── destructive confirmation ────────────────────── */

    /**
     * user.delete is the cleanest destructive action to test: no FK, no row-level
     * scoping, and the target is the users table so no employee is needed. The
     * fake planner must return a params key matching the registry's URI segment
     * name ("user") so prepare() can resolve it.
     */
    public function test_a_destructive_action_parks_and_waits_for_confirm(): void
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
            ->assertOk()
            ->json('session_id');

        // Read the stored reply back — the controller inserted it as an ai-turn.
        $messages = DB::table('ai_chat_messages')
            ->where('session_id', $sessionId)
            ->where('role', 'ai')
            ->orderByDesc('seq')
            ->value('message');

        $this->assertStringContainsString('bob@hospital.test', $messages);
        $this->assertStringContainsString('confirm', $messages);
        $this->assertStringContainsString('cannot be undone', $messages);

        // Nothing was deleted yet.
        $this->assertDatabaseHas('users', ['id' => $bob->id]);
        $this->assertDatabaseCount('audit_trails', 0);

        // The pending action is stored so a reload survives the park.
        $this->assertNotNull(
            DB::table('ai_chat_sessions')->where('id', $sessionId)->value('pending_action')
        );
    }

    public function test_confirm_actually_performs_the_parked_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bob = User::factory()->create(['email' => 'bob@hospital.test', 'name' => 'Bob']);

        // The first message parks with the URI param filled so prepare() succeeds.
        $this->planningProvider([
            'action' => 'user.delete',
            'params' => ['user' => 'bob@hospital.test'],
            'missing' => [],
            'summary' => 'Delete user bob@hospital.test.',
        ]);

        $sessionId = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'Delete the user bob'])
            ->json('session_id');

        // Confirm — the pending_action fires before the planner, so this reply is
        // never read. A real model returning "none" still wouldn't fire.
        $this->planningProvider('{"action":"none"}');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'confirm', 'session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok')
            ->assertJsonPath('pending_confirm', false);

        $this->assertDatabaseMissing('users', ['id' => $bob->id]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'ai_delete',
            'resource_type' => 'users',
            'resource_id' => (string) $bob->id,
        ]);
    }

    /** Anything except an explicit yes must cancel. */
    public function test_anything_but_confirm_cancels_the_parked_action(): void
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

        $this->planningProvider('{"action":"none"}');

        $response = $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'wait no', 'session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('action_status', null);

        $this->assertStringContainsString('Cancelled', $response->json('response'));
        $this->assertDatabaseHas('users', ['id' => $bob->id]);
    }

    /**
     * A stale offer must not run. Five minutes is the TTL; this pins that the
     * check actually runs.
     */
    public function test_a_stale_pending_action_is_ignored(): void
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

        // Push the pending_action_at back six minutes.
        DB::table('ai_chat_sessions')
            ->where('id', $sessionId)
            ->update(['pending_action_at' => now()->subMinutes(6)]);

        // "confirm" now fires against nothing, so the planner runs and sees "none".
        $this->planningProvider('{"action":"none"}');

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'confirm', 'session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('pending_confirm', false);

        $this->assertDatabaseHas('users', ['id' => $bob->id]);
    }

    /* ───────────────────────── role-based refusal ───────────────────────── */

    /**
     * A supervisor cannot create a review cycle. The route is role:admin,hr_manager
     * — the planner gets a catalogue without this action and returns null even
     * though the fake was told to return it (the re-check kills it). The controller
     * then falls through to the conversational path.
     */
    public function test_a_supervisor_cannot_create_a_review_cycle(): void
    {
        $this->planningProvider($this->cyclePlan());

        $response = $this->actingAs($this->linkedUser('supervisor', 'supervisor@hospital.test'))
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->assertOk()
            // Planner re-check refused it → falls through to conversational path.
            // The fake has no conversational reply (it only returns the plan JSON
            // that decode() already handled), but the test cares about the outcome:
            // no action_status means no write was performed.
            ->assertJsonPath('action_status', null);

        $this->assertDatabaseCount('review_cycles', 0);
    }

    /** Staff have exactly seven self-service actions, none of which create cycles. */
    public function test_staff_cannot_create_a_review_cycle(): void
    {
        $this->planningProvider($this->cyclePlan());

        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->postJson('/ai/query', ['query' => 'Create a 2027 annual review cycle'])
            ->assertOk()
            ->assertJsonPath('action_status', null);

        $this->assertDatabaseCount('review_cycles', 0);
    }

    /**
     * AiAccessPolicy still runs ahead of the action pipeline. A supervisor asking
     * to delete a user is refused before the planner runs, so the cost is zero AI
     * calls rather than two.
     */
    public function test_a_blocked_topic_is_refused_before_the_planner_runs(): void
    {
        $spy = $this->planningProvider('{"action":"user.delete"}');

        $response = $this->actingAs($this->linkedUser('supervisor', 'supervisor@hospital.test'))
            ->postJson('/ai/query', ['query' => 'Delete the user bob@hospital.test'])
            ->assertOk();

        $this->assertSame(0, $spy->calls, 'The planner was called for a blocked topic.');
        $this->assertStringStartsWith('🔒', $response->json('response'));
        $this->assertStringContainsString('user', $response->json('response'));
    }

    /* ───────────────────────── conversational fallback ──────────────────────── */

    /**
     * When the plan is null — because the message was not a command, or the model
     * hallucinated a key, or the role lacks the returned action — the controller
     * falls through to the ordinary conversational path.
     */
    public function test_a_question_is_answered_conversationally_not_executed(): void
    {
        $this->planningProvider('{"action":"none"}');

        $response = $this->actingAs($this->linkedUser('admin', 'admin@hospital.test'))
            ->postJson('/ai/query', ['query' => 'How do I create a review cycle?'])
            ->assertOk()
            ->assertJsonPath('action_status', null)
            ->assertJsonPath('pending_confirm', false);

        $this->assertDatabaseCount('review_cycles', 0);
        $this->assertDatabaseCount('audit_trails', 0);
    }

    /* ──────────────────────── reaching the planner at all ─────────────────────── */

    /**
     * The verb pre-filter decides whether a message is even shown to the planner,
     * and a message it rejects lands on the conversational path — where the model
     * is free to narrate a success that never happened. That is exactly what was
     * seen in the browser: "reactivate maria santos" contained no listed verb, so
     * the planner never ran and the reply claimed the record had been restored
     * while employment_status was still 'terminated'.
     *
     * Status changes are ordinary employee.update calls, so the fix is the verb
     * list, not the executor. Asserted through the planner spy rather than on an
     * outcome: what regressed was whether the classifier was consulted.
     */
    public function test_a_status_change_instruction_reaches_the_planner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // One spy for the whole loop, counted by delta: the provider is resolved
        // into the controller once, so re-binding between requests would leave
        // the controller holding the first spy and every later count at zero.
        $spy = $this->planningProvider('{"action":"none"}');

        foreach ([
            'reactivate Maria Santos',
            'make Maria Santos active again',
            'restore Maria Santos',
            'deactivate Maria Santos',
            'mark Maria Santos as terminated',
            'suspend Maria Santos',
        ] as $prompt) {
            $before = $spy->calls;

            $this->actingAs($admin)
                ->postJson('/ai/query', ['query' => $prompt])
                ->assertOk();

            // Two calls: the classifier, then the conversational answer once the
            // fake returns "none". One call would mean the pre-filter skipped it.
            $this->assertSame(
                2,
                $spy->calls - $before,
                "The planner was never consulted for: {$prompt}"
            );
        }
    }

    /** A question still costs one call — the pre-filter's whole purpose. */
    public function test_a_question_still_skips_the_planner(): void
    {
        $spy = $this->planningProvider('{"action":"none"}');

        $this->actingAs($this->linkedUser('admin', 'admin@hospital.test'))
            ->postJson('/ai/query', ['query' => 'Who can see the succession module?'])
            ->assertOk();

        $this->assertSame(1, $spy->calls);
    }

    /* ─────────────────────────── partial updates ─────────────────────────── */

    /**
     * Update controllers are written against a web form that posts the whole
     * record — every field `required`, every column overwritten. A chat
     * instruction names one field, so the executor fills the rest from the row
     * as it stands before calling.
     *
     * Found in the browser: "set Maria Santos on probation" came back as "That
     * was rejected: The first name field is required. The last name field is
     * required..." for fields nobody had mentioned.
     */
    public function test_a_one_field_update_keeps_every_other_field(): void
    {
        $admin = $this->linkedUser('admin', 'admin@hospital.test');
        $target = DB::table('employees')->where('email', 'admin@hospital.test')->first();

        $this->planningProvider([
            'action' => 'employee.update',
            'params' => [
                'id' => $target->employee_id,
                'employment_status' => 'probationary',
            ],
            'missing' => [],
            'summary' => 'Put Test Person on probation.',
        ]);

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'set Test Person on probation'])
            ->assertOk()
            ->assertJsonPath('action_status', 'ok');

        $after = DB::table('employees')->where('employee_id', $target->employee_id)->first();

        $this->assertSame('probationary', $after->employment_status);

        // The fields the instruction never mentioned are untouched, not blanked.
        $this->assertSame($target->first_name, $after->first_name);
        $this->assertSame($target->last_name, $after->last_name);
        $this->assertSame($target->email, $after->email);
        $this->assertSame($target->department_id, $after->department_id);
        $this->assertSame($target->role_id, $after->role_id);
        $this->assertSame($target->hire_date, $after->hire_date);
    }

    /** Pre-filling must not let the model reach a column the registry hides. */
    public function test_prefill_does_not_widen_the_whitelist(): void
    {
        $admin = $this->linkedUser('admin', 'admin@hospital.test');
        $target = DB::table('employees')->where('email', 'admin@hospital.test')->first();

        $this->planningProvider([
            'action' => 'employee.update',
            'params' => [
                'id' => $target->employee_id,
                'employment_status' => 'on_leave',
                // Not in the registry's param list for employee.update.
                'employee_code' => 'EMP-HACKED',
            ],
            'missing' => [],
            'summary' => 'Put Test Person on leave.',
        ]);

        $this->actingAs($admin)
            ->postJson('/ai/query', ['query' => 'put Test Person on leave'])
            ->assertOk();

        $this->assertSame(
            $target->employee_code,
            DB::table('employees')->where('employee_id', $target->employee_id)->value('employee_code')
        );
    }
}
