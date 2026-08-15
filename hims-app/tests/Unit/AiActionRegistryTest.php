<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Ai\AiActionRegistry;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The registry is the permission boundary for everything the assistant can do,
 * and it derives that boundary from the router rather than from a hand-written
 * role list. Two failure modes are worth pinning.
 *
 * A ROUTE NAME TYPO is silent. An entry pointing at a name no route carries is
 * simply skipped by availableTo(), so the action quietly stops existing and the
 * assistant answers "I don't think HIMS does that" forever.
 *
 * A ROUTE CARRYING TWO `role:` MIDDLEWARES is the one that bites. employees.
 * destroy and succession.candidates.store both inherit role:admin,hr_manager,
 * supervisor from the prefix group and then narrow it on the route itself.
 * Laravel runs both; reading only the first hands supervisors the delete.
 *
 * No database: availableTo() reads $user->role and the route table, so the users
 * here are unsaved models.
 */
class AiActionRegistryTest extends TestCase
{
    private function user(string $role): User
    {
        return new User(['role' => $role]);
    }

    /** Every action's route name must resolve, or the action cannot be invoked. */
    public function test_every_registered_action_points_at_a_real_route(): void
    {
        // An admin passes every role check, so this is the full catalogue.
        $actions = AiActionRegistry::availableTo($this->user('admin'));

        $this->assertNotEmpty($actions);

        foreach ($actions as $key => $spec) {
            $this->assertNotNull(
                Route::getRoutes()->getByName($spec['route']),
                "Action {$key} names route {$spec['route']}, which does not exist."
            );
        }
    }

    /**
     * The bug this test exists for: a supervisor may reach the employees prefix
     * group but not the destroy route inside it.
     */
    public function test_a_second_role_middleware_narrows_and_is_not_ignored(): void
    {
        $middleware = Route::getRoutes()->getByName('employees.destroy')->gatherMiddleware();
        $roleLayers = array_values(array_filter($middleware, fn ($m) => str_starts_with($m, 'role:')));

        $this->assertCount(2, $roleLayers, 'employees.destroy no longer carries two role layers — retarget this test.');

        $this->assertNull(
            AiActionRegistry::get('employee.delete', $this->user('supervisor')),
            'A supervisor was granted employee.delete: only the first role: layer was read.'
        );
        $this->assertNotNull(AiActionRegistry::get('employee.delete', $this->user('admin')));
        $this->assertNotNull(AiActionRegistry::get('employee.delete', $this->user('hr_manager')));
    }

    /** The same shape on a create route, so the fix is not specific to deletes. */
    public function test_supervisors_cannot_nominate_successors(): void
    {
        $this->assertNull(AiActionRegistry::get('succession.candidate.create', $this->user('supervisor')));
        $this->assertNotNull(AiActionRegistry::get('succession.candidate.create', $this->user('hr_manager')));
    }

    /**
     * Staff hold exactly the self-service actions — the routes with no role:
     * middleware at all. Pinned as a set: an action becoming reachable by staff
     * is the kind of change that must be deliberate.
     *
     * Six, not seven. `learning.enroll` was removed when course self-enrolment
     * was deleted; a course enrolment is now only ever created from Required
     * Training, which is supervisor-and-up, and there is no assistant action for
     * it. See test_staff_have_no_route_into_a_course below — the loss is asserted
     * rather than left as a gap in this list.
     */
    public function test_staff_hold_only_the_self_service_actions(): void
    {
        $keys = array_keys(AiActionRegistry::availableTo($this->user('staff')));
        sort($keys);

        $this->assertSame([
            'learning.cpd.log',
            'training.feedback.submit',
            'training.register',
        ], $keys);
    }

    /**
     * The capability that was removed, asserted from the outside.
     *
     * Deleting the entry from the set above would also pass if the action had
     * merely been renamed or re-gated, so this pins the actual boundary: no
     * enrolment action exists for anybody, at any seniority, and the one
     * remaining way a person joins a course is an action no staff member holds.
     */
    public function test_staff_have_no_route_into_a_course(): void
    {
        foreach (['staff', 'supervisor', 'hr_manager', 'admin'] as $role) {
            $this->assertNull(
                AiActionRegistry::get('learning.enroll', $this->user($role)),
                "learning.enroll is reachable again by {$role}."
            );
        }

        $staff = AiActionRegistry::catalogueFor($this->user('staff'));

        $this->assertStringNotContainsString('enrol', mb_strtolower($staff));
    }

