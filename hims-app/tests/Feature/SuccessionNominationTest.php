<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuccessionNominationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private string $deptId;
    private string $emp1Id;
    private string $emp2Id;
    private string $positionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $this->deptId,
            'name' => 'Nursing Services',
            'department_code' => 'NS',
            'is_clinical' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Nurse',
            'role_slug' => 'nurse',
            'department_id' => $this->deptId,
            'is_clinical' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->emp1Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $this->emp1Id,
            'employee_code' => 'EMP-01',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'm.santos@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $roleId,
            'position_title' => 'Head Nurse',
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->emp2Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $this->emp2Id,
            'employee_code' => 'EMP-02',
            'first_name' => 'Jose',
            'last_name' => 'Reyes',
            'email' => 'j.reyes@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $roleId,
            'position_title' => 'Staff Nurse II',
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'employee_id' => $this->emp1Id,
        ]);

        $this->positionId = (string) Str::uuid();
        DB::table('critical_positions')->insert([
            'position_id' => $this->positionId,
            'position_title' => 'Director of Nursing Services',
            'department_id' => $this->deptId,
            'current_holder_id' => $this->emp1Id,
            'is_critical' => true,
            'vacancy_risk' => 'critical',
            'impact_description' => 'Clinical nursing governance',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_succession_index_displays_nominate_modal_and_positions(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('succession.index'));

        $response->assertStatus(200);
        $response->assertSee('Nominate Successor');
        $response->assertSee('id="nominationModal"', false);
        $response->assertSee('Director of Nursing Services');
        $response->assertDontSee('<div class="hims-modal-backdrop" id="nominationModal" style="display:none">', false);
    }

    public function test_nomination_can_be_submitted_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('succession.candidates.store'), [
            'employee_id' => $this->emp2Id,
            'position_id' => $this->positionId,
            'performance_score' => 4,
            'potential_score' => 4,
            'readiness_level' => 'ready_now',
            'nomination_notes' => 'Strong clinical leader with exceptional crisis triage capability.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('succession_candidates', [
            'employee_id' => $this->emp2Id,
            'position_id' => $this->positionId,
            'performance_score' => 4,
            'potential_score' => 4,
            'readiness_level' => 'ready_now',
            'status' => 'proposed',
        ]);
    }
}
