<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature test for the employee progression view.
 *
 * Uses MySQL-only SQL (DATE_ADD, CURDATE(), SUBSTRING_INDEX, GROUP_CONCAT) so
 * this test must target the MySQL connection, not sqlite :memory:.
 *
 * @group mysql
 */
class EmployeeProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip unless the connection is actually MySQL — the query uses
        // DATE_ADD, CURDATE(), SUBSTRING_INDEX, and GROUP_CONCAT.
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This test requires MySQL for the reassessments query.');
        }
    }

    public function test_staff_can_access_own_progression(): void
    {
        $user = DB::table('users')->insertGetId([
            'name' => 'Jane Staff',
            'email' => 'staff@example.org',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'Nursing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // roles.role_slug is NOT NULL and unique — omitting it fails the insert.
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Staff Nurse',
            'role_slug' => 'staff-nurse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP001',
            'first_name' => 'Jane',
            'last_name' => 'Staff',
            'email' => 'staff@example.org',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $user)->update(['employee_id' => $empId]);

        $response = $this->actingAs(User::find($user))
            ->get(route('employees.progression', $empId));

        $response->assertStatus(200);
        $response->assertSee('Jane Staff');
        $response->assertSee('Development Progression');
    }

    public function test_staff_cannot_access_other_employee_progression(): void
    {
        $user = DB::table('users')->insertGetId([
            'name' => 'Jane Staff',
            'email' => 'staff@example.org',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'Nursing',
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

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP001',
            'first_name' => 'Jane',
            'last_name' => 'Staff',
            'email' => 'staff@example.org',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $user)->update(['employee_id' => $empId]);

        $otherEmpId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $otherEmpId,
            'employee_code' => 'EMP002',
            'first_name' => 'John',
            'last_name' => 'Other',
            'email' => 'other@example.org',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(1)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::find($user))
            ->get(route('employees.progression', $otherEmpId));

        $response->assertStatus(403);
    }

    public function test_my_progression_route_redirects_when_no_employee_link(): void
    {
        $user = DB::table('users')->insertGetId([
            'name' => 'Unlinked User',
            'email' => 'unlinked@example.org',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
            'employee_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::find($user))
            ->get(route('employees.progression.mine'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_progression_view_shows_competency_gaps(): void
    {
        $user = $this->createAuthenticatedUser();
        $empId = $user->employee_id;

        // competencies.category_id is NOT NULL with an FK to competency_categories,
        // which in turn requires a competency_domains row — so the whole chain
        // has to exist before a competency can be inserted.
        $domainId = (string) Str::uuid();
        DB::table('competency_domains')->insert([
            'domain_id' => $domainId,
            'domain_name' => 'Clinical Care',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (string) Str::uuid();
        DB::table('competency_categories')->insert([
            'category_id' => $categoryId,
            'domain_id' => $domainId,
            'category_name' => 'Nursing Fundamentals',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compId = (string) Str::uuid();
        DB::table('competencies')->insert([
            'competency_id' => $compId,
            'competency_name' => 'Patient Assessment',
            'competency_code' => 'CA-001',
            'category_id' => $categoryId,
            'required_proficiency' => 4,
            'is_mandatory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // `gap` is deliberately omitted: trg_compute_gap_insert sets it to
        // current_proficiency - required_proficiency (2 - 4 = -2). Letting the
        // trigger fill it means the -2 assertion below exercises the real
        // computation instead of echoing a value the test hardcoded.
        DB::table('competency_assessments')->insert([
            'assessment_id' => (string) Str::uuid(),
            'competency_id' => $compId,
            'employee_id' => $empId,
            'assessed_by' => $empId,
            'current_proficiency' => 2,
            'assessed_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::find($user->user_id))
            ->get(route('employees.progression', $empId));

        $response->assertStatus(200);
        $response->assertSee('Patient Assessment');
        $response->assertSee('CA-001');
        $response->assertSee('-2');
    }

    /**
     * Regression: the credentials table on this page rendered a column that does
     * not exist (`credential_name`), so the page 500'd for any employee who had
     * so much as one credential on file — and stayed green in every test,
     * because no test had ever put a credential row in front of the loop. The
     * column is `credential_type`, as every other credential view already knew.
     */
    public function test_progression_view_renders_credentials(): void
    {
        $user = $this->createAuthenticatedUser();

        DB::table('employee_credentials')->insert([
            'credential_id' => (string) Str::uuid(),
            'employee_id' => $user->employee_id,
            'credential_type' => 'PRC License',
            'credential_number' => 'RN-0123456',
            'issuing_body' => 'Professional Regulation Commission',
            'issue_date' => now()->subYears(2)->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::find($user->user_id))
            ->get(route('employees.progression', $user->employee_id));

        $response->assertStatus(200);
        $response->assertSee('PRC License');
        $response->assertSee('Professional Regulation Commission');
    }

    private function createAuthenticatedUser()
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Employee',
            'email' => 'employee@example.org',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'Nursing',
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

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP-TEST',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'employee@example.org',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $userId)->update(['employee_id' => $empId]);

        return (object) ['user_id' => $userId, 'employee_id' => $empId];
    }
}
