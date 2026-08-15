<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeopleManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $departmentId;

    private string $roleId;

    private string $hrEmployeeId;

    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->departmentId = $this->department('People Operations');
        $this->roleId = $this->jobRole('Hospital Employee');
        $this->hrEmployeeId = $this->employee('HR', 'Actor', 'hr-actor@example.org');
        $this->hrUser = $this->user('hr_manager', $this->hrEmployeeId);
    }

    public function test_reports_to_lists_only_active_people_managers_with_review_access(): void
    {
        $eligible = $this->employee('Eligible', 'Manager', 'eligible@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('supervisor', $eligible);

        $notFlagged = $this->employee('Not', 'Flagged', 'not-flagged@example.org');
        $this->user('supervisor', $notFlagged);

        $staffOnly = $this->employee('Staff', 'Access', 'staff-access@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('staff', $staffOnly);

        $inactive = $this->employee('Inactive', 'Manager', 'inactive@example.org', [
            'is_people_manager' => true,
            'employment_status' => 'on_leave',
        ]);
        $this->user('supervisor', $inactive);

        $explicitHrManager = $this->employee('Explicit', 'HR Manager', 'explicit-hr@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('hr_manager', $explicitHrManager);

        $unflaggedAdmin = $this->employee('Unflagged', 'Admin', 'unflagged-admin@example.org');
        $this->user('admin', $unflaggedAdmin);

        $response = $this->actingAs($this->hrUser)->get(route('employees.create'));

        $response->assertOk()
            ->assertSee('Eligible Manager')
            ->assertSee('Explicit HR Manager')
            ->assertDontSee('Not Flagged')
            ->assertDontSee('Staff Access')
            ->assertDontSee('Inactive Manager')
            ->assertDontSee('Unflagged Admin');
    }

    public function test_forged_assignments_are_rejected_and_eligible_managers_are_accepted(): void
    {
        $ordinary = $this->employee('Ordinary', 'Supervisor Account', 'ordinary@example.org');
        $this->user('supervisor', $ordinary);

        $eligible = $this->employee('Valid', 'Manager', 'valid-manager@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('supervisor', $eligible);

        $this->actingAs($this->hrUser)
            ->post(route('employees.store'), $this->createPayload('rejected@example.org', [
                'supervisor_id' => $ordinary,
            ]))
            ->assertSessionHasErrors('supervisor_id');

        $this->assertDatabaseMissing('employees', ['email' => 'rejected@example.org']);

        $this->actingAs($this->hrUser)
            ->post(route('employees.store'), $this->createPayload('accepted@example.org', [
                'supervisor_id' => $eligible,
            ]))
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'email' => 'accepted@example.org',
            'supervisor_id' => $eligible,
        ]);
    }

    public function test_self_reporting_and_short_or_long_reporting_loops_are_rejected(): void
    {
        $ana = $this->eligibleManager('Ana', 'Manager', 'ana@example.org');
        $maria = $this->eligibleManager('Maria', 'Manager', 'maria@example.org', ['supervisor_id' => $ana]);
        $jose = $this->eligibleManager('Jose', 'Manager', 'jose@example.org', ['supervisor_id' => $maria]);

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $ana), $this->updatePayload($ana, [
                'supervisor_id' => $ana,
                'is_people_manager' => 1,
            ]))
            ->assertSessionHasErrors('supervisor_id');

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $ana), $this->updatePayload($ana, [
                'supervisor_id' => $jose,
                'is_people_manager' => 1,
            ]))
            ->assertSessionHasErrors('supervisor_id');

        $this->assertDatabaseHas('employees', ['employee_id' => $ana, 'supervisor_id' => null]);
    }

    public function test_manager_with_reports_cannot_lose_people_manager_status(): void
    {
        $manager = $this->employee('Protected', 'Manager', 'protected@example.org', [
            'is_people_manager' => true,
        ]);
        $this->employee('Direct', 'Report', 'direct-report@example.org', ['supervisor_id' => $manager]);

        $payload = $this->updatePayload($manager);
        unset($payload['is_people_manager']);

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $manager), $payload)
            ->assertSessionHasErrors('is_people_manager');

        $this->assertDatabaseHas('employees', ['employee_id' => $manager, 'is_people_manager' => true]);
    }

    public function test_people_manager_status_never_promotes_the_linked_account(): void
    {
        $employee = $this->employee('Future', 'Manager', 'future-manager@example.org');
        $account = $this->user('staff', $employee);

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $employee), $this->updatePayload($employee, [
                'is_people_manager' => 1,
            ]))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', ['employee_id' => $employee, 'is_people_manager' => true]);
        $this->assertDatabaseHas('users', ['id' => $account->id, 'role' => 'staff']);
    }

    public function test_existing_unavailable_manager_remains_visible_and_can_be_preserved(): void
    {
        $legacyManager = $this->employee('Legacy', 'Manager', 'legacy@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('staff', $legacyManager);
        $employee = $this->employee('Existing', 'Report', 'existing-report@example.org', [
            'supervisor_id' => $legacyManager,
        ]);

        $this->actingAs($this->hrUser)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('Legacy Manager')
            ->assertSee('Setup incomplete');

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $employee), $this->updatePayload($employee, [
                'supervisor_id' => $legacyManager,
            ]))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', [
            'employee_id' => $employee,
            'supervisor_id' => $legacyManager,
        ]);
    }

    public function test_inactive_manager_status_is_allowed_with_named_reassignment_warning(): void
    {
        $manager = $this->employee('Leave', 'Manager', 'leave-manager@example.org', [
            'is_people_manager' => true,
        ]);
        $this->employee('Needs', 'Reassignment', 'needs-reassignment@example.org', [
            'supervisor_id' => $manager,
        ]);

        $this->actingAs($this->hrUser)
            ->put(route('employees.update', $manager), $this->updatePayload($manager, [
                'employment_status' => 'on_leave',
                'is_people_manager' => 1,
            ]))
            ->assertRedirect(route('employees.show', $manager))
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, 'Needs Reassignment'));

        $this->assertDatabaseHas('employees', [
            'employee_id' => $manager,
            'employment_status' => 'on_leave',
        ]);
    }

    public function test_manager_setup_report_lists_each_mismatch(): void
    {
        $withoutAccount = $this->employee('No', 'Account', 'no-account@example.org', [
            'is_people_manager' => true,
        ]);
        $staffOnly = $this->employee('Only', 'Staff', 'only-staff@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('staff', $staffOnly);
        $notManager = $this->employee('Supervisor', 'Mismatch', 'supervisor-mismatch@example.org');
        $this->user('supervisor', $notManager);
        $inactive = $this->employee('Inactive', 'With Reports', 'inactive-reports@example.org', [
            'is_people_manager' => true,
            'employment_status' => 'on_leave',
        ]);
        $this->employee('Active', 'Report', 'active-report@example.org', ['supervisor_id' => $inactive]);

        $this->actingAs($this->hrUser)
            ->get(route('employees.manager-setup'))
            ->assertOk()
            ->assertSee('No Account')
            ->assertSee('Only Staff')
            ->assertSee('Supervisor Mismatch')
            ->assertSee('Inactive With Reports')
            ->assertSee('Active Report');
    }

    public function test_incomplete_manager_account_opens_only_an_audited_hr_exception_path(): void
    {
        $manager = $this->employee('Staff', 'Manager', 'staff-manager@example.org', [
            'is_people_manager' => true,
        ]);
        $this->user('staff', $manager);
        $report = $this->employee('Review', 'Subject', 'review-subject@example.org', [
            'supervisor_id' => $manager,
        ]);
        $cycle = $this->cycle($this->hrEmployeeId);

        $this->actingAs($this->hrUser)
            ->post(route('performance.reviews.store'), [
                'employee_id' => $report,
                'cycle_id' => $cycle,
                'review_type' => 'standard',
                'exception_reason' => 'Manager account is awaiting access correction.',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('performance_reviews', [
            'employee_id' => $report,
            'reviewer_id' => $this->hrEmployeeId,
            'is_exception_review' => true,
            'exception_basis' => 'supervisor_account_unavailable',
        ]);
    }

    public function test_migration_backfills_existing_managers_without_changing_reporting_lines(): void
    {
        $manager = $this->employee('Existing', 'Manager', 'migration-manager@example.org');
        $report = $this->employee('Existing', 'Report', 'migration-report@example.org', [
            'supervisor_id' => $manager,
        ]);

        $migration = require database_path('migrations/2026_08_15_000030_add_people_manager_to_employees.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('employees', [
            'employee_id' => $manager,
            'is_people_manager' => true,
        ]);
        $this->assertDatabaseHas('employees', [
            'employee_id' => $report,
            'supervisor_id' => $manager,
        ]);
    }

    private function eligibleManager(string $firstName, string $lastName, string $email, array $overrides = []): string
    {
        $employee = $this->employee($firstName, $lastName, $email, array_merge([
            'is_people_manager' => true,
        ], $overrides));
        $this->user('supervisor', $employee);

        return $employee;
    }

    private function createPayload(string $email, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Employee',
            'email' => $email,
            'department_id' => $this->departmentId,
            'role_id' => $this->roleId,
            'position_title' => 'Registered Nurse',
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'supervisor_id' => null,
        ], $overrides);
    }

    private function updatePayload(string $employeeId, array $overrides = []): array
    {
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();

        return array_merge([
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'department_id' => $employee->department_id,
            'role_id' => $employee->role_id,
            'position_title' => $employee->position_title,
            'hire_date' => $employee->hire_date,
            'employment_status' => $employee->employment_status,
            'supervisor_id' => $employee->supervisor_id,
            'is_people_manager' => $employee->is_people_manager ? 1 : 0,
        ], $overrides);
    }

    private function employee(string $firstName, string $lastName, string $email, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(8)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'department_id' => $this->departmentId,
            'role_id' => $this->roleId,
            'position_title' => 'Registered Nurse',
            'hire_date' => now()->subYears(2)->toDateString(),
            'employment_status' => 'active',
            'is_people_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function user(string $role, ?string $employeeId): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst(str_replace('_', ' ', $role)).' User',
            'email' => $role.'-'.Str::lower(Str::random(8)).'@example.org',
            'password' => bcrypt('password'),
            'role' => $role,
            'employee_id' => $employeeId,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function department(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function jobRole(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => $name.' '.Str::random(6),
            'role_slug' => 'role-'.Str::lower(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function cycle(string $createdBy): string
    {
        $id = (string) Str::uuid();
        DB::table('review_cycles')->insert([
            'cycle_id' => $id,
            'cycle_name' => 'Annual Review '.Str::random(4),
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
