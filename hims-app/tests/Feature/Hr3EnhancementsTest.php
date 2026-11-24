<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Models\User;
use App\Services\AwardService;
use App\Services\CompetencyGapAnalysisService;
use App\Services\SuccessionQualificationService;
use App\Support\AccountStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HR3 improvements, 2026-11-24 (second pass): landing page + 2-minute idle,
 * locked/deactivated accounts, gap calculation transparency, CPD
 * qualification, awards/certificates/badges, succession promotion checks.
 */
class Hr3EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    // ── Landing page & idle timeout ──────────────────────────

    public function test_landing_page_is_the_first_screen(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign in to HR3')->assertSee('HR1');
    }

    public function test_idle_session_ends_on_the_landing_page_after_two_minutes(): void
    {
        $this->assertSame(120, EnforceSessionTimeout::idleTimeoutSeconds());

        $user = $this->user('staff');
        $this->actingAs($user)->withSession(['hims_last_activity' => time() - 130])
            ->get(route('dashboard'))
            ->assertRedirect(route('landing', ['timeout' => 1]));

        $this->get(route('landing', ['timeout' => 1]))->assertSee('signed out after 2 minutes');
    }

    public function test_deactivated_session_lands_with_reason(): void
    {
        $user = $this->user('staff');
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->actingAs($user->fresh())->withSession(['hims_last_activity' => time()])
            ->get(route('dashboard'))
            ->assertRedirect(route('landing', ['reason' => 'deactivated']));
    }

    // ── Locked / deactivated accounts ────────────────────────

    public function test_locked_account_says_it_is_locked_when_password_is_right(): void
    {
        $user = $this->user('staff');
        DB::table('users')->where('id', $user->id)->update(['locked_until' => now()->addMinutes(10)]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account is locked after repeated failed sign-in attempts. Try again in 10 minute(s), or ask an administrator to unlock it.']);
    }

    public function test_account_status_shows_on_profile_and_directory(): void
    {
        $dept = $this->department();
        $emp = $this->employee($dept, 'lock@example.org');
        $locked = $this->user('staff', $emp);
        DB::table('users')->where('id', $locked->id)->update(['locked_until' => now()->addMinutes(5)]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('employees.show', $emp))->assertOk()->assertSee('Locked')->assertSee('Unlock');
        $this->actingAs($admin)->get(route('employees.index'))->assertOk()->assertSee('Login');

        DB::table('users')->where('id', $locked->id)->update(['is_active' => false, 'locked_until' => null]);
        $this->actingAs($admin)->get(route('employees.show', $emp))->assertSee('Deactivated')->assertSee('Reactivate login');
    }

    public function test_leaving_employee_loses_login_and_remembered_devices(): void
    {
        $dept = $this->department();
        $emp = $this->employee($dept, 'leaver@example.org');
        $user = $this->user('staff', $emp);
        DB::table('user_trusted_devices')->insert([
            'user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(30),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AccountStatus::deactivateForEmployee($emp, 'employee_status_resigned');

        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_active'));
        $this->assertSame(0, DB::table('user_trusted_devices')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('audit_trails', ['action' => 'user_account_auto_deactivated', 'resource_id' => (string) $user->id]);
    }

    // ── Competency gap calculation ───────────────────────────

    public function test_each_gap_carries_its_working(): void
    {
        $service = app(CompetencyGapAnalysisService::class);
        $compute = new \ReflectionMethod($service, 'computeGaps');

        $req = fn ($id, $required, $mandatory) => (object) [
            'competency_id' => $id, 'competency_name' => "C{$id}", 'competency_code' => "C{$id}", 'domain_name' => 'D',
            'category_name' => 'K', 'required_proficiency' => $required, 'is_critical' => false, 'is_mandatory' => $mandatory,
        ];
        $gaps = $compute->invoke($service,
            collect(['a' => $req('a', 4, true), 'b' => $req('b', 3, false), 'c' => $req('c', 3, false)]),
            collect(['a' => (object) ['current_proficiency' => 2, 'assessed_date' => null], 'b' => (object) ['current_proficiency' => 3, 'assessed_date' => null]]),
            ['competencies_trained' => ['a' => 'BLS refresher']],
        )->keyBy('competency_id');

        $this->assertSame('Current 2 − Required 4 = -2', $gaps['a']['calculation']['gap']);
        $this->assertSame(95, $gaps['a']['priority']); // 2×25 + 30 + 15
        $this->assertStringContainsString('= 95', $gaps['a']['calculation']['priority']);
        $this->assertSame('met', $gaps['b']['status']);
        $this->assertSame('unassessed', $gaps['c']['status']);

        $pct = new \ReflectionMethod($service, 'proficiencyPercentage');
        // min(2,4) + min(3,3) + 0 = 5 of 10 → 50%
        $this->assertSame(50, $pct->invoke($service, $gaps->values()));
    }

    // ── CPD qualification ────────────────────────────────────

    public function test_training_cpd_without_attendance_is_not_qualified(): void
    {
        [$staff, $emp] = $this->staffWithEmployee();
        $session = $this->trainingSession($emp, 2.0);

        $this->actingAs($staff)->post(route('learning.cpd.store'), [
            'source_type' => 'training', 'source_id' => $session, 'activity_name' => 'Sepsis bundle',
            'cpd_hours' => 2, 'date_earned' => now()->toDateString(),
        ])->assertSessionHas('error');

        $row = DB::table('cpd_records')->where('employee_id', $emp)->first();
        $this->assertFalse((bool) $row->verified);

        $this->actingAs($staff)->get(route('learning.cpd.index'))->assertOk()->assertSee('Not qualified')->assertSee('Not registered');
    }

    public function test_attendance_banks_qualified_cpd_automatically_and_caps_hours(): void
    {
        [$staff, $emp] = $this->staffWithEmployee();
        $session = $this->trainingSession($emp, 1.5);
        $regId = (string) Str::uuid();
        DB::table('training_registrations')->insert(['registration_id' => $regId, 'session_id' => $session, 'employee_id' => $emp, 'status' => 'registered']);

        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('training.sessions.checkin', $session), ['registrations' => [$regId => 'attended']])->assertRedirect();

        $cpd = DB::table('cpd_records')->where('employee_id', $emp)->where('source_id', $session)->first();
        $this->assertNotNull($cpd);
        $this->assertTrue((bool) $cpd->verified);
        $this->assertSame(1.5, (float) $cpd->cpd_hours);

        // Over-claiming against the same session is refused as a duplicate.
        $this->actingAs($staff)->post(route('learning.cpd.store'), [
            'source_type' => 'training', 'source_id' => $session, 'activity_name' => 'x', 'cpd_hours' => 20, 'date_earned' => now()->toDateString(),
        ])->assertSessionHas('error');

        // Attendance withdrawn → the entry stops qualifying.
        $this->actingAs($admin)->post(route('training.sessions.checkin', $session), ['registrations' => [$regId => 'no_show']]);
        $this->assertFalse((bool) DB::table('cpd_records')->where('cpd_id', $cpd->cpd_id)->value('verified'));
    }

    public function test_course_cpd_needs_completion_and_hr_cannot_force_it(): void
    {
        [$staff, $emp] = $this->staffWithEmployee();
        $course = $this->course(3);
        DB::table('course_enrollments')->insert(['enrollment_id' => (string) Str::uuid(), 'employee_id' => $emp, 'course_id' => $course,
            'enrollment_date' => now()->toDateString(), 'status' => 'in_progress', 'progress_pct' => 40]);

        $this->actingAs($staff)->post(route('learning.cpd.store'), [
            'source_type' => 'course', 'source_id' => $course, 'activity_name' => 'IPC', 'cpd_hours' => 3, 'date_earned' => now()->toDateString(),
        ])->assertSessionHas('error');

        $cpdId = DB::table('cpd_records')->where('employee_id', $emp)->value('cpd_id');
        $this->actingAs($this->user('hr_manager'))->post(route('learning.cpd.verify', $cpdId))->assertSessionHas('error');
        $this->assertFalse((bool) DB::table('cpd_records')->where('cpd_id', $cpdId)->value('verified'));
    }

    // ── Awards, certificates and badges ──────────────────────

    public function test_hr_issues_award_and_certificate_is_verifiable_and_shareable(): void
    {
        $dept = $this->department();
        $nurse = $this->employee($dept, 'nurse@example.org', ['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $hr = $this->user('hr_manager', $this->employee($dept, 'hr@example.org'));
        $type = DB::table('award_types')->where('name', 'Compassionate Care Award')->value('award_type_id');

        $this->actingAs($hr)->post(route('recognition.awards.issue'), [
            'award_type_id' => $type, 'employee_ids' => [$nurse], 'citation' => 'Stayed with a frightened family through the night.',
            'awarded_on' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $award = DB::table('employee_awards')->where('employee_id', $nurse)->first();
        $this->assertNotNull($award);
        $this->assertMatchesRegularExpression('/^HR3-AW-\d{4}-[A-Z0-9]{8}$/', $award->certificate_code);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $nurse, 'notification_type' => 'award_received']);

        auth()->logout();
        $this->get(route('awards.verify', $award->certificate_code))->assertOk()
            ->assertSee('Ana Reyes')->assertSee('Certificate of Compassionate Care')->assertSee('frightened family')->assertSee('Verified');

        $svg = $this->get(route('awards.badge', $award->certificate_code));
        $svg->assertOk();
        $this->assertStringStartsWith('image/svg+xml', $svg->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $svg->getContent());

        $this->actingAs($this->user('admin'))->get(route('employees.show', $nurse))->assertSee('Compassionate Care Award');

        $this->actingAs($hr)->post(route('recognition.awards.revoke', $award->award_id), ['reason' => 'Issued to the wrong person'])->assertRedirect();
        $this->get(route('awards.verify', $award->certificate_code))->assertSee('REVOKED');
    }

    public function test_staff_cannot_create_or_issue_awards_but_see_their_badges(): void
    {
        [$staff, $emp] = $this->staffWithEmployee();
        $type = DB::table('award_types')->value('award_type_id');

        $this->actingAs($staff)->post(route('recognition.awards.issue'), ['award_type_id' => $type, 'employee_ids' => [$emp], 'awarded_on' => now()->toDateString()])->assertForbidden();
        $this->actingAs($staff)->post(route('recognition.awards.types.store'), ['name' => 'X', 'category' => 'service', 'certificate_title' => 'X', 'points_value' => 1])->assertForbidden();

        app(AwardService::class)->issue($type, $emp);
        $this->actingAs($staff)->get(route('recognition.awards.index'))->assertOk()->assertSee('My badges');
    }

    public function test_hr_can_create_award_type_with_auto_rule(): void
    {
        $hr = $this->user('hr_manager');
        $this->actingAs($hr)->post(route('recognition.awards.types.store'), [
            'name' => 'Perfect Attendance Award', 'category' => 'conduct', 'certificate_title' => 'Certificate of Perfect Attendance',
            'badge_icon' => 'bi-calendar-check', 'badge_color' => '#123456', 'points_value' => 15,
            'auto_rule' => 'service_years', 'auto_value' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('award_types', ['name' => 'Perfect Attendance Award', 'auto_rule' => 'service_years', 'auto_value' => 2]);
    }

    public function test_service_milestones_are_awarded_automatically_once(): void
    {
        $dept = $this->department();
        $veteran = $this->employee($dept, 'vet@example.org', ['hire_date' => now()->subYears(11)->toDateString()]);
        $newbie = $this->employee($dept, 'new@example.org', ['hire_date' => now()->subYears(1)->toDateString()]);

        Artisan::call('hims:auto-awards');
        Artisan::call('hims:auto-awards');

        $names = DB::table('employee_awards as ea')->join('award_types as at', 'ea.award_type_id', '=', 'at.award_type_id')
            ->where('ea.employee_id', $veteran)->pluck('at.name')->sort()->values()->all();
        $this->assertSame(['10-Year Service Award', '5-Year Service Award'], $names);
        $this->assertSame(0, DB::table('employee_awards')->where('employee_id', $newbie)->count());
        $this->assertSame('auto', DB::table('employee_awards')->where('employee_id', $veteran)->value('source'));
    }

    // ── Succession promotion qualification ───────────────────

    public function test_unqualified_candidate_needs_override_to_be_promoted(): void
    {
        [$candidateId, $positionId, $emp] = $this->candidate(['readiness_level' => '1_2_years']);
        $hr = $this->user('hr_manager', $this->employee($this->department(), 'hr3@example.org'));

        $this->actingAs($hr)->get(route('succession.candidates.show', $candidateId))->assertOk()->assertSee('Not yet qualified');

        $this->actingAs($hr)->post(route('succession.candidates.promote', $candidateId))->assertSessionHas('error');
        $this->assertSame('approved', DB::table('succession_candidates')->where('candidate_id', $candidateId)->value('status'));

        $this->actingAs($hr)->post(route('succession.candidates.promote', $candidateId), ['override_reason' => 'Acting role covered for 18 months with excellent results'])
            ->assertSessionHas('success');
        $row = DB::table('succession_candidates')->where('candidate_id', $candidateId)->first();
        $this->assertSame('promoted', $row->status);
        $this->assertNotNull($row->promotion_override_reason);
        $this->assertSame($emp, DB::table('critical_positions')->where('position_id', $positionId)->value('current_holder_id'));
    }

    public function test_qualified_candidate_promotes_without_override(): void
    {
        [$candidateId, $positionId, $emp] = $this->candidate(['readiness_level' => 'ready_now']);
        DB::table('critical_positions')->where('position_id', $positionId)->update(['target_role_id' => $this->competentRole($emp)]);
        $this->review($emp, 4.2);
        $hr = $this->user('hr_manager', $this->employee($this->department(), 'hr4@example.org'));

        $result = app(SuccessionQualificationService::class)->evaluate(
            DB::table('succession_candidates')->where('candidate_id', $candidateId)->first(),
            DB::table('critical_positions')->where('position_id', $positionId)->first());
        $this->assertTrue($result['qualified'], json_encode($result['checks']));

        $this->actingAs($hr)->post(route('succession.candidates.promote', $candidateId))->assertSessionHas('success');
        $this->assertNull(DB::table('succession_candidates')->where('candidate_id', $candidateId)->value('promotion_override_reason'));
    }

    public function test_expired_required_licence_blocks_qualification(): void
    {
        [$candidateId, $positionId, $emp] = $this->candidate(['readiness_level' => 'ready_now']);
        DB::table('critical_positions')->where('position_id', $positionId)->update([
            'target_role_id' => $this->competentRole($emp), 'required_credentials' => json_encode(['PRC License']),
        ]);
        $this->review($emp, 4.5);
        DB::table('employee_credentials')->insert(['credential_id' => (string) Str::uuid(), 'employee_id' => $emp, 'credential_type' => 'PRC License',
            'issuing_body' => 'PRC', 'issue_date' => now()->subYears(4)->toDateString(), 'expiry_date' => now()->subDay()->toDateString(),
            'created_at' => now(), 'updated_at' => now()]);

        $result = app(SuccessionQualificationService::class)->evaluate(
            DB::table('succession_candidates')->where('candidate_id', $candidateId)->first(),
            DB::table('critical_positions')->where('position_id', $positionId)->first());

        $this->assertFalse($result['qualified']);
        $cred = collect($result['checks'])->firstWhere('key', 'credentials');
        $this->assertFalse($cred['passed']);
        $this->assertStringContainsString('PRC License', $cred['detail']);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function user(string $role, ?string $employeeId = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' User', 'email' => $role.'-'.Str::lower(Str::random(6)).'@example.org',
            'password' => bcrypt('password'), 'role' => $role, 'email_verified_at' => now(), 'employee_id' => $employeeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    private function staffWithEmployee(): array
    {
        $emp = $this->employee($this->department(), 'staff-'.Str::random(5).'@example.org');

        return [$this->user('staff', $emp), $emp];
    }

    private function department(): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert(['department_id' => $id, 'name' => 'Ward '.Str::random(6), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function role(): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert(['role_id' => $id, 'role_name' => 'Staff Nurse '.Str::random(5), 'role_slug' => 'sn-'.Str::lower(Str::random(8)), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function employee(string $dept, string $email, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id, 'employee_code' => 'EMP-'.Str::upper(Str::random(6)), 'first_name' => 'Test', 'last_name' => 'Person',
            'email' => $email, 'department_id' => $dept, 'role_id' => $this->role(), 'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function trainingSession(string $instructorId, float $hours): string
    {
        $id = (string) Str::uuid();
        DB::table('training_sessions')->insert([
            'session_id' => $id, 'title' => 'Sepsis Bundle Workshop', 'category' => 'clinical', 'instructor_id' => $instructorId,
            'session_date' => now()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00', 'capacity' => 20,
            'status' => 'completed', 'cpd_hours' => $hours, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function course(float $hours): string
    {
        $id = (string) Str::uuid();
        DB::table('courses')->insert(['course_id' => $id, 'title' => 'Infection Control', 'category' => 'compliance', 'cpd_hours' => $hours, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function candidate(array $overrides): array
    {
        $dept = $this->department();
        $emp = $this->employee($dept, 'cand-'.Str::random(5).'@example.org', ['hire_date' => now()->subYears(6)->toDateString()]);
        $positionId = (string) Str::uuid();
        DB::table('critical_positions')->insert(['position_id' => $positionId, 'position_title' => 'Nurse Manager, ICU', 'department_id' => $dept,
            'vacancy_risk' => 'high', 'is_critical' => true, 'created_at' => now(), 'updated_at' => now()]);
        $candidateId = (string) Str::uuid();
        DB::table('succession_candidates')->insert(array_merge([
            'candidate_id' => $candidateId, 'position_id' => $positionId, 'employee_id' => $emp, 'performance_score' => 4, 'potential_score' => 4,
            'readiness_level' => 'ready_now', 'status' => 'approved', 'nominated_at' => now()->subMonth(), 'approved_at' => now()->subWeek(),
        ], $overrides));

        return [$candidateId, $positionId, $emp];
    }

    /** A target role with one competency the employee already meets. */
    private function competentRole(string $employeeId): string
    {
        $roleId = $this->role();
        $domain = (string) Str::uuid();
        $category = (string) Str::uuid();
        $competency = (string) Str::uuid();
        DB::table('competency_domains')->insert(['domain_id' => $domain, 'domain_name' => 'Leadership '.Str::random(4), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('competency_categories')->insert(['category_id' => $category, 'domain_id' => $domain, 'category_name' => 'Unit management', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('competencies')->insert(['competency_id' => $competency, 'category_id' => $category, 'competency_code' => 'LDR-'.Str::upper(Str::random(4)),
            'competency_name' => 'Staffing & rostering', 'required_proficiency' => 3, 'is_mandatory' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_competency_requirements')->insert(['id' => (string) Str::uuid(), 'role_id' => $roleId, 'competency_id' => $competency, 'minimum_proficiency' => 3, 'is_critical' => true]);
        DB::table('competency_assessments')->insert(['assessment_id' => (string) Str::uuid(), 'employee_id' => $employeeId, 'competency_id' => $competency,
            'assessed_by' => $employeeId, 'current_proficiency' => 4, 'gap' => 1, 'assessed_date' => now()->subMonth()->toDateString(),
            'assessment_method' => 'observation', 'created_at' => now(), 'updated_at' => now()]);

        return $roleId;
    }

    private function review(string $employeeId, float $score): void
    {
        $cycle = (string) Str::uuid();
        DB::table('review_cycles')->insert(['cycle_id' => $cycle, 'cycle_name' => 'Annual '.Str::random(4), 'cycle_type' => 'annual',
            'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->subMonth()->toDateString(), 'status' => 'closed', 'created_by' => $employeeId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('performance_reviews')->insert(['review_id' => (string) Str::uuid(), 'employee_id' => $employeeId, 'cycle_id' => $cycle,
            'status' => 'completed', 'overall_score' => $score, 'created_at' => now(), 'updated_at' => now()]);
    }
}
