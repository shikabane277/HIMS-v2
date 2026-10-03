<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GapAnalysisSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private string $emp1Id;

    private string $emp2Id;

    protected function setUp(): void
    {
        parent::setUp();

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'Nursing Services',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Staff Nurse',
            'role_slug' => 'staff-nurse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->emp1Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $this->emp1Id,
            'employee_code' => 'EMP-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@hospital.test',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'position_title' => 'Registered Nurse',
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->emp2Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $this->emp2Id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@hospital.test',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'position_title' => 'ICU Nurse',
            'employment_status' => 'active',
            'hire_date' => now()->subYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'admin@hospital.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'employee_id' => $this->emp1Id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_gap_analysis_index_renders_employee_selector(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('competency.gap.index'));

        $response->assertOk();
        $response->assertSee('Select Specific Employee for AI Gap Analysis');
        $response->assertSee('employeeSearchInput');
        $response->assertSee('Type name, position, or department to search...');
        $response->assertSee('Maria Santos');
        $response->assertSee('Juan Dela Cruz');
        $response->assertSee('Run AI Analysis');
        $response->assertSee('individualTableFilter');
    }

    public function test_selecting_specific_employee_redirects_to_employee_analysis(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('competency.gap.index', [
            'employee_id' => $this->emp2Id,
        ]));

        $response->assertRedirect(route('competency.gap.employee', $this->emp2Id));
    }

    public function test_gap_analysis_renders_searchable_department_filter(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('competency.gap.index'));

        $response->assertOk();
        $response->assertSee('Select / Filter by Department');
        $response->assertSee('departmentSearchInput');
        $response->assertSee('Type department name to search...');
        $response->assertSee('Nursing Services');
    }
}
