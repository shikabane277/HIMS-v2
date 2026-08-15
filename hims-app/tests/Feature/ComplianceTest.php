<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RenewalCycleService;
use App\Services\TrainingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The compliance layer: mandatory training assignment, renewal cycles, and the
 * oversight pages over both.
 *
 * Stays on sqlite. The queries under test are portable — the MySQL-only SQL and
 * the competency triggers are in modules this feature only reads through
 * aggregate counts, so nothing here needs the `@group mysql` gate that
 * Feature\EmployeeProgressionTest carries.
 */
class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    // ── Assignment ───────────────────────────────────────────

    public function test_a_department_assignment_enrolls_everyone_in_it(): void
    {
        [$admin] = $this->user('admin');
        $dept = $this->department('ICU');
        $a = $this->employee($dept, 'a@example.org');
        $b = $this->employee($dept, 'b@example.org');
        $elsewhere = $this->employee($this->department('Wards'), 'c@example.org');
        $course = $this->course();

        $this->actingAs($admin)->post(route('compliance.assignments.store'), [
            'subject_type' => 'course',
            'subject_id' => $course,
            'target_type' => 'department',
            'target_id' => $dept,
            'required_by' => now()->addMonth()->toDateString(),
            'reason' => 'JCI IPSG.1',
        ])->assertRedirect();

        $enrolled = DB::table('course_enrollments')->where('course_id', $course)
            ->whereNotNull('assignment_id')->pluck('employee_id')->all();

        sort($enrolled);
        $expected = [$a, $b];
        sort($expected);

        $this->assertSame($expected, $enrolled);
        $this->assertNotContains($elsewhere, $enrolled);
    }

    public function test_assigning_does_not_disturb_an_existing_self_enrollment(): void
    {
        [$admin] = $this->user('admin');
        $dept = $this->department('ICU');
        $employee = $this->employee($dept, 'self@example.org');
        $course = $this->course();

        // Already enrolled by choice, half way through.
        DB::table('course_enrollments')->insert([
            'enrollment_id' => (string) Str::uuid(),
            'employee_id' => $employee,
            'course_id' => $course,
            'enrollment_date' => now()->subWeek()->toDateString(),
            'status' => 'in_progress',
            'progress_pct' => 60,
        ]);

        $this->actingAs($admin)->post(route('compliance.assignments.store'), [
            'subject_type' => 'course',
            'subject_id' => $course,
            'target_type' => 'employee',
            'target_id' => $employee,
        ])->assertRedirect();

        $row = DB::table('course_enrollments')
            ->where('employee_id', $employee)->where('course_id', $course)->first();

        $this->assertSame(60, (int) $row->progress_pct);
        $this->assertSame('in_progress', $row->status);
        $this->assertNull($row->assignment_id, 'a self-enrollment must not be retagged as assigned');
    }

    public function test_compliance_rate_counts_completions_against_the_roster(): void
    {
        $dept = $this->department('ICU');
        $done = $this->employee($dept, 'done@example.org');
        $this->employee($dept, 'pending@example.org');
        $course = $this->course();

        $result = app(TrainingAssignmentService::class)->assign(
            'course', $course, 'department', $dept, now()->addMonth()->toDateString(), null,
        );

        DB::table('course_enrollments')
            ->where('employee_id', $done)->where('course_id', $course)
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $assignment = DB::table('training_assignments')
            ->where('assignment_id', $result['assignment_id'])->first();

        $compliance = app(TrainingAssignmentService::class)->complianceFor($assignment);

        $this->assertSame(2, $compliance['total']);
        $this->assertSame(1, $compliance['complete']);
        $this->assertSame(1, $compliance['outstanding']);
        $this->assertSame(50.0, $compliance['rate']);
    }

    /**
     * The organisation dashboard's Overdue Required Training tile.
     *
     * It delegates to `overdueCount()` precisely so it cannot disagree with the
     * Required Training tab, so this asserts the three cases that make the
     * definitions match: past due and unfinished counts, past due and finished
     * does not, and an assignment with no `required_by` never counts however old
     * it is — nothing was asked of those people by a date.
     */
    public function test_the_overdue_count_matches_the_per_assignment_definition(): void
    {
        $dept = $this->department('ICU');
        $late = $this->employee($dept, 'late@example.org');
        $done = $this->employee($dept, 'done@example.org');
        $service = app(TrainingAssignmentService::class);

        $overdue = $service->assign(
            'course', $this->course(), 'department', $dept, now()->subWeek()->toDateString(), null,
        );

        // One of the two finished it, so only the other is overdue.
        DB::table('course_enrollments')
            ->where('employee_id', $done)
            ->where('assignment_id', $overdue['assignment_id'])
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertSame(1, $service->overdueCount());

        // A course nobody was given a deadline for is never overdue.
        $service->assign('course', $this->course(), 'department', $dept, null, null);

        $this->assertSame(1, $service->overdueCount());

        // And the tile agrees with the assignment's own figure.
        $assignment = DB::table('training_assignments')
            ->where('assignment_id', $overdue['assignment_id'])->first();

        $this->assertSame(
            $service->complianceFor($assignment)['overdue'],
            $service->overdueCount(),
            'the dashboard tile and the Required Training row must not disagree'
        );

        // And it is the person who did not finish who is being counted.
        $this->assertSame(
            [$late],
            DB::table('course_enrollments')
                ->where('assignment_id', $overdue['assignment_id'])
                ->where('status', '!=', 'completed')
                ->pluck('employee_id')->all()
        );
    }

    public function test_a_staff_user_cannot_assign_training(): void
    {
        [$staff] = $this->user('staff');
        $dept = $this->department('ICU');
        $this->employee($dept, 'x@example.org');

        $this->actingAs($staff)->post(route('compliance.assignments.store'), [
            'subject_type' => 'course',
            'subject_id' => $this->course(),
            'target_type' => 'department',
            'target_id' => $dept,
        ])->assertForbidden();

        $this->assertSame(0, DB::table('training_assignments')->count());
    }

    public function test_the_assignment_writes_one_audit_row(): void
    {
        [$admin] = $this->user('admin');
        $dept = $this->department('ICU');
        $this->employee($dept, 'a@example.org');

        $this->actingAs($admin)->post(route('compliance.assignments.store'), [
            'subject_type' => 'course',
            'subject_id' => $this->course(),
            'target_type' => 'department',
            'target_id' => $dept,
        ])->assertRedirect();

        $audit = DB::table('audit_trails')->where('action', 'assign_training')->first();

        $this->assertNotNull($audit, 'assignment must log through the existing audit trail');
        $this->assertSame('training_assignments', $audit->resource_type);
    }

    public function test_a_role_assignment_reaches_that_role_across_departments(): void
    {
        [$admin] = $this->user('admin');
        $nurseRole = $this->role('Staff Nurse');
        $clerkRole = $this->role('Records Clerk');

        $icuNurse = $this->employee($this->department('ICU'), 'icu@example.org', $nurseRole);
        $wardNurse = $this->employee($this->department('Wards'), 'ward@example.org', $nurseRole);
        $clerk = $this->employee($this->department('Records'), 'clerk@example.org', $clerkRole);
        $course = $this->course();

        $this->actingAs($admin)->post(route('compliance.assignments.store'), [
            'subject_type' => 'course',
            'subject_id' => $course,
            'target_type' => 'role',
            'target_id' => $nurseRole,
        ])->assertRedirect();

        $enrolled = DB::table('course_enrollments')->pluck('employee_id')->all();

        $this->assertContains($icuNurse, $enrolled);
        $this->assertContains($wardNurse, $enrolled, 'a role target is not bounded by department');
        $this->assertNotContains($clerk, $enrolled);
    }

    public function test_a_resigned_employee_is_left_out_of_an_assignment(): void
    {
        $dept = $this->department('ICU');
        $active = $this->employee($dept, 'active@example.org');
        $resigned = $this->employee($dept, 'gone@example.org', null, ['employment_status' => 'resigned']);

        app(TrainingAssignmentService::class)
            ->assign('course', $this->course(), 'department', $dept, null, null);

        $enrolled = DB::table('course_enrollments')->pluck('employee_id')->all();

        $this->assertSame([$active], $enrolled);
        $this->assertNotContains($resigned, $enrolled);
    }

    // ── Recording completion ─────────────────────────────────

    /**
     * The write the compliance rate was waiting on. Nothing in the app used to
     * set `course_enrollments.status = 'completed'`, so an assignment's rate
     * could only ever read 0% — a number on screen that no action could move.
     */
    public function test_marking_an_assigned_enrollment_complete_moves_the_compliance_rate(): void
    {
        [$admin] = $this->user('admin');
        $dept = $this->department('ICU');
        $finisher = $this->employee($dept, 'finisher@example.org');
        $this->employee($dept, 'laggard@example.org');

        $result = app(TrainingAssignmentService::class)
            ->assign('course', $this->course(), 'department', $dept, null, null);

        $assignment = DB::table('training_assignments')
            ->where('assignment_id', $result['assignment_id'])->first();
        $service = app(TrainingAssignmentService::class);

        $this->assertSame(0.0, $service->complianceFor($assignment)['rate']);

        $enrollment = DB::table('course_enrollments')->where('employee_id', $finisher)->value('enrollment_id');

        $this->actingAs($admin)
            ->post(route('learning.enrollments.complete', $enrollment))
            ->assertRedirect();

        $this->assertSame(50.0, $service->complianceFor($assignment)['rate']);

        $row = DB::table('course_enrollments')->where('enrollment_id', $enrollment)->first();
        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->completed_at);
        $this->assertSame(100, (int) $row->progress_pct);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'complete_enrollment',
            'resource_type' => 'course_enrollments',
            'resource_id' => $enrollment,
        ]);
    }

    /**
     * Completion is what makes a renewal cycle move: the hours land as a
     * verified `cpd_records` row, which is the only thing attainedHours() sums.
     */
    public function test_completing_a_course_credits_verified_cpd_a_renewal_cycle_counts(): void
    {
        [$admin] = $this->user('admin');
        $employee = $this->employee($this->department('ICU'), 'credited@example.org');
        $course = $this->course();

        $enrollment = (string) Str::uuid();
        DB::table('course_enrollments')->insert([
            'enrollment_id' => $enrollment,
            'employee_id' => $employee,
            'course_id' => $course,
            'enrollment_date' => now()->toDateString(),
            'status' => 'in_progress',
            'progress_pct' => 40,
        ]);

        $this->actingAs($admin)->post(route('learning.enrollments.complete', $enrollment))->assertRedirect();

        $cpd = DB::table('cpd_records')
            ->where('employee_id', $employee)->where('source_type', 'course')->first();

        $this->assertNotNull($cpd, 'finishing a course must bank its CPD hours');
        $this->assertSame($course, $cpd->source_id);
        $this->assertSame(3.0, (float) $cpd->cpd_hours);
        $this->assertTrue((bool) $cpd->verified, 'HIMS-sourced hours are evidenced by the completion itself');

        $attained = app(RenewalCycleService::class)->attainedHours(
            $employee, now()->subMonth()->toDateString(), now()->addMonth()->toDateString(),
        );

        $this->assertSame(3.0, $attained);
        $this->assertSame(3.0, (float) DB::table('course_enrollments')
            ->where('enrollment_id', $enrollment)->value('cpd_hours_earned'));
    }

    public function test_a_staff_user_cannot_record_a_completion(): void
    {
        $employee = $this->employee($this->department('ICU'), 'self@example.org');
        [$staff] = $this->user('staff', $employee);

        $enrollment = $this->enrollment($employee, $this->course());

        $this->actingAs($staff)
            ->post(route('learning.enrollments.complete', $enrollment))
            ->assertForbidden();

        $this->assertSame('enrolled', DB::table('course_enrollments')
            ->where('enrollment_id', $enrollment)->value('status'));
    }

    /**
     * A supervisor's reach is the reporting line, not the department. The
     * out-of-reach enrolment here belongs to somebody in the *same* department
     * who does not report to them — which is exactly the case the old
     * department match got wrong.
     */
    public function test_a_supervisor_completes_only_for_their_own_direct_reports(): void
    {
        $icu = $this->department('ICU');
        $boss = $this->employee($icu, 'boss@example.org');
        [$supervisor] = $this->user('supervisor', $boss);

        $course = $this->course();
        $mine = $this->enrollment($this->employee($icu, 'mine@example.org', null, ['supervisor_id' => $boss]), $course);
        $theirs = $this->enrollment($this->employee($icu, 'theirs@example.org'), $course);

        $this->actingAs($supervisor)->post(route('learning.enrollments.complete', $mine))->assertRedirect();
        $this->actingAs($supervisor)->post(route('learning.enrollments.complete', $theirs))->assertForbidden();

        $this->assertSame('completed', DB::table('course_enrollments')->where('enrollment_id', $mine)->value('status'));
        $this->assertSame('enrolled', DB::table('course_enrollments')->where('enrollment_id', $theirs)->value('status'));
    }

    /** Recording the same completion twice must not bank the hours twice. */
    public function test_completing_the_same_enrollment_twice_is_refused(): void
    {
        [$admin] = $this->user('admin');
        $employee = $this->employee($this->department('ICU'), 'twice@example.org');
        $enrollment = $this->enrollment($employee, $this->course());

        $this->actingAs($admin)->post(route('learning.enrollments.complete', $enrollment));
        $this->actingAs($admin)->post(route('learning.enrollments.complete', $enrollment))
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('cpd_records')->where('employee_id', $employee)->count());
    }

    public function test_reopening_withdraws_the_completion_and_its_cpd_credit(): void
    {
        [$admin] = $this->user('admin');
        $employee = $this->employee($this->department('ICU'), 'undo@example.org');
        $enrollment = $this->enrollment($employee, $this->course());

        $this->actingAs($admin)->post(route('learning.enrollments.complete', $enrollment));
        $this->assertSame(1, DB::table('cpd_records')->where('employee_id', $employee)->count());

        $this->actingAs($admin)->post(route('learning.enrollments.reopen', $enrollment))->assertRedirect();

        $row = DB::table('course_enrollments')->where('enrollment_id', $enrollment)->first();
        $this->assertSame('in_progress', $row->status);
        $this->assertNull($row->completed_at);
        $this->assertSame(0.0, (float) $row->cpd_hours_earned);

        $this->assertSame(0, DB::table('cpd_records')->where('employee_id', $employee)->count(),
            'the hours must go with the completion, or the cycle keeps counting them');
    }

    /** Withdrawing evidence is narrower than adding it: HR/admin only. */
    public function test_a_supervisor_cannot_reopen_a_completion(): void
    {
        $icu = $this->department('ICU');
        $boss = $this->employee($icu, 'boss2@example.org');
        [$supervisor] = $this->user('supervisor', $boss);

        $enrollment = $this->enrollment(
            $this->employee($icu, 'done@example.org', null, ['supervisor_id' => $boss]),
            $this->course()
        );
        $this->actingAs($supervisor)->post(route('learning.enrollments.complete', $enrollment));

        $this->actingAs($supervisor)->post(route('learning.enrollments.reopen', $enrollment))->assertForbidden();

        $this->assertSame('completed', DB::table('course_enrollments')
            ->where('enrollment_id', $enrollment)->value('status'));
    }

    // ── Renewal cycles ───────────────────────────────────────

    public function test_only_verified_cpd_inside_the_window_counts(): void
    {
        $employee = $this->employee($this->department('ICU'), 'cpd@example.org');

        $this->cpd($employee, now()->subMonth()->toDateString(), 8);          // counts
        $this->cpd($employee, now()->subMonths(2)->toDateString(), 5);        // counts
        $this->cpd($employee, now()->subMonth()->toDateString(), 40, false);  // unverified
        $this->cpd($employee, now()->subYears(3)->toDateString(), 30);        // before the window

        $attained = app(RenewalCycleService::class)->attainedHours(
            $employee,
            now()->subYear()->toDateString(),
            now()->addYear()->toDateString(),
        );

        $this->assertSame(13.0, $attained);
    }

    public function test_syncing_opens_one_cycle_per_rule_and_does_not_duplicate(): void
    {
        $employee = $this->employee($this->department('ICU'), 'sync@example.org');
        $this->rule(['subject_key' => 'nursing', 'label' => 'PRC Nursing CPD']);

        $service = app(RenewalCycleService::class);

        $this->assertSame(1, $service->syncCycles($employee));
        $this->assertSame(0, $service->syncCycles($employee), 'a second sync must not reopen the same window');
        $this->assertSame(1, DB::table('employee_renewal_cycles')->where('employee_id', $employee)->count());
    }

    public function test_a_credential_rule_skips_someone_who_does_not_hold_it(): void
    {
        $employee = $this->employee($this->department('ICU'), 'nocred@example.org');
        $this->rule(['subject_type' => 'credential', 'subject_key' => 'BLS', 'label' => 'BLS renewal']);

        $this->assertSame(0, app(RenewalCycleService::class)->syncCycles($employee));
    }

    public function test_a_closed_window_with_hours_still_owed_reads_as_shortfall(): void
    {
        $employee = $this->employee($this->department('ICU'), 'short@example.org');
        $rule = $this->rule();
        $cycle = $this->cycle($employee, $rule, now()->subMonths(13), now()->subDay(), 45);

        $decorated = app(RenewalCycleService::class)->decorate($cycle);

        $this->assertSame('shortfall', $decorated->risk);
        $this->assertSame(45.0, $decorated->hours_remaining);
    }

    public function test_a_cycle_whose_hours_are_in_reads_as_met_and_caps_at_100(): void
    {
        $employee = $this->employee($this->department('ICU'), 'met@example.org');
        $rule = $this->rule();
        $this->cpd($employee, now()->subMonth()->toDateString(), 60);
        $cycle = $this->cycle($employee, $rule, now()->subMonths(11), now()->addMonth(), 45);

        $decorated = app(RenewalCycleService::class)->decorate($cycle);

        $this->assertSame('met', $decorated->risk);
        $this->assertSame(0.0, $decorated->hours_remaining);
        $this->assertSame(100.0, $decorated->pct_complete, 'over-attainment must not read as 133%');
    }

    // ── Pages and access ─────────────────────────────────────

    public function test_a_supervisor_reaches_oversight_but_not_the_rules(): void
    {
        [$supervisor] = $this->user('supervisor');

        $this->actingAs($supervisor)->get(route('compliance.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('compliance.at-risk'))->assertOk();
        $this->actingAs($supervisor)->get(route('compliance.accreditation'))->assertOk();

        // Rules are hospital-wide policy, and account coverage exposes login state.
        $this->actingAs($supervisor)->get(route('compliance.rules.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('compliance.account-coverage'))->assertForbidden();
    }

    public function test_staff_are_kept_out_of_the_compliance_area_entirely(): void
    {
        [$staff] = $this->user('staff');

        foreach (['compliance.index', 'compliance.at-risk', 'compliance.accreditation'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
    }

    /**
     * Both employees sit in the supervisor's own department; only one reports to
     * them. The list must follow `supervisor_id`, so the colleague they have no
     * authority over stays off it.
     */
    public function test_a_supervisors_at_risk_list_stops_at_their_own_reports(): void
    {
        $icu = $this->department('ICU');

        $boss = $this->employee($icu, 'boss@example.org');
        [$supervisor] = $this->user('supervisor', $boss);

        $mine = $this->employee($icu, 'mine@example.org', null, ['last_name' => 'Reportsto', 'supervisor_id' => $boss]);
        $theirs = $this->employee($icu, 'theirs@example.org', null, ['last_name' => 'Outsidechain']);

        $rule = $this->rule();
        foreach ([$mine, $theirs] as $employee) {
            $this->cycle($employee, $rule, now()->subMonths(13), now()->subDay(), 45);
        }

        $this->actingAs($supervisor)->get(route('compliance.at-risk'))
            ->assertOk()
            ->assertSee('Reportsto')
            ->assertDontSee('Outsidechain');
    }

    public function test_every_compliance_page_works_for_an_employee_with_no_login(): void
    {
        [$admin] = $this->user('admin');
        $dept = $this->department('ICU');

        // Proxy-tracked: an employee record with nothing in `users` behind it.
        $proxy = $this->employee($dept, 'proxy@example.org', null, ['last_name' => 'Proxyonly']);
        $rule = $this->rule();
        $this->cycle($proxy, $rule, now()->subMonths(13), now()->subDay(), 45);

        app(TrainingAssignmentService::class)
            ->assign('course', $this->course(), 'department', $dept, null, null);

        $this->assertSame(1, DB::table('course_enrollments')->where('employee_id', $proxy)->count());

        foreach (['compliance.at-risk', 'compliance.accreditation', 'compliance.account-coverage'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('Proxyonly');
        }
    }

    public function test_my_cycles_tells_an_unlinked_account_why_it_is_empty(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Unlinked', 'email' => 'unlinked@example.org',
            'password' => bcrypt('password'), 'role' => 'staff',
            'email_verified_at' => now(), 'employee_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::find($userId))
            ->get(route('learning.cycles.mine'))
            ->assertOk()
            ->assertSee('not linked to an employee record');
    }

    /**
     * The accreditation rollup reads `jci_standard_code` from
     * `competency_categories`, not from `competencies` — the column only exists on
     * the category table, so selecting or filtering it off `competencies` is a hard
     * SQL error. Every earlier test got a 200 out of this page because none of them
     * put a competency in front of it; this one does.
     *
     * The standard narrows the competency columns, not the roster: an employee with
     * nothing assessed under the chosen standard is still a row on the report, which
     * is the point — a survey wants to see who has no evidence, not a shorter list.
     */
    public function test_the_accreditation_report_reports_against_the_standard_on_the_category(): void
    {
        [$admin] = $this->user('admin');
        $employee = $this->employee($this->department('ICU'), 'assessed@example.org', null, [
            'last_name' => 'Standardcase',
        ]);

        $this->assessment($employee, $this->competency($this->category('IPSG.1'), 4), 2);

        $this->actingAs($admin)->get(route('compliance.accreditation'))
            ->assertOk()->assertSee('Standardcase')->assertDontSee('Not assessed');

        $this->actingAs($admin)->get(route('compliance.accreditation', ['standard' => 'IPSG.1']))
            ->assertOk()->assertSee('Standardcase')->assertDontSee('Not assessed');

        $this->actingAs($admin)->get(route('compliance.accreditation', ['standard' => 'QPS.9']))
            ->assertOk()->assertSee('Standardcase')->assertSee('Not assessed');
    }

    public function test_the_accreditation_report_filters_by_department_and_role(): void
    {
        [$admin] = $this->user('admin');
        $nurseRole = $this->role('Staff Nurse');
        $icu = $this->department('ICU');

        $this->employee($icu, 'icu@example.org', $nurseRole, ['last_name' => 'Icunurse']);
        $this->employee($this->department('Wards'), 'ward@example.org', null, ['last_name' => 'Wardperson']);

        $this->actingAs($admin)->get(route('compliance.accreditation', ['department' => $icu]))
            ->assertOk()->assertSee('Icunurse')->assertDontSee('Wardperson');

        $this->actingAs($admin)->get(route('compliance.accreditation', ['role' => $nurseRole]))
            ->assertOk()->assertSee('Icunurse')->assertDontSee('Wardperson');
    }

    public function test_my_cycles_shows_a_staff_member_their_own_standing(): void
    {
        $employee = $this->employee($this->department('ICU'), 'own@example.org');
        [$staff] = $this->user('staff', $employee);
        $this->rule(['label' => 'PRC Nursing CPD']);
        $this->cpd($employee, now()->toDateString(), 10);

        // The page syncs on load, so the cycle does not have to pre-exist.
        $this->actingAs($staff)->get(route('learning.cycles.mine'))
            ->assertOk()
            ->assertSee('PRC Nursing CPD');
    }

    // ── Fixtures ─────────────────────────────────────────────

    /**
     * A signed-in account. Passing $employeeId links it to an existing employee;
     * otherwise the account is deliberately unlinked, which every page must
     * tolerate.
     *
     * @return array{0: User}
     */
    private function user(string $role, ?string $employeeId = null): array
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

        return [User::find($id)];
    }

    private function department(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(4),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** roles.role_slug is NOT NULL and unique — omitting it fails the insert. */
    private function role(string $name = 'Staff Nurse'): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => $name.' '.Str::random(4),
            'role_slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(string $departmentId, string $email, ?string $roleId = null, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(6)),
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $roleId ?: $this->role(),
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function course(): string
    {
        $id = (string) Str::uuid();
        DB::table('courses')->insert([
            'course_id' => $id,
            'title' => 'Infection Control '.Str::random(4),
            'category' => 'compliance',
            'cpd_hours' => 3,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** A plain enrolment, in the state both enrolment paths leave it in. */
    private function enrollment(string $employeeId, string $courseId): string
    {
        $id = (string) Str::uuid();
        DB::table('course_enrollments')->insert([
            'enrollment_id' => $id,
            'employee_id' => $employeeId,
            'course_id' => $courseId,
            'enrollment_date' => now()->toDateString(),
            'status' => 'enrolled',
            'progress_pct' => 0,
        ]);

        return $id;
    }

    private function rule(array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('renewal_rules')->insert(array_merge([
            'rule_id' => $id,
            'subject_type' => 'cpd',
            'subject_key' => 'nursing-'.Str::lower(Str::random(5)),
            'label' => 'PRC Nursing CPD',
            'required_hours' => 45,
            'cycle_months' => 36,
            'grace_days' => 0,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    /** An open cycle over an explicit window, returned decorated-ready. */
    private function cycle(string $employeeId, string $ruleId, $start, $end, float $required): object
    {
        $id = (string) Str::uuid();
        DB::table('employee_renewal_cycles')->insert([
            'cycle_id' => $id,
            'employee_id' => $employeeId,
            'rule_id' => $ruleId,
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $end->toDateString(),
            'hours_required_snapshot' => $required,
            'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('employee_renewal_cycles as erc')
            ->join('renewal_rules as rr', 'rr.rule_id', '=', 'erc.rule_id')
            ->where('erc.cycle_id', $id)
            ->select('erc.*', 'rr.label', 'rr.subject_type', 'rr.grace_days')
            ->first();
    }

    /**
     * A category carrying the JCI tag, with the domain row its FK requires.
     * `competency_domains.domain_name` is unique, so the name is randomised.
     */
    private function category(?string $jciStandard = null): string
    {
        $domainId = (string) Str::uuid();
        DB::table('competency_domains')->insert([
            'domain_id' => $domainId,
            'domain_name' => 'Clinical Care '.Str::random(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = (string) Str::uuid();
        DB::table('competency_categories')->insert([
            'category_id' => $id,
            'domain_id' => $domainId,
            'category_name' => 'Patient Safety',
            'jci_standard_code' => $jciStandard,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function competency(string $categoryId, int $requiredProficiency): string
    {
        $id = (string) Str::uuid();
        DB::table('competencies')->insert([
            'competency_id' => $id,
            'category_id' => $categoryId,
            'competency_name' => 'Hand Hygiene '.Str::random(4),
            'competency_code' => 'C-'.Str::upper(Str::random(6)),
            'required_proficiency' => $requiredProficiency,
            'is_mandatory' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * `assessed_by` is NOT NULL and FKs back to `employees`, so it needs a real
     * row — the subject stands in as their own assessor, which no assertion here
     * depends on. `gap` is left null: MySQL triggers compute it, sqlite has none,
     * and the column is nullable either way.
     */
    private function assessment(string $employeeId, string $competencyId, int $proficiency): void
    {
        DB::table('competency_assessments')->insert([
            'assessment_id' => (string) Str::uuid(),
            'employee_id' => $employeeId,
            'competency_id' => $competencyId,
            'assessed_by' => $employeeId,
            'assessment_method' => 'observation',
            'current_proficiency' => $proficiency,
            'assessed_date' => now()->subMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cpd(string $employeeId, string $dateEarned, float $hours, bool $verified = true): void
    {
        DB::table('cpd_records')->insert([
            'cpd_id' => (string) Str::uuid(),
            'employee_id' => $employeeId,
            'source_type' => 'external',
            'activity_name' => 'Seminar '.Str::random(4),
            'cpd_hours' => $hours,
            'date_earned' => $dateEarned,
            'verified' => $verified,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
