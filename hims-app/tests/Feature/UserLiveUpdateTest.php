<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

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

        $adminEmpId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => Str::uuid(),
            'name' => 'Administration',
            'department_code' => 'ADM',
            'is_clinical' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Administrator',
            'role_slug' => 'admin',
            'is_clinical' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employees')->insert([
            'employee_id' => $adminEmpId,
            'employee_code' => 'EMP-ADM01',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.live@hospital.ph',
            'department_id' => DB::table('departments')->value('department_id'),
            'role_id' => $roleId,
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.live@hospital.ph',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'employee_id' => $adminEmpId,
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_index_returns_json_for_live_sync(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('users.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'total',
                'stats' => ['total', 'admins', 'managers', 'locked'],
                'users',
                'html',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Admin User', $response->json('html'));
    }

    public function test_user_store_returns_json_for_ajax_creation(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('users.store'), [
            'name' => 'Lorenz Fabriga',
            'email' => 'fabriga.lorenz.live@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'supervisor',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Lorenz Fabriga',
                    'email' => 'fabriga.lorenz.live@gmail.com',
                    'role' => 'supervisor',
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'fabriga.lorenz.live@gmail.com']);
    }

    public function test_newly_created_user_appears_first_by_default(): void
    {
        // Create older user
        User::create([
            'name' => 'Aaron Older',
            'email' => 'aaron@hospital.ph',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'created_at' => now()->subDay(),
        ]);

        // Create newer user
        User::create([
            'name' => 'Zack Newer',
            'email' => 'zack@hospital.ph',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('users.index'));

        $users = $response->json('users');
        $this->assertEquals('Zack Newer', $users[0]['name']);
    }

    public function test_user_store_standard_request_still_redirects(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'name' => 'Standard Form User',
            'email' => 'standard@hospital.ph',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'staff',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'standard@hospital.ph']);
    }
}