    /** Roles widen monotonically: nothing a supervisor may do is denied to HR. */
    public function test_permissions_widen_with_seniority(): void
    {
        $staff = array_keys(AiActionRegistry::availableTo($this->user('staff')));
        $supervisor = array_keys(AiActionRegistry::availableTo($this->user('supervisor')));
        $hr = array_keys(AiActionRegistry::availableTo($this->user('hr_manager')));
        $admin = array_keys(AiActionRegistry::availableTo($this->user('admin')));

        $this->assertEmpty(array_diff($staff, $supervisor));
        $this->assertEmpty(array_diff($supervisor, $hr));
        $this->assertEmpty(array_diff($hr, $admin));

        // And they are genuinely different, not all the same list.
        $this->assertGreaterThan(count($staff), count($supervisor));
        $this->assertGreaterThan(count($hr), count($admin));
    }

    /** Only the admin route set includes account administration. */
    public function test_account_administration_is_admin_only(): void
    {
        foreach (['user.create', 'user.update', 'user.delete'] as $key) {
            $this->assertNotNull(AiActionRegistry::get($key, $this->user('admin')), $key);
            $this->assertNull(AiActionRegistry::get($key, $this->user('hr_manager')), $key);
            $this->assertNull(AiActionRegistry::get($key, $this->user('staff')), $key);
        }
    }

    public function test_an_unknown_action_key_resolves_to_null(): void
    {
        $this->assertNull(AiActionRegistry::get('performance.cycle.obliterate', $this->user('admin')));
        $this->assertNull(AiActionRegistry::get('', $this->user('admin')));
    }

    /**
     * Deletes and withdrawals must stay flagged: the flag is what routes them
     * through the confirm step instead of executing on the spot.
     *
     * @return array<string, array{string}>
     */
    public static function destructiveActions(): array
    {
        return [
            'delete an employee' => ['employee.delete'],
            'delete a user' => ['user.delete'],
            'withdraw a candidate' => ['succession.candidate.withdraw'],
            'delete a milestone' => ['succession.milestone.delete'],
        ];
    }

    #[DataProvider('destructiveActions')]
    public function test_destructive_actions_are_flagged(string $key): void
    {
        $spec = AiActionRegistry::get($key, $this->user('admin'));

        $this->assertNotNull($spec, "{$key} is missing from the registry.");
        $this->assertTrue($spec['destructive'] ?? false, "{$key} is no longer flagged destructive.");
    }

    /** Nothing else is: a mis-flagged create would demand a pointless confirm. */
    public function test_no_other_action_is_flagged_destructive(): void
    {
        $flagged = [];

        foreach (AiActionRegistry::availableTo($this->user('admin')) as $key => $spec) {
            if ($spec['destructive'] ?? false) {
                $flagged[] = $key;
            }
        }

        sort($flagged);
        $expected = array_column(self::destructiveActions(), 0);
        sort($expected);

        $this->assertSame($expected, $flagged);
    }

    /**
     * The catalogue is the only place the model learns what it may call, so an
     * action absent from it cannot be invoked — and one present that the role
     * lacks would invite a refusal it should never have been offered.
     */
    public function test_the_catalogue_shows_only_what_the_role_may_do(): void
    {
        $staff = AiActionRegistry::catalogueFor($this->user('staff'));

        $this->assertStringContainsString('learning.cpd.log', $staff);
        $this->assertStringNotContainsString('user.delete', $staff);
        $this->assertStringNotContainsString('performance.cycle.create', $staff);

        $admin = AiActionRegistry::catalogueFor($this->user('admin'));

        $this->assertStringContainsString('user.delete', $admin);
        $this->assertStringContainsString('[DESTRUCTIVE]', $admin);
        // Parameters are documented, not just keys — the planner fills them.
        $this->assertStringContainsString('cycle_type', $admin);
    }

    /** Every spec carries what the executor and the audit row need. */
    public function test_each_action_declares_a_table_a_key_and_a_label(): void
    {
        foreach (AiActionRegistry::availableTo($this->user('admin')) as $key => $spec) {
            $this->assertArrayHasKey('table', $spec, $key);
            $this->assertArrayHasKey('pk', $spec, $key);
            $this->assertArrayHasKey('params', $spec, $key);
            $this->assertIsArray($spec['params'], $key);
            $this->assertNotEmpty($spec['label'] ?? '', $key);
            // audit_trails.resource_type is varchar(50).
            $this->assertLessThanOrEqual(50, strlen($spec['table']), $key);
        }
    }

    /**
     * Laravel's confirmed rule prevents a typo across two password form fields;
     * chat has no second field. A registry declaring mirror => [from => to] has
     * the executor fill `to` from `from` before the request reaches the
     * controller, so the validation passes without the model guessing the value.
     */
    public function test_user_create_declares_password_confirmation_mirror(): void
    {
        $spec = AiActionRegistry::get('user.create', $this->user('admin'));

        $this->assertArrayHasKey('mirror', $spec);
        $this->assertSame(['password' => 'password_confirmation'], $spec['mirror']);
    }
}
