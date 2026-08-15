<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuccessionConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_have_no_access_to_succession_records(): void
    {
        $department = $this->department('Nursing');
        $employee = $this->employee($department, 'staff@example.org');
        $staff = $this->user('staff', $employee);

        $this->actingAs($staff)->get(route('succession.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('succession.milestones.store', 'missing'), [
            'milestone_title' => 'Should be blocked',
        ])->assertForbidden();
    }

    public function test_supervisor_can_manage_milestones_for_a_direct_report_but_not_an_unrelated_candidate(): void
    {
        $department = $this->department('Emergency');
        $supervisorId = $this->employee($department, 'supervisor@example.org');
        $reportId = $this->employee($department, 'report@example.org', ['supervisor_id' => $supervisorId]);
        $otherId = $this->employee($department, 'other@example.org');
        $supervisor = $this->user('supervisor', $supervisorId);
        $positionId = $this->position($department);
        $reportCandidate = $this->candidate($positionId, $reportId, $supervisorId);
        $otherCandidate = $this->candidate($positionId, $otherId, $supervisorId);

        $this->actingAs($supervisor)->post(route('succession.milestones.store', $reportCandidate), [
            'milestone_title' => 'Complete charge nurse rotation',
            'milestone_type' => 'rotation',
        ])->assertRedirect();

        $this->assertDatabaseHas('leadership_development_paths', [
            'candidate_id' => $reportCandidate,
            'milestone_title' => 'Complete charge nurse rotation',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'succession_milestone_created',
            'resource_type' => 'leadership_development_paths',
        ]);

        $this->actingAs($supervisor)->post(route('succession.milestones.store', $otherCandidate), [
            'milestone_title' => 'Should be blocked',
        ])->assertForbidden();
    }

    public function test_hr_changes_to_candidate_ratings_are_audited(): void
    {
        $department = $this->department('Human Resources');
        $hrId = $this->employee($department, 'hr@example.org');
        $candidateEmployee = $this->employee($department, 'candidate@example.org');
        $hr = $this->user('hr_manager', $hrId);
        $positionId = $this->position($department);
        $candidateId = $this->candidate($positionId, $candidateEmployee, $hrId);

        $this->actingAs($hr)->put(route('succession.candidates.update', $candidateId), [
            'performance_score' => 5,
            'potential_score' => 4,
            'readiness_level' => 'ready_now',
        ])->assertRedirect(route('succession.candidates.show', $candidateId));

        $this->assertDatabaseHas('succession_candidates', [
            'candidate_id' => $candidateId,
            'performance_score' => 5,
            'potential_score' => 4,
            'readiness_level' => 'ready_now',
            'nine_box_label' => 'star',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'succession_candidate_updated',
            'resource_type' => 'succession_candidates',
            'resource_id' => $candidateId,
            'employee_id' => $hrId,
        ]);
    }

    public function test_hr_can_record_a_quarterly_position_review_and_audit_it(): void
    {
        $department = $this->department('Quality');
        $hrId = $this->employee($department, 'hr@example.org');
        $hr = $this->user('hr_manager', $hrId);
        $positionId = $this->position($department);

        $this->actingAs($hr)->post(route('succession.positions.review', $positionId), [
            'quarterly_review_notes' => 'Risk remains high; maintain two named successors and complete the mentoring rotation.',
        ])->assertRedirect();

        $this->assertDatabaseHas('critical_positions', [
            'position_id' => $positionId,
            'last_reviewed_by' => $hrId,
            'quarterly_review_notes' => 'Risk remains high; maintain two named successors and complete the mentoring rotation.',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'succession_position_review',
            'resource_type' => 'critical_positions',
            'resource_id' => $positionId,
            'employee_id' => $hrId,
        ]);
    }

    public function test_supervisor_cannot_use_hr_only_candidate_rating_routes(): void
    {
        $department = $this->department('Surgery');
        $supervisorId = $this->employee($department, 'supervisor@example.org');
        $reportId = $this->employee($department, 'report@example.org', ['supervisor_id' => $supervisorId]);
        $supervisor = $this->user('supervisor', $supervisorId);
        $candidateId = $this->candidate($this->position($department), $reportId, $supervisorId);

        $this->actingAs($supervisor)->put(route('succession.candidates.update', $candidateId), [
            'performance_score' => 4,
            'potential_score' => 4,
            'readiness_level' => 'ready_now',
        ])->assertForbidden();
    }

    public function test_supervisor_views_redact_confidential_fields_and_exclude_unrelated_candidates(): void
    {
        $this->registerSqliteConcat();
        $department = $this->department('Radiology');
        $supervisorId = $this->employee($department, 'supervisor@example.org');
        $reportId = $this->employee($department, 'report@example.org', ['supervisor_id' => $supervisorId]);
        $otherId = $this->employee($department, 'other@example.org');
        $supervisor = $this->user('supervisor', $supervisorId);
        $positionId = $this->position($department);
        $reportCandidate = $this->candidate($positionId, $reportId, $supervisorId);
        $this->candidate($positionId, $otherId, $supervisorId);

        $this->actingAs($supervisor)->get(route('succession.candidates.show', $reportCandidate))
            ->assertOk()
            ->assertViewHas('canSeeConfidential', false)
            ->assertViewHas('candidate', fn ($candidate) => $candidate->performance_score === null
                && $candidate->potential_score === null
                && $candidate->readiness_level === null
                && $candidate->nine_box_label === null
                // The fixture seeds a real rationale, so this assertion fails if
                // the field is ever dropped from the redaction list. Asserting
                // null on a column nothing populated proves nothing.
                && $candidate->nomination_notes === null)
            ->assertDontSee('Confidential HR rationale');

        $this->actingAs($supervisor)->get(route('succession.positions.show', $positionId))
            ->assertOk()
            ->assertViewHas('position', fn ($position) => $position->vacancy_risk === null)
            ->assertViewHas('candidates', fn ($candidates) => $candidates->count() === 1
                && $candidates->first()->employee_id === $reportId
                && $candidates->first()->performance_score === null);
    }

    /**
     * The nomination modal's Notes field posted to nothing: there was no column,
     * `storeCandidate()` neither validated nor inserted it, and the user got a
     * success message while their rationale was discarded. This is the
     * regression test for that, which is why it asserts the stored value rather
     * than just a 302.
     */
    public function test_a_nomination_rationale_is_stored_rather_than_silently_discarded(): void
    {
        $department = $this->department('Pharmacy');
        $hrEmployeeId = $this->employee($department, 'hr@example.org');
        $candidateEmployeeId = $this->employee($department, 'nominee@example.org');
        $hr = $this->user('hr_manager', $hrEmployeeId);
        $positionId = $this->position($department);

        $this->actingAs($hr)->post(route('succession.candidates.store'), [
            'employee_id' => $candidateEmployeeId,
            'position_id' => $positionId,
            'performance_score' => 4,
            'potential_score' => 5,
            'readiness_level' => 'ready_now',
            'nomination_notes' => 'Ran the ICU rota through Q3 and covered two resignations.',
        ])->assertRedirect(route('succession.index'));

        $this->assertSame(
            'Ran the ICU rota through Q3 and covered two resignations.',
            DB::table('succession_candidates')
                ->where('employee_id', $candidateEmployeeId)
                ->value('nomination_notes')
        );

        // HR wrote it, so HR can read it back.
        $this->registerSqliteConcat();
        $candidateId = DB::table('succession_candidates')
            ->where('employee_id', $candidateEmployeeId)->value('candidate_id');

        $this->actingAs($hr)->get(route('succession.candidates.show', $candidateId))
            ->assertOk()
            ->assertSee('Ran the ICU rota through Q3');
    }

    public function test_a_nomination_without_notes_is_still_accepted(): void
    {
        $department = $this->department('Records');
        $hrEmployeeId = $this->employee($department, 'hr2@example.org');
        $candidateEmployeeId = $this->employee($department, 'nominee2@example.org');
        $hr = $this->user('hr_manager', $hrEmployeeId);
        $positionId = $this->position($department);

        $this->actingAs($hr)->post(route('succession.candidates.store'), [
            'employee_id' => $candidateEmployeeId,
            'position_id' => $positionId,
        ])->assertRedirect(route('succession.index'));

        $this->assertNull(
            DB::table('succession_candidates')
                ->where('employee_id', $candidateEmployeeId)
                ->value('nomination_notes')
        );
    }

    private function registerSqliteConcat(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'concat',
                fn (...$parts) => implode('', array_map(fn ($part) => $part ?? '', $parts))
            );
        }
    }

    private function department(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(4),
            'department_code' => Str::upper(Str::random(6)),
            'is_clinical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(string $departmentId, string $email, array $overrides = []): string
    {
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Role '.Str::random(6),
            'role_slug' => 'role-'.Str::lower(Str::random(8)),
            'department_id' => $departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(7)),
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $roleId,
            'hire_date' => now()->subYear()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function user(string $role, string $employeeId): User
    {
        return User::factory()->create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@example.org',
            'role' => $role,
            'employee_id' => $employeeId,
        ]);
    }

    private function position(string $departmentId): string
    {
        $id = (string) Str::uuid();
        DB::table('critical_positions')->insert([
            'position_id' => $id,
            'position_title' => 'Critical Role '.Str::random(4),
            'department_id' => $departmentId,
            'vacancy_risk' => 'high',
            'is_critical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function candidate(string $positionId, string $employeeId, string $nominatedBy): string
    {
        $id = (string) Str::uuid();
        DB::table('succession_candidates')->insert([
            'candidate_id' => $id,
            'position_id' => $positionId,
            'employee_id' => $employeeId,
            'performance_score' => 3,
            'potential_score' => 3,
            'nine_box_label' => 'core',
            'readiness_level' => '1_2_years',
            'status' => 'proposed',
            'nomination_notes' => 'Confidential HR rationale for this nomination.',
            'nominated_by' => $nominatedBy,
            'nominated_at' => now(),
        ]);

        return $id;
    }
}
