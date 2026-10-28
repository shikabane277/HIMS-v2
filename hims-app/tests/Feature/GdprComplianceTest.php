<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GdprComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_and_no_store_cache_control_are_applied_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_user_can_export_their_personal_data_package_under_gdpr_articles_15_and_20(): void
    {
        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'Cardiology',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Cardiac Nurse',
            'role_slug' => 'cardiac-nurse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP-GDPR1',
            'first_name' => 'Clara',
            'last_name' => 'Oswald',
            'email' => 'clara@hospital.test',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => '2025-01-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'name' => 'Clara Oswald',
            'email' => 'clara@hospital.test',
            'employee_id' => $empId,
        ]);

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertOk();
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));

        $json = json_decode($response->streamedContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('clara@hospital.test', $json['account']['email']);
        $this->assertSame('EMP-GDPR1', $json['employee_profile']['employee_code']);
        $this->assertStringContainsString('PROHIBITED', $json['gdpr_compliance_metadata']['ai_employee_data_sharing']);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'gdpr_data_export',
            'resource_type' => 'users',
            'resource_id' => (string) $user->id,
        ]);
    }

    public function test_user_can_acknowledge_gdpr_privacy_notice_consent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('profile.gdpr-consent'));

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'gdpr_consent_user_' . $user->id,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'gdpr_consent_acknowledged',
            'resource_type' => 'users',
            'resource_id' => (string) $user->id,
        ]);
    }

    public function test_account_deletion_purges_ai_chat_history_and_logs_gdpr_erasure(): void
    {
        $user = User::factory()->create();
        $sessionId = (string) Str::uuid();

        DB::table('ai_chat_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'title' => 'My chat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ai_chat_messages')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'message' => 'Hello',
            'seq' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->delete('/profile', [
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('ai_chat_sessions', ['id' => $sessionId]);
        $this->assertDatabaseMissing('ai_chat_messages', ['session_id' => $sessionId]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'gdpr_account_erasure',
            'resource_type' => 'users',
            'resource_id' => (string) $user->id,
        ]);
    }

    public function test_admin_can_anonymize_terminated_employee_pii_under_gdpr_article_17(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'ER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'ER Nurse',
            'role_slug' => 'er-nurse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP-TERM1',
            'first_name' => 'Former',
            'last_name' => 'Worker',
            'email' => 'former.worker@hospital.test',
            'phone' => '09171234567',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'terminated',
            'hire_date' => '2023-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('employees.anonymize', $empId));
        $response->assertRedirect(route('employees.show', $empId));

        $updated = DB::table('employees')->where('employee_id', $empId)->first();
        $this->assertSame('Redacted', $updated->first_name);
        $this->assertNull($updated->phone);
        $this->assertStringEndsWith('@anonymized.invalid', $updated->email);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'gdpr_employee_anonymized',
            'resource_type' => 'employees',
            'resource_id' => $empId,
        ]);
    }

    public function test_active_employee_cannot_be_anonymized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId,
            'name' => 'ICU',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'ICU Nurse',
            'role_slug' => 'icu-nurse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId,
            'employee_code' => 'EMP-ACT1',
            'first_name' => 'Active',
            'last_name' => 'Nurse',
            'email' => 'active.nurse@hospital.test',
            'department_id' => $deptId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => '2024-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('employees.anonymize', $empId))
            ->assertSessionHas('error');

        $this->assertSame(
            'Active',
            DB::table('employees')->where('employee_id', $empId)->value('first_name')
        );
    }

    public function test_automated_data_retention_command_prunes_expired_records(): void
    {
        $user = User::factory()->create();
        $oldSessionId = (string) Str::uuid();
        $recentSessionId = (string) Str::uuid();

        DB::table('ai_chat_sessions')->insert([
            [
                'id' => $oldSessionId,
                'user_id' => $user->id,
                'title' => 'Old Session',
                'created_at' => now()->subDays(120),
                'updated_at' => now()->subDays(120),
            ],
            [
                'id' => $recentSessionId,
                'user_id' => $user->id,
                'title' => 'Recent Session',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
        ]);

        Artisan::call('hims:prune-expired-data', ['--chat-days' => 90]);

        $this->assertDatabaseMissing('ai_chat_sessions', ['id' => $oldSessionId]);
        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $recentSessionId]);
    }

    public function test_record_access_unlock_and_failure_are_logged_to_audit_trail(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'record_access_password_hash'],
            ['value' => Hash::make('Hospital@2026!'), 'updated_at' => now()]
        );

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->postJson(route('record-access.unlock'), [
            'password' => 'wrong-pass',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'record_access_failed',
            'user_id' => (string) $user->id,
        ]);

        $this->actingAs($user)->postJson(route('record-access.unlock'), [
            'password' => 'Hospital@2026!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'record_access_unlocked',
            'user_id' => (string) $user->id,
        ]);
    }
}

