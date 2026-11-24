<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Models\User;
use App\Services\HrIntegrationService;
use App\Support\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the 2026-11-24 hardening pass: cron authorisation,
 * idle-timeout heartbeat, record-access password, tamper-evident audit chain,
 * request-level audit coverage, credential verification and the removal of
 * invented report figures.
 */
class HospitalHardeningTest extends TestCase
{
    use RefreshDatabase;

    // ── Cron endpoint ────────────────────────────────────────

    public function test_cron_rejects_spoofed_vercel_header_without_secret(): void
    {
        config(['hims.cron_secret' => 'a-very-long-secret-value']);

        $this->get('/api/cron/prune', ['x-vercel-cron' => '1'])->assertForbidden();
        $this->get('/api/cron/prune?key=a-very-long-secret-value')->assertForbidden();
    }

    public function test_cron_rejects_everything_when_no_secret_outside_local(): void
    {
        config(['hims.cron_secret' => '']);

        $this->get('/api/cron/prune', ['Authorization' => 'Bearer anything'])->assertForbidden();
    }

    public function test_cron_accepts_bearer_or_header_secret(): void
    {
        config(['hims.cron_secret' => 'a-very-long-secret-value']);

        $this->get('/api/cron/prune', ['Authorization' => 'Bearer wrong'])->assertForbidden();
        $this->get('/api/cron/prune', ['Authorization' => 'Bearer a-very-long-secret-value'])
            ->assertOk()->assertJson(['status' => 'success', 'action' => 'hims:prune-expired-data']);
        $this->get('/api/cron/prune', ['X-Cron-Key' => 'a-very-long-secret-value'])->assertOk();
        $this->get('/api/cron/nope', ['X-Cron-Key' => 'a-very-long-secret-value'])->assertNotFound();
    }

    // ── Idle timeout ─────────────────────────────────────────

    public function test_activity_heartbeat_keeps_an_active_session_alive(): void
    {
        $user = $this->user('staff');

        $this->actingAs($user)
            ->withSession(['hims_last_activity' => time() - 30])
            ->postJson(route('session.activity'))
            ->assertOk()
            ->assertJson(['status' => 'active']);
    }

    public function test_heartbeat_cannot_revive_an_expired_session(): void
    {
        config(['session.idle_timeout' => 180]);
        $user = $this->user('staff');

        $this->actingAs($user)
            ->withSession(['hims_last_activity' => time() - 600])
            ->postJson(route('session.activity'))
            ->assertStatus(401);

        $this->assertGuest();
    }

    public function test_status_ping_does_not_count_as_activity(): void
    {
        config(['session.idle_timeout' => 180]);
        $user = $this->user('staff');
        $stamp = time() - 100;

        $this->actingAs($user)
            ->withSession(['hims_last_activity' => $stamp])
            ->postJson(route('session.ping'))
            ->assertOk()
            ->assertJson(['status' => 'active']);

        $this->assertSame($stamp, session('hims_last_activity'));
    }

    public function test_idle_timeout_is_one_unit_everywhere(): void
    {
        config(['session.idle_timeout' => 240]);

        $this->assertSame(240, EnforceSessionTimeout::idleTimeoutSeconds());
    }

    // ── Record access password ───────────────────────────────

    public function test_record_access_has_no_default_password(): void
    {
        DB::table('system_settings')->where('key', 'record_access_password_hash')->delete();
        $user = $this->user('admin');

        $this->actingAs($user)->postJson(route('record-access.unlock'), ['password' => 'password'])
            ->assertStatus(422);
    }

