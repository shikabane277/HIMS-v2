<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewCrudsAndRbacTest extends TestCase
{
    use RefreshDatabase;

    private string $deptId;

    private string $roleId;

    private string $adminEmpId;

    private User $adminUser;

    private string $supervisorEmpId;

    private User $supervisorUser;

    private string $staffEmpId;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'concat',
                fn (...$parts) => implode('', array_map(fn ($part) => $part ?? '', $parts))
            );
        }

        $this->deptId = $this->createDepartment('Emergency');
        $this->roleId = $this->createRole('Registered Nurse');

        $this->adminEmpId = $this->createEmployee('Super', 'Admin', 'admin@hospital.test');
        $this->adminUser = $this->createUser('admin', $this->adminEmpId);

        $this->supervisorEmpId = $this->createEmployee('Head', 'Nurse', 'supervisor@hospital.test');
        $this->supervisorUser = $this->createUser('supervisor', $this->supervisorEmpId);

        $this->staffEmpId = $this->createEmployee('Staff', 'Nurse', 'staff@hospital.test', [
            'supervisor_id' => $this->supervisorEmpId,
        ]);
        $this->staffUser = $this->createUser('staff', $this->staffEmpId);
    }

    public function test_admin_and_hr_can_update_and_delete_venue_and_prevent_in_use_delete(): void
    {
        $venueId = (string) Str::uuid();
        DB::table('training_venues')->insert([
            'venue_id' => $venueId,
            'venue_name' => 'Auditorium A',
            'building' => 'Main Wing',
            'capacity' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff / Supervisor cannot update
        $this->actingAs($this->staffUser)
            ->put(route('training.venues.update', $venueId), [
                'venue_name' => 'Renamed Auditorium',
                'capacity' => 120,
            ])
            ->assertStatus(403);

        // Admin can update
        $this->actingAs($this->adminUser)
            ->put(route('training.venues.update', $venueId), [
                'venue_name' => 'Renamed Auditorium',
                'building' => 'North Wing',
                'capacity' => 120,
                'is_active' => true,
            ])
            ->assertRedirect(route('training.venues.index'));

        $this->assertDatabaseHas('training_venues', [
            'venue_id' => $venueId,
            'venue_name' => 'Renamed Auditorium',
            'building' => 'North Wing',
            'capacity' => 120,
        ]);

        // Attach training session to venue
        $sessionId = (string) Str::uuid();
        DB::table('training_sessions')->insert([
            'session_id' => $sessionId,
            'venue_id' => $venueId,
            'title' => 'BLS Training',
            'category' => 'clinical',
            'instructor_id' => $this->adminEmpId,
            'session_date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'capacity' => 30,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deleting when in use is prevented
        $this->actingAs($this->adminUser)
            ->delete(route('training.venues.destroy', $venueId))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('training_venues', ['venue_id' => $venueId]);

        // Remove session and delete venue succeeds
        DB::table('training_sessions')->where('session_id', $sessionId)->delete();

        $this->actingAs($this->adminUser)
            ->delete(route('training.venues.destroy', $venueId))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('training_venues', ['venue_id' => $venueId]);
    }

    public function test_admin_and_hr_can_update_and_delete_domain_and_prevent_in_use_delete(): void
    {
        $domainId = (string) Str::uuid();
        DB::table('competency_domains')->insert([
            'domain_id' => $domainId,
            'domain_name' => 'Clinical Safety',
            'description' => 'Safety practices',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff / supervisor cannot update or delete
        $this->actingAs($this->staffUser)
            ->put(route('competency.domains.update', $domainId), [
                'domain_name' => 'Updated Safety',
            ])
            ->assertStatus(403);

        $this->actingAs($this->supervisorUser)
            ->delete(route('competency.domains.destroy', $domainId))
            ->assertStatus(403);

        // Admin updates domain
        $this->actingAs($this->adminUser)
            ->from(route('competency.index'))
            ->put(route('competency.domains.update', $domainId), [
                'domain_name' => 'Updated Clinical Safety',
                'description' => 'Updated description',
            ])
            ->assertRedirect(route('competency.index'));

        $this->assertDatabaseHas('competency_domains', [
            'domain_id' => $domainId,
            'domain_name' => 'Updated Clinical Safety',
        ]);

        // Add a category to domain
        $catId = (string) Str::uuid();
        DB::table('competency_categories')->insert([
            'category_id' => $catId,
            'domain_id' => $domainId,
            'category_name' => 'Infection Prevention',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deleting when categories exist is prevented
        $this->actingAs($this->adminUser)
            ->delete(route('competency.domains.destroy', $domainId))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('competency_domains', ['domain_id' => $domainId]);

        // Remove category, delete domain succeeds
        DB::table('competency_categories')->where('category_id', $catId)->delete();

        $this->actingAs($this->adminUser)
            ->delete(route('competency.domains.destroy', $domainId))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('competency_domains', ['domain_id' => $domainId]);
    }

    public function test_admin_and_hr_can_manage_role_competency_requirements(): void
    {
        // Create domain, category, competency
        $domainId = (string) Str::uuid();
        DB::table('competency_domains')->insert([
            'domain_id' => $domainId,
            'domain_name' => 'Domain 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = (string) Str::uuid();
        DB::table('competency_categories')->insert([
            'category_id' => $catId,
            'domain_id' => $domainId,
            'category_name' => 'Category 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compId = (string) Str::uuid();
        DB::table('competencies')->insert([
            'competency_id' => $compId,
            'category_id' => $catId,
            'competency_code' => 'COMP-101',
            'competency_name' => 'Patient Triage',
            'required_proficiency' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff cannot view or store
        $this->actingAs($this->staffUser)
            ->get(route('competency.role-requirements.index'))
            ->assertStatus(403);

        // Admin views role requirements index
        $this->actingAs($this->adminUser)
            ->get(route('competency.role-requirements.index'))
            ->assertStatus(200)
            ->assertSee('Role Competency Requirements');

        // Admin creates role requirement
        $this->actingAs($this->adminUser)
            ->post(route('competency.role-requirements.store'), [
                'role_id' => $this->roleId,
                'competency_id' => $compId,
                'minimum_proficiency' => 4,
                'is_critical' => 1,
            ])
            ->assertSessionHas('success');

        $requirement = DB::table('role_competency_requirements')
            ->where('role_id', $this->roleId)
            ->where('competency_id', $compId)
            ->first();

        $this->assertNotNull($requirement);
        $this->assertEquals(4, $requirement->minimum_proficiency);
        $this->assertEquals(1, $requirement->is_critical);

        // Admin updates role requirement
        $this->actingAs($this->adminUser)
            ->put(route('competency.role-requirements.update', $requirement->id), [
                'minimum_proficiency' => 5,
                'is_critical' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('role_competency_requirements', [
            'id' => $requirement->id,
            'minimum_proficiency' => 5,
            'is_critical' => 0,
        ]);

        // Admin deletes role requirement
        $this->actingAs($this->adminUser)
            ->delete(route('competency.role-requirements.destroy', $requirement->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('role_competency_requirements', [
            'id' => $requirement->id,
        ]);
    }

    public function test_admin_and_hr_can_update_and_delete_department_and_prevent_staffed_delete(): void
    {
        $newDeptId = $this->createDepartment('Radiology');

        // Staff cannot update or delete
        $this->actingAs($this->staffUser)
            ->put(route('departments.update', $newDeptId), ['name' => 'Renamed Dept'])
            ->assertStatus(403);

        $this->actingAs($this->staffUser)
            ->delete(route('departments.destroy', $newDeptId))
            ->assertStatus(403);

        // Admin updates department
        $this->actingAs($this->adminUser)
            ->put(route('departments.update', $newDeptId), [
                'name' => 'Advanced Radiology',
                'is_clinical' => 1,
            ])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', [
            'department_id' => $newDeptId,
            'name' => 'Advanced Radiology',
        ]);

        // Attach an active employee to the department
        $empId = $this->createEmployee('Rad', 'Tech', 'rad@hospital.test', [
            'department_id' => $newDeptId,
        ]);

        // Attempt delete department with staff
        $this->actingAs($this->adminUser)
            ->delete(route('departments.destroy', $newDeptId))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['department_id' => $newDeptId]);

        // Reassign or remove employee from department
        DB::table('employees')->where('employee_id', $empId)->update(['department_id' => $this->deptId]);

        // Now delete succeeds
        $this->actingAs($this->adminUser)
            ->delete(route('departments.destroy', $newDeptId))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('departments', ['department_id' => $newDeptId]);
    }

    public function test_performance_goals_and_pip_crud_and_rbac(): void
    {
        $cycleId = (string) Str::uuid();
        DB::table('review_cycles')->insert([
            'cycle_id' => $cycleId,
            'cycle_name' => 'Annual 2026',
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'created_by' => $this->adminEmpId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewId = (string) Str::uuid();
        DB::table('performance_reviews')->insert([
            'review_id' => $reviewId,
            'cycle_id' => $cycleId,
            'employee_id' => $this->staffEmpId,
            'reviewer_id' => $this->supervisorEmpId,
            'review_type' => 'standard',
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff cannot add goal or PIP
        $this->actingAs($this->staffUser)
            ->post(route('performance.reviews.goals.store', $reviewId), [
                'goal_title' => 'Improve documentation',
            ])
            ->assertStatus(403);

        $this->actingAs($this->staffUser)
            ->post(route('performance.reviews.pip.store', $reviewId), [
                'start_date' => now()->toDateString(),
                'target_end_date' => now()->addMonths(3)->toDateString(),
                'action_steps' => 'Step 1',
            ])
            ->assertStatus(403);

        // Supervisor adds a goal
        $this->actingAs($this->supervisorUser)
            ->from(route('performance.show', $reviewId))
            ->post(route('performance.reviews.goals.store', $reviewId), [
                'goal_title' => 'Complete triage certification',
                'target_date' => now()->addMonths(2)->toDateString(),
                'status' => 'not_started',
            ])
            ->assertSessionHas('success');

        $goal = DB::table('review_goals')->where('review_id', $reviewId)->first();
        $this->assertNotNull($goal);
        $this->assertEquals('Complete triage certification', $goal->goal_title);

        // Supervisor updates the goal
        $this->actingAs($this->supervisorUser)
            ->from(route('performance.show', $reviewId))
            ->put(route('performance.reviews.goals.update', $goal->goal_id), [
                'status' => 'in_progress',
                'progress_pct' => 50,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('review_goals', [
            'goal_id' => $goal->goal_id,
            'status' => 'in_progress',
            'progress_pct' => 50,
        ]);

        // Supervisor initiates PIP
        $this->actingAs($this->supervisorUser)
            ->from(route('performance.show', $reviewId))
            ->post(route('performance.reviews.pip.store', $reviewId), [
                'start_date' => now()->toDateString(),
                'target_end_date' => now()->addMonths(3)->toDateString(),
                'action_steps' => "1. Shadow senior nurse\n2. Weekly chart audit",
                'notes' => 'Requires improved charting timeliness',
            ])
            ->assertSessionHas('success');

        $pip = DB::table('performance_improvement_plans')->where('triggered_by_review', $reviewId)->first();
        $this->assertNotNull($pip);
        $this->assertEquals('initiated', $pip->status);

        // Supervisor updates PIP
        $this->actingAs($this->supervisorUser)
            ->from(route('performance.show', $reviewId))
            ->put(route('performance.reviews.pip.update', $pip->pip_id), [
                'status' => 'completed',
                'notes' => 'All objectives met satisfactorily',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('performance_improvement_plans', [
            'pip_id' => $pip->pip_id,
            'status' => 'completed',
            'notes' => 'All objectives met satisfactorily',
        ]);
    }

    public function test_performance_cycle_status_filtering(): void
    {
        // 1 Active cycle
        DB::table('review_cycles')->insert([
            'cycle_id' => (string) Str::uuid(),
            'cycle_name' => 'Cycle Active One',
            'cycle_type' => 'annual',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'active',
            'created_by' => $this->adminEmpId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 Planned cycle
        DB::table('review_cycles')->insert([
            'cycle_id' => (string) Str::uuid(),
            'cycle_name' => 'Cycle Planned Two',
            'cycle_type' => 'quarterly',
            'start_date' => now()->addMonths(1)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'status' => 'planned',
            'created_by' => $this->adminEmpId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 Closed cycle
        DB::table('review_cycles')->insert([
            'cycle_id' => (string) Str::uuid(),
            'cycle_name' => 'Cycle Closed Three',
            'cycle_type' => 'annual',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => 'closed',
            'created_by' => $this->adminEmpId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('performance.index', ['status' => 'active']))
            ->assertSee('Cycle Active One')
            ->assertDontSee('Cycle Planned Two')
            ->assertDontSee('Cycle Closed Three');

        $this->actingAs($this->adminUser)
            ->get(route('performance.index', ['status' => 'planned']))
            ->assertSee('Cycle Planned Two')
            ->assertDontSee('Cycle Active One')
            ->assertDontSee('Cycle Closed Three');

        $this->actingAs($this->adminUser)
            ->get(route('performance.index', ['status' => 'closed']))
            ->assertSee('Cycle Closed Three')
            ->assertDontSee('Cycle Active One')
            ->assertDontSee('Cycle Planned Two');
    }

    public function test_rbac_button_visibility_in_views(): void
    {
        // Staff viewing learning.index does NOT see New Course or New Pathway
        $this->actingAs($this->staffUser)
            ->get(route('learning.index'))
            ->assertDontSee('data-modal-open="courseCreateModal"', false)
            ->assertDontSee('data-modal-open="pathwayCreateModal"', false);

        // Admin viewing learning.index DOES see New Course and New Pathway
        $this->actingAs($this->adminUser)
            ->get(route('learning.index'))
            ->assertSee('data-modal-open="courseCreateModal"', false)
            ->assertSee('data-modal-open="pathwayCreateModal"', false);

        // Supervisor viewing competency.index does NOT see Role Requirements or Add Domain
        $this->actingAs($this->supervisorUser)
            ->get(route('competency.index'))
            ->assertDontSee('Role Requirements')
            ->assertDontSee('data-modal-open="domainCreateModal"', false);

        // Supervisor viewing training.venues.index does NOT see Add Venue
        $this->actingAs($this->supervisorUser)
            ->get(route('training.venues.index'))
            ->assertDontSee('data-modal-open="venueCreateModal"', false);

        // Supervisor viewing departments.index does NOT see Add Department
        $this->actingAs($this->supervisorUser)
            ->get(route('departments.index'))
            ->assertDontSee('data-modal-open="addDeptModal"', false);
    }

    public function test_ai_driven_terminology_on_gap_analysis_pages(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('competency.gap.index'))
            ->assertOk()
            ->assertSee('AI-Driven Competency Gap Analysis');
    }

    // --- Helpers ---
    private function createUser(string $role, ?string $employeeId): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.Str::lower(Str::random(8)).'@hospital.test',
            'password' => bcrypt('password'),
            'role' => $role,
            'employee_id' => $employeeId,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function createDepartment(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(4),
            'is_clinical' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createRole(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => $name.' '.Str::random(4),
            'role_slug' => 'role-'.Str::lower(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createEmployee(string $firstName, string $lastName, string $email, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.strtoupper(Str::random(5)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $id;
    }
}
