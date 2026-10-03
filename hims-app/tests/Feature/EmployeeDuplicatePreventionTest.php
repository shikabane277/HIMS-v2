<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private string $deptId;

    private string $roleId;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'concat',
                fn (...$parts) => implode('', array_map(fn ($part) => $part ?? '', $parts))
            );
        }

        $this->deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $this->deptId,
            'name' => 'General Medicine',
            'department_code' => 'MED',
            'is_clinical' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $this->roleId,
            'role_name' => 'Physician',
            'role_slug' => 'physician',
            'department_id' => $this->deptId,
            'is_clinical' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminEmpId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $adminEmpId,
            'employee_code' => 'EMP-ADM01',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.test@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.test@hospital.ph',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'employee_id' => $adminEmpId,
            'email_verified_at' => now(),
        ]);
    }

    public function test_cannot_create_employee_with_duplicate_email(): void
    {
        // First employee
        $response1 = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'first_name' => 'Lorenz',
            'last_name' => 'Fabriga',
            'email' => 'fabriga.lorenxz@gmail.com',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => '2026-10-02',
            'employment_status' => 'active',
        ]);
        $response1->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', ['email' => 'fabriga.lorenxz@gmail.com']);

        // Duplicate submission with same email
        $response2 = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'first_name' => 'Lorenz',
            'last_name' => 'Fabriga',
            'email' => 'fabriga.lorenxz@gmail.com',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => '2026-10-02',
            'employment_status' => 'active',
        ]);

        $response2->assertSessionHasErrors(['email']);
        $this->assertEquals(1, DB::table('employees')->where('email', 'fabriga.lorenxz@gmail.com')->count());
    }

    public function test_cannot_create_employee_with_mixed_case_duplicate_email(): void
    {
        // Create first with lowercase
        $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'first_name' => 'Lorenz',
            'last_name' => 'Fabriga',
            'email' => 'fabriga.lorenxz@gmail.com',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => '2026-10-02',
            'employment_status' => 'active',
        ])->assertRedirect(route('employees.index'));

        // Try submitting with uppercase / whitespace
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'first_name' => 'Lorenz',
            'last_name' => 'Fabriga',
            'email' => ' Fabriga.Lorenxz@gmail.com ',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => '2026-10-02',
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(1, DB::table('employees')->where('email', 'fabriga.lorenxz@gmail.com')->count());
    }

    public function test_cannot_update_employee_to_existing_email(): void
    {
        // Create first employee
        $emp1Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $emp1Id,
            'employee_code' => 'EMP-0010',
            'first_name' => 'First',
            'last_name' => 'Emp',
            'email' => 'first@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create second employee
        $emp2Id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $emp2Id,
            'employee_code' => 'EMP-0011',
            'first_name' => 'Second',
            'last_name' => 'Emp',
            'email' => 'second@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try updating second employee to have first employee's email
        $response = $this->actingAs($this->adminUser)->put(route('employees.update', $emp2Id), [
            'first_name' => 'Second',
            'last_name' => 'Emp',
            'email' => 'first@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_create_admin_command_rejects_email_if_employee_exists(): void
    {
        // Create employee with this email
        DB::table('employees')->insert([
            'employee_id' => (string) Str::uuid(),
            'employee_code' => 'EMP-0099',
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'email' => 'existing.staff@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt running create-admin with that email
        $this->artisan('hims:create-admin', [
            '--email' => 'existing.staff@hospital.ph',
            '--name' => 'Existing Admin',
            '--password' => 'Password123!',
            '--force' => true,
        ])->assertExitCode(1);
    }

    public function test_concurrent_duplicate_insert_catches_query_exception(): void
    {
        $insertedConcurrently = false;
        DB::listen(function ($query) use (&$insertedConcurrently) {
            if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, 'employees') && ! $insertedConcurrently) {
                $insertedConcurrently = true;
                DB::table('employees')->insert([
                    'employee_id' => (string) Str::uuid(),
                    'employee_code' => 'EMP-RACE01',
                    'first_name' => 'Race',
                    'last_name' => 'Winner',
                    'email' => 'race@hospital.ph',
                    'department_id' => $this->deptId,
                    'role_id' => $this->roleId,
                    'hire_date' => now()->toDateString(),
                    'employment_status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'first_name' => 'Race',
            'last_name' => 'Loser',
            'email' => 'race@hospital.ph',
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'hire_date' => '2026-10-02',
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(
            'An employee with this email address already exists.',
            session('errors')->first('email')
        );
    }
}