    public function test_record_access_never_redirects_off_site(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'record_access_password_hash'],
            ['value' => Hash::make('Correct-Horse-9'), 'updated_at' => now()]
        );
        $user = $this->user('admin');

        $this->actingAs($user)->post(route('record-access.verify'), [
            'password' => 'Correct-Horse-9',
            'intended' => 'https://evil.example.com/phish',
        ])->assertRedirect(url('/phish')); // host is dropped: stays on this site

        $this->actingAs($user)->post(route('record-access.verify'), [
            'password' => 'Correct-Horse-9',
            'intended' => 'javascript:alert(1)',
        ])->assertRedirect(url('/alert(1)'));

        $this->actingAs($user)->post(route('record-access.verify'), [
            'password' => 'Correct-Horse-9',
            'intended' => '//evil.example.com/employees',
        ])->assertRedirect(url('/employees'));
    }

    public function test_record_access_unlock_is_rate_limited(): void
    {
        $user = $this->user('admin');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson(route('record-access.unlock'), ['password' => 'guess'.$i]);
        }

        $this->actingAs($user)->postJson(route('record-access.unlock'), ['password' => 'guess-final'])
            ->assertStatus(429);
    }

    // ── Audit chain ──────────────────────────────────────────

    public function test_audit_chain_verifies_and_detects_tampering(): void
    {
        $user = $this->user('admin');
        $this->actingAs($user);

        AuditTrail::record('test_one', 'employees', (string) Str::uuid(), afterState: ['b' => 2, 'a' => 1]);
        AuditTrail::record('test_two', 'employees', (string) Str::uuid(), metadata: ['note' => 'x']);
        $third = AuditTrail::record('test_three', 'employees', (string) Str::uuid(), beforeState: ['x' => 'y']);

        $this->assertSame(0, Artisan::call('hims:audit-verify'));

        $rows = DB::table('audit_trails')->orderBy('chain_seq')->get();
        $this->assertSame([1, 2, 3], $rows->pluck('chain_seq')->map(fn ($v) => (int) $v)->all());
        $this->assertNotNull($rows[0]->chain_hash);
        $this->assertNotNull($rows[0]->after_state_hash);

        // Someone quietly edits history.
        DB::table('audit_trails')->where('audit_id', $third)->update(['before_state' => json_encode(['x' => 'z'])]);
        $this->assertSame(1, Artisan::call('hims:audit-verify'));
    }

    public function test_audit_chain_detects_a_deleted_row(): void
    {
        $this->actingAs($this->user('admin'));
        AuditTrail::record('a', 'employees', null);
        $middle = AuditTrail::record('b', 'employees', null);
        AuditTrail::record('c', 'employees', null);

        DB::table('audit_trails')->where('audit_id', $middle)->delete();

        $this->assertSame(1, Artisan::call('hims:audit-verify'));
    }

    public function test_prune_keeps_audit_trail_by_default(): void
    {
        $this->actingAs($this->user('admin'));
        $id = AuditTrail::record('old_event', 'employees', null);
        DB::table('audit_trails')->where('audit_id', $id)->update(['created_at' => now()->subYears(3)]);

        Artisan::call('hims:prune-expired-data');
        $this->assertDatabaseHas('audit_trails', ['audit_id' => $id]);

        // Explicit request below the six-year floor is raised to six years.
        Artisan::call('hims:prune-expired-data', ['--audit-days' => 30]);
        $this->assertDatabaseHas('audit_trails', ['audit_id' => $id]);
    }

    // ── Request-level audit coverage ─────────────────────────

    public function test_department_changes_are_audited(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Dialysis Unit',
            'department_code' => 'DIAL',
            'is_clinical' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'http_post',
            'resource_type' => 'departments.store',
            'user_id' => (string) $admin->id,
        ]);
    }

    public function test_failed_validation_is_not_audited_and_secrets_are_redacted(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('departments.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('audit_trails', ['resource_type' => 'departments.store']);

        $this->actingAs($admin)->post(route('settings.record-access-password'), [
            'current_admin_password' => 'password',
            'record_access_password' => 'Sup3r-Secret-Value!',
            'record_access_password_confirmation' => 'Sup3r-Secret-Value!',
        ])->assertSessionHasNoErrors();

        $logged = DB::table('audit_trails')->where('resource_type', 'settings.record-access-password')->value('after_state');
        $this->assertNotNull($logged);
        $this->assertStringNotContainsString('Sup3r-Secret-Value!', $logged);
        $this->assertStringNotContainsString('"password"', $logged);
    }

    public function test_viewing_an_employee_file_is_audited(): void
    {
        $dept = $this->department();
        $employee = $this->employee($dept, 'viewed@example.org');
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('employees.show', $employee))->assertOk();

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'view',
            'resource_type' => 'employees.show',
            'resource_id' => $employee,
        ]);
    }

    // ── Credential verification ──────────────────────────────

    public function test_hr_can_verify_another_employees_credential(): void
    {
        $dept = $this->department();
        $nurse = $this->employee($dept, 'nurse@example.org');
        $hrEmp = $this->employee($dept, 'hr@example.org');
        $hr = $this->user('hr_manager', $hrEmp);
        $cred = $this->credential($nurse);

        $this->actingAs($hr)->post(route('competency.credentials.verify', $cred))->assertRedirect()
            ->assertSessionHas('success');

        $row = DB::table('employee_credentials')->where('credential_id', $cred)->first();
        $this->assertSame($hrEmp, $row->verified_by);
        $this->assertNotNull($row->verified_at);
        $this->assertDatabaseHas('audit_trails', ['action' => 'verify_credential', 'resource_id' => $cred]);
    }

    public function test_nobody_verifies_their_own_credential_and_staff_cannot_verify(): void
    {
        $dept = $this->department();
        $hrEmp = $this->employee($dept, 'hr2@example.org');
        $hr = $this->user('hr_manager', $hrEmp);
        $own = $this->credential($hrEmp);

        $this->actingAs($hr)->post(route('competency.credentials.verify', $own))->assertSessionHas('error');
        $this->assertNull(DB::table('employee_credentials')->where('credential_id', $own)->value('verified_at'));

        $staffEmp = $this->employee($dept, 'staff@example.org');
        $staff = $this->user('staff', $staffEmp);
        $other = $this->credential($hrEmp);
        $this->actingAs($staff)->post(route('competency.credentials.verify', $other))->assertForbidden();
    }

    public function test_hr1_renewal_clears_old_verification(): void
    {
        $dept = $this->department();
        $nurse = $this->employee($dept, 'renew@example.org', null, ['employee_code' => 'EMP-RENEW1']);
        $cred = $this->credential($nurse, ['verified_at' => now()->subMonth(), 'credential_type' => 'PRC License']);

        app(HrIntegrationService::class)->processHr1CredentialsPayload([[
            'employee_code' => 'EMP-RENEW1',
            'email' => 'renew@example.org',
            'credential_type' => 'PRC License',
            'credential_number' => 'RN-0001234',
            'issuing_body' => 'PRC',
            'expiry_date' => now()->addYears(3)->toDateString(),
        ]]);

        $this->assertNull(DB::table('employee_credentials')->where('credential_id', $cred)->value('verified_at'));
    }

    // ── No invented figures ──────────────────────────────────

    public function test_training_dashboard_shows_no_invented_feedback_scores(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->get(route('training.index'))->assertOk();

        $this->assertSame([], $response->viewData('feedbackMetrics'));
        $this->assertNull($response->viewData('stats')['avg_feedback_score']);
        $response->assertDontSee('[4.5, 4.7, 4.3, 4.6]', false);
    }

    public function test_dashboards_contain_no_hardcoded_sample_chart_data(): void
    {
        foreach (['competency/index', 'learning/index', 'performance/index', 'recognition/index', 'training/index'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/(length|hasData) \? \w+ : \[/', $source, "{$view} still has sample-data fallbacks");
        }

        $appraisal = file_get_contents(resource_path('views/reports/appraisal.blade.php'));
        $this->assertStringNotContainsString('consistently demonstrates adherence to clinical protocols', $appraisal);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function user(string $role, ?string $employeeId = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@example.org',
            'password' => bcrypt('password'),
            'role' => $role,
            'email_verified_at' => now(),
            'employee_id' => $employeeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    private function department(string $name = 'Ward'): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(5),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(string $departmentId, string $email, ?string $roleId = null, array $overrides = []): string
    {
        if (! $roleId) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'role_id' => $roleId,
                'role_name' => 'Staff Nurse '.Str::random(5),
                'role_slug' => 'staff-nurse-'.Str::lower(Str::random(8)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(6)),
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function credential(string $employeeId, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employee_credentials')->insert(array_merge([
            'credential_id' => $id,
            'employee_id' => $employeeId,
            'credential_type' => 'BLS Certification',
            'credential_number' => Crypt::encryptString('BLS-998877'),
            'issuing_body' => 'AHA',
            'issue_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }
}
