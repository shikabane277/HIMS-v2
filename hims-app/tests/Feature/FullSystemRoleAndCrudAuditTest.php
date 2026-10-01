<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FullSystemRoleAndCrudAuditTest extends TestCase
{
    use RefreshDatabase;

    private string $deptId;
    private string $roleId;
    private string $adminEmpId;
    private User $adminUser;
    private string $hrEmpId;
    private User $hrUser;
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

        $this->deptId = $this->createDepartment('Emergency Medicine');
        $this->roleId = $this->createRole('Clinical Specialist');

        $this->adminEmpId = $this->createEmployee('Admin', 'Director', 'admin@system.test');
        $this->adminUser = $this->createUser('admin', $this->adminEmpId);

        $this->hrEmpId = $this->createEmployee('HR', 'Head', 'hr@system.test');
        $this->hrUser = $this->createUser('hr_manager', $this->hrEmpId);

        $this->supervisorEmpId = $this->createEmployee('Super', 'Visor', 'supervisor@system.test');
        $this->supervisorUser = $this->createUser('supervisor', $this->supervisorEmpId);

        $this->staffEmpId = $this->createEmployee('Staff', 'Member', 'staff@system.test', [
            'supervisor_id' => $this->supervisorEmpId,
        ]);
        $this->staffUser = $this->createUser('staff', $this->staffEmpId);
    }

    /**
     * Test all pages and views for Admin role (200 OK across the entire system)
     */
    public function test_admin_has_complete_access_to_all_pages(): void
    {
        $this->actingAs($this->adminUser);

        // Core / Common
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('search', ['q' => 'Staff']))->assertOk();
        $this->get(route('profile.edit'))->assertOk();

        // Employees
        $this->get(route('employees.index'))->assertOk();
        $this->get(route('employees.create'))->assertOk();
        $this->get(route('employees.manager-setup'))->assertOk();
        $this->get(route('employees.show', $this->staffEmpId))->assertOk();
        $this->get(route('employees.edit', $this->staffEmpId))->assertOk();
        $this->get(route('employees.progression', $this->staffEmpId))->assertOk();
        $this->get(route('employees.progression.mine'))->assertOk();

        // Departments
        $this->get(route('departments.index'))->assertOk();

        // Performance
        $this->get(route('performance.index'))->assertOk();
        $this->get(route('performance.reviews.index'))->assertOk();
        $this->get(route('performance.reviews.create'))->assertOk();

        // Competency
        $this->get(route('competency.index'))->assertOk();
        $this->get(route('competency.credentials.index'))->assertOk();
        $this->get(route('competency.role-requirements.index'))->assertOk();
        $this->get(route('competency.gap.index'))->assertOk();
        $this->get(route('competency.gap.department'))->assertOk();

        // Learning & Training
        $this->get(route('learning.index'))->assertOk();
        $this->get(route('learning.pathways.index'))->assertOk();
        $this->get(route('learning.cpd.index'))->assertOk();
        $this->get(route('learning.cycles.mine'))->assertOk();
        $this->get(route('learning.assignments.index'))->assertOk();
        $this->get(route('learning.renewals.index'))->assertOk();
        $this->get(route('learning.renewals.rules'))->assertOk();
        $this->get(route('learning.accreditation'))->assertOk();
        $this->get(route('learning.accounts'))->assertOk();
        $this->get(route('training.index'))->assertOk();
        $this->get(route('training.venues.index'))->assertOk();

        // Recognition
        $this->get(route('recognition.index'))->assertOk();

        // Succession
        $this->get(route('succession.index'))->assertOk();
        $this->get(route('succession.positions.index'))->assertOk();
        $this->get(route('succession.positions.create'))->assertOk();
        $this->get(route('succession.candidates.create'))->assertOk();

        // Users (Admin only)
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk();
        $this->get(route('users.edit', $this->staffUser->id))->assertOk();
    }

    /**
     * Test all pages and views for HR Manager role
     */
    public function test_hr_manager_access_and_boundaries(): void
    {
        $this->actingAs($this->hrUser);

        // HR has full access to all HR/clinical modules
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('employees.index'))->assertOk();
        $this->get(route('departments.index'))->assertOk();
        $this->get(route('performance.index'))->assertOk();
        $this->get(route('competency.index'))->assertOk();
        $this->get(route('competency.role-requirements.index'))->assertOk();
        $this->get(route('learning.index'))->assertOk();
        $this->get(route('learning.renewals.rules'))->assertOk();
        $this->get(route('succession.index'))->assertOk();
        $this->get(route('recognition.index'))->assertOk();

        // HR is forbidden from User Administration (Admin only)
        $this->get(route('users.index'))->assertStatus(403);
        $this->get(route('users.create'))->assertStatus(403);
    }

    /**
     * Test Supervisor role: accessible supervisory pages vs blocked admin endpoints
     */
    public function test_supervisor_access_and_boundaries(): void
    {
        $this->actingAs($this->supervisorUser);

        // Allowed supervisory reads
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('employees.index'))->assertOk();
        $this->get(route('performance.index'))->assertOk();
        $this->get(route('performance.reviews.index'))->assertOk();
        $this->get(route('competency.index'))->assertOk();
        $this->get(route('competency.credentials.index'))->assertOk();
        $this->get(route('competency.gap.index'))->assertOk();
        $this->get(route('learning.index'))->assertOk();
        $this->get(route('learning.assignments.index'))->assertOk();
        $this->get(route('learning.renewals.index'))->assertOk();
        $this->get(route('training.index'))->assertOk();
        $this->get(route('training.venues.index'))->assertOk();
        $this->get(route('recognition.index'))->assertOk();
        $this->get(route('succession.index'))->assertOk();

        // Forbidden admin / HR-only pages for Supervisor
        $this->get(route('departments.index'))->assertStatus(403);
        $this->get(route('employees.create'))->assertStatus(403);
        $this->get(route('employees.manager-setup'))->assertStatus(403);
        $this->get(route('competency.role-requirements.index'))->assertStatus(403);
        $this->get(route('learning.renewals.rules'))->assertStatus(403);
        $this->get(route('learning.accounts'))->assertStatus(403);
        $this->get(route('succession.positions.create'))->assertStatus(403);
        $this->get(route('succession.candidates.create'))->assertStatus(403);
        $this->get(route('users.index'))->assertStatus(403);

        // View verification: ensure NO 403 buttons appear in Supervisor views
        $this->get(route('competency.index'))
            ->assertDontSee('Role Requirements')
            ->assertDontSee('data-modal-open="domainCreateModal"', false)
            ->assertSee('data-modal-open="assessmentCreateModal"', false)
            ->assertSee('data-modal-open="credentialCreateModal"', false);

        $this->get(route('training.venues.index'))
            ->assertDontSee('data-modal-open="venueCreateModal"', false)
            ->assertDontSee('data-venue-edit', false);

        $this->get(route('learning.index'))
            ->assertDontSee('data-modal-open="courseCreateModal"', false)
            ->assertDontSee('data-modal-open="pathwayCreateModal"', false);

        $this->get(route('succession.index'))
            ->assertDontSee(route('succession.positions.create'))
            ->assertDontSee('data-modal-open="nominationModal"', false);
    }

    /**
     * Test Staff role: self-service access and strict RBAC isolation
     */
    public function test_staff_access_and_boundaries(): void
    {
        $this->actingAs($this->staffUser);

        // Staff self-service reads
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('employees.progression.mine'))->assertOk();
        $this->get(route('performance.index'))->assertOk();
        $this->get(route('performance.reviews.index'))->assertOk();
        $this->get(route('competency.index'))->assertOk();
        $this->get(route('competency.credentials.index'))->assertOk();
        $this->get(route('learning.index'))->assertOk();
        $this->get(route('learning.pathways.index'))->assertOk();
        $this->get(route('learning.cpd.index'))->assertOk();
        $this->get(route('learning.cycles.mine'))->assertOk();
        $this->get(route('training.index'))->assertOk();
        $this->get(route('training.venues.index'))->assertOk();
        $this->get(route('recognition.index'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();

        // Forbidden managerial / institutional pages for Staff
        $this->get(route('employees.index'))->assertStatus(403);
        $this->get(route('departments.index'))->assertStatus(403);
        $this->get(route('performance.reviews.create'))->assertStatus(403);
        $this->get(route('competency.role-requirements.index'))->assertStatus(403);
        $this->get(route('competency.gap.index'))->assertStatus(403);
        $this->get(route('learning.assignments.index'))->assertStatus(403);
        $this->get(route('learning.renewals.index'))->assertStatus(403);
        $this->get(route('learning.accreditation'))->assertStatus(403);
        $this->get(route('succession.index'))->assertStatus(403);
        $this->get(route('users.index'))->assertStatus(403);

        // View verification: ensure staff views don't offer managerial modals
        $this->get(route('learning.index'))
            ->assertDontSee('data-modal-open="courseCreateModal"', false)
            ->assertDontSee('data-modal-open="pathwayCreateModal"', false);

        $this->get(route('competency.index'))
            ->assertDontSee('data-modal-open="assessmentCreateModal"', false)
            ->assertDontSee('data-modal-open="credentialCreateModal"', false)
            ->assertDontSee('data-modal-open="domainCreateModal"', false)
            ->assertDontSee('Role Requirements');

        $this->get(route('competency.credentials.index'))
            ->assertDontSee('data-modal-open="credentialCreateModal"', false);

        $this->get(route('training.index'))
            ->assertDontSee('data-modal-open="sessionCreateModal"', false);
    }

    /**
     * Test full lifecycle CRUD and actions across subsystems
     */
    public function test_full_lifecycle_crud_actions(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Employee CRUD
        $empCode = 'EMP-'.strtoupper(Str::random(5));
        $this->post(route('employees.store'), [
            'first_name' => 'Roberto',
            'last_name' => 'Luna',
            'email' => 'roberto.luna@hospital.test',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'employee_code' => $empCode,
        ])->assertRedirect();

        $emp = DB::table('employees')->where('email', 'roberto.luna@hospital.test')->first();
        $this->assertNotNull($emp);

        $this->put(route('employees.update', $emp->employee_id), [
            'first_name' => 'Roberto Carlos',
            'last_name' => 'Luna',
            'email' => 'roberto.luna@hospital.test',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'employee_id' => $emp->employee_id,
            'first_name' => 'Roberto Carlos',
        ]);

        // 2. Department CRUD
        $this->post(route('departments.store'), [
            'name' => 'Neurology Intensive Care',
            'department_code' => 'NICU-'.Str::random(3),
            'is_clinical' => 1,
        ])->assertRedirect();

        $dept = DB::table('departments')->where('name', 'Neurology Intensive Care')->first();
        $this->assertNotNull($dept);

        $this->put(route('departments.update', $dept->department_id), [
            'name' => 'Neurology Care Center',
            'department_code' => 'NCC',
            'is_clinical' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'department_id' => $dept->department_id,
            'name' => 'Neurology Care Center',
        ]);

        $this->delete(route('departments.destroy', $dept->department_id))->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', ['department_id' => $dept->department_id]);

        // 3. Learning Course & Pathway CRUD
        $this->post(route('learning.courses.store'), [
            'title' => 'Advanced Trauma Life Support',
            'course_code' => 'ATLS-'.Str::random(3),
            'category' => 'clinical',
            'cpd_hours' => 12.5,
            'difficulty_level' => 'advanced',
            'passing_score' => 80,
            'estimated_duration' => 720,
            'is_mandatory' => 1,
            'description' => 'Trauma management protocols',
        ])->assertSessionHas('success');

        $course = DB::table('courses')->where('title', 'Advanced Trauma Life Support')->first();
        $this->assertNotNull($course);

        $this->put(route('learning.courses.update', $course->course_id), [
            'title' => 'Advanced Trauma Care & Life Support',
            'category' => 'clinical',
            'cpd_hours' => 14,
            'difficulty_level' => 'advanced',
            'passing_score' => 85,
            'estimated_duration' => 720,
            'is_mandatory' => 1,
            'description' => 'Updated trauma protocols',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('courses', [
            'course_id' => $course->course_id,
            'title' => 'Advanced Trauma Care & Life Support',
        ]);

        $this->post(route('learning.pathways.store'), [
            'pathway_name' => 'Emergency Physician Pathway',
            'description' => 'Comprehensive pathway',
            'is_mandatory' => 1,
        ])->assertSessionHas('success');

        $pathway = DB::table('learning_pathways')->where('pathway_name', 'Emergency Physician Pathway')->first();
        $this->assertNotNull($pathway);

        // 4. Training Venue & Session CRUD
        $this->post(route('training.venues.store'), [
            'venue_name' => 'Simulation Center West',
            'building' => 'Pavilion 2',
            'capacity' => 45,
            'is_active' => 1,
        ])->assertRedirect();

        $venue = DB::table('training_venues')->where('venue_name', 'Simulation Center West')->first();
        $this->assertNotNull($venue);

        $this->put(route('training.venues.update', $venue->venue_id), [
            'venue_name' => 'Simulation Center East',
            'building' => 'Pavilion 2',
            'capacity' => 50,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('training_venues', [
            'venue_id' => $venue->venue_id,
            'venue_name' => 'Simulation Center East',
        ]);

        $this->post(route('training.sessions.store'), [
            'title' => 'Code Blue Simulation Drills',
            'category' => 'clinical',
            'venue_id' => $venue->venue_id,
            'instructor_id' => $this->adminEmpId,
            'session_date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '11:00',
            'capacity' => 20,
            'cpd_hours' => 3,
        ])->assertSessionHas('success');

        $session = DB::table('training_sessions')->where('title', 'Code Blue Simulation Drills')->first();
        $this->assertNotNull($session);

        // Register staff to session
        $this->actingAs($this->staffUser)
            ->post(route('training.register', $session->session_id))
            ->assertRedirect();

        $reg = DB::table('training_registrations')->where('session_id', $session->session_id)->first();
        $this->assertNotNull($reg);

        // Supervisor marks attendance
        $this->actingAs($this->supervisorUser)
            ->post(route('training.sessions.checkin', $session->session_id), [
                'registrations' => [$reg->registration_id => 'attended'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('training_registrations', [
            'registration_id' => $reg->registration_id,
            'status' => 'attended',
        ]);

        // 5. Social Recognition Flow
        $this->actingAs($this->adminUser)
            ->post(route('recognition.badges.store'), [
                'badge_name' => 'Excellence in Compassion',
                'description' => 'Exemplary patient care and bedside manner',
                'badge_icon' => 'bi-heart-pulse-fill',
                'badge_color' => '#dc2626',
                'points_value' => 10,
            ])->assertSessionHas('success');

        $badge = DB::table('recognition_badges')->where('badge_name', 'Excellence in Compassion')->first();
        $this->assertNotNull($badge);

        $this->actingAs($this->supervisorUser)
            ->post(route('recognition.posts.store'), [
                'recipient_id' => $this->staffEmpId,
                'badge_id' => $badge->badge_id,
                'message' => 'Outstanding teamwork during overnight triage duty.',
                'post_type' => 'supervisor',
                'is_public' => 1,
            ])->assertSessionHas('success');

        $post = DB::table('recognition_posts')->where('recipient_id', $this->staffEmpId)->first();
        $this->assertNotNull($post);

        // React to post
        $this->actingAs($this->staffUser)
            ->post(route('recognition.react', $post->post_id), [
                'reaction_type' => 'clap',
            ])->assertRedirect();

        $this->assertDatabaseHas('recognition_reactions', [
            'post_id' => $post->post_id,
            'employee_id' => $this->staffEmpId,
        ]);

        // Comment on post
        $this->actingAs($this->staffUser)
            ->post(route('recognition.comments.store', $post->post_id), [
                'comment_text' => 'Thank you for the guidance and support!',
            ])->assertSessionHas('success');

        $this->assertDatabaseHas('recognition_comments', [
            'post_id' => $post->post_id,
            'author_id' => $this->staffEmpId,
        ]);

        // 6. Succession Planning Flow
        $this->actingAs($this->adminUser)
            ->post(route('succession.positions.store'), [
                'position_title' => 'Chief Clinical Officer',
                'department_id' => $this->deptId,
                'is_critical' => 1,
                'vacancy_risk' => 'high',
                'impact_description' => 'Executive clinical governance continuity',
            ])->assertRedirect();

        $pos = DB::table('critical_positions')->where('position_title', 'Chief Clinical Officer')->first();
        $this->assertNotNull($pos);

        $this->post(route('succession.candidates.store'), [
            'position_id' => $pos->position_id,
            'employee_id' => $this->supervisorEmpId,
            'readiness_level' => 'ready_now',
            'performance_score' => 4,
            'potential_score' => 5,
            'status' => 'approved',
        ])->assertRedirect();

        $cand = DB::table('succession_candidates')->where('position_id', $pos->position_id)->first();
        $this->assertNotNull($cand);

        $this->from(route('succession.candidates.show', $cand->candidate_id))
            ->post(route('succession.milestones.store', $cand->candidate_id), [
                'milestone_title' => 'Complete Executive Healthcare Leadership Program',
                'target_date' => now()->addMonths(6)->toDateString(),
            ])->assertRedirect();

        $this->assertDatabaseHas('leadership_development_paths', [
            'candidate_id' => $cand->candidate_id,
            'milestone_title' => 'Complete Executive Healthcare Leadership Program',
        ]);

        // 7. User Management Flow (Admin)
        $newEmpId = $this->createEmployee('Quality', 'Lead', 'quality.lead.emp@hospital.test');

        $this->actingAs($this->adminUser)
            ->post(route('users.store'), [
                'name' => 'Quality Lead',
                'email' => 'quality.lead@system.test',
                'role' => 'supervisor',
                'employee_id' => $newEmpId,
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ])->assertRedirect(route('users.index'));

        $newUser = User::where('email', 'quality.lead@system.test')->first();
        $this->assertNotNull($newUser);

        // Lock and unlock user
        DB::table('users')->where('id', $newUser->id)->update([
            'locked_until' => now()->addMinutes(15),
            'failed_login_attempts' => 5,
        ]);

        $this->post(route('users.unlock', $newUser->id))->assertRedirect();

        $refreshed = User::find($newUser->id);
        $this->assertNull($refreshed->locked_until);
        $this->assertEquals(0, $refreshed->failed_login_attempts);
    }

    // --- Helpers ---
    private function createUser(string $role, ?string $employeeId): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' System User',
            'email' => $role.'-'.Str::lower(Str::random(8)).'@system.test',
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
