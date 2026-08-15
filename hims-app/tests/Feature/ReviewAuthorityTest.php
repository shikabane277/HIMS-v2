<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CycleStatus;
use App\Support\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Performance review authority: who may open a review, who may score it, and
 * when it stops being editable.
 *
 * The rule under test is identity, not role — `employees.supervisor_id` decides
 * reviewer legitimacy, and admin/HR reach outside the chain only through the
 * logged exception path. Every case here therefore builds a reporting line
 * explicitly; a fixture that only shares a department is the *negative* case.
 *
 * There is one voice on a review: the reviewer's. The subject and their peers
 * write nothing, so the only column ownership left to test is that nobody but
 * the named reviewer reaches `supervisor_score` — and that the cycle's end date
 * takes even them off it.
 *
 * Stays on sqlite. The write paths exercised here are portable Query Builder.
 * The three tests that drive a GET screen are the exception — every review
 * listing selects `CONCAT(first_name,' ',last_name)`, which sqlite has no
 * function for — so those call requiresMysql() and skip rather than pretend.
 */
class ReviewAuthorityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Skip a test that reaches one of the review read screens.
     *
     * Per-method rather than in setUp(): the authority rules themselves are all
     * portable, and gating the whole class on MySQL would take the write-path
     * coverage — which is where the rules actually live — out of the default run.
     */
    private function requiresMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The review listings select CONCAT(), which sqlite does not provide.');
        }
    }

    // ── Opening a review ─────────────────────────────────────

    public function test_a_supervisor_opens_a_review_for_a_direct_report(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss);

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $cycle,
            'review_type' => 'standard',
        ])->assertRedirect();

        $review = DB::table('performance_reviews')->where('employee_id', $report)->first();

        $this->assertNotNull($review);
        $this->assertSame($boss, $review->reviewer_id);
        $this->assertFalse((bool) $review->is_exception_review);
        $this->assertNull($review->exception_basis);
        $this->assertSame(ReviewStatus::DRAFT, $review->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'review_created',
            'resource_id' => $review->review_id,
            'employee_id' => $boss,
            'user_id' => (string) $supervisor->id,
        ]);
    }

    public function test_a_linked_hr_user_creates_a_review_cycle_with_an_authorship_audit(): void
    {
        $hrEmployee = $this->employee($this->department('HR'), 'hr@example.org');
        [$hr] = $this->user('hr_manager', $hrEmployee);

        $this->actingAs($hr)->post(route('performance.cycles.store'), [
            'cycle_name' => 'Annual Review 2027',
            'cycle_type' => 'annual',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])->assertRedirect(route('performance.index'));

        $cycle = DB::table('review_cycles')->where('cycle_name', 'Annual Review 2027')->first();
        $this->assertNotNull($cycle);
        $this->assertSame($hrEmployee, $cycle->created_by);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'review_cycle_created',
            'resource_id' => $cycle->cycle_id,
            'employee_id' => $hrEmployee,
            'user_id' => (string) $hr->id,
        ]);
    }

    public function test_an_unlinked_account_cannot_attribute_a_cycle_to_someone_else(): void
    {
        [$admin] = $this->user('admin', null);

        $this->actingAs($admin)->post(route('performance.cycles.store'), [
            'cycle_name' => 'Unattributed Cycle',
            'cycle_type' => 'annual',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('review_cycles', 0);
        $this->assertDatabaseMissing('audit_trails', ['action' => 'review_cycle_created']);
    }

    public function test_review_cycle_edits_record_before_after_state_and_actor(): void
    {
        $hrEmployee = $this->employee($this->department('HR'), 'hr@example.org');
        [$hr] = $this->user('hr_manager', $hrEmployee);
        $cycleId = $this->cycle($hrEmployee);

        $this->actingAs($hr)->put(route('performance.cycles.update', $cycleId), [
            'cycle_name' => 'Closed Annual Review',
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'closed',
        ])->assertRedirect(route('performance.index'));

        $audit = DB::table('audit_trails')
            ->where('action', 'review_cycle_updated')
            ->where('resource_id', $cycleId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($hrEmployee, $audit->employee_id);
        $this->assertSame((string) $hr->id, (string) $audit->user_id);
        $this->assertSame('active', json_decode($audit->before_state, true)['status']);
        $this->assertSame('closed', json_decode($audit->after_state, true)['status']);
    }

    /**
     * Sharing a department is not authority. The subject here reports to nobody
     * the acting supervisor commands, so the review is refused outright — a
     * supervisor has no exception path, only admin and HR do.
     */
    public function test_a_supervisor_cannot_open_a_review_outside_their_chain(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $colleague = $this->employee($dept, 'colleague@example.org');
        [$supervisor] = $this->user('supervisor', $boss);

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), [
            'employee_id' => $colleague,
            'cycle_id' => $this->cycle($boss),
            'review_type' => 'standard',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('performance_reviews', 0);
    }

    /** HR lost its blanket pass: an employee with a present supervisor is theirs. */
    public function test_hr_cannot_open_a_review_when_the_supervisor_is_available(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        $this->user('supervisor', $boss);
        [$hr] = $this->user('hr_manager', $this->employee($this->department('HR'), 'hr@example.org'));

        $this->actingAs($hr)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $this->cycle($boss),
            'review_type' => 'standard',
            'exception_reason' => 'Covering for the ward.',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('performance_reviews', 0);
    }

    public function test_an_employee_with_no_supervisor_is_an_admin_exception(): void
    {
        $orphan = $this->employee($this->department('ICU'), 'orphan@example.org');
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');
        [$admin] = $this->user('admin', $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $orphan,
            'cycle_id' => $this->cycle($adminEmp),
            'review_type' => 'standard',
            'exception_reason' => 'No reporting line recorded yet.',
        ])->assertRedirect();

        $review = DB::table('performance_reviews')->where('employee_id', $orphan)->first();

        $this->assertTrue((bool) $review->is_exception_review);
        $this->assertSame('no_supervisor', $review->exception_basis);
        $this->assertSame('No reporting line recorded yet.', $review->exception_reason);

        // Logged through the existing audit trail, not a second system.
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'review_exception',
            'resource_type' => 'performance_reviews',
            'resource_id' => $review->review_id,
        ]);
    }

    public function test_an_absent_supervisor_is_an_admin_exception(): void
    {
        $dept = $this->department('ICU');
        $away = $this->employee($dept, 'away@example.org', ['employment_status' => 'on_leave']);
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $away]);
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');
        [$admin] = $this->user('admin', $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $this->cycle($adminEmp),
            'review_type' => 'standard',
            'exception_reason' => 'Head nurse is on leave for the cycle.',
        ])->assertRedirect();

        $this->assertSame('supervisor_unavailable', DB::table('performance_reviews')
            ->where('employee_id', $report)->value('exception_basis'));
    }

    /**
     * A supervisor being reviewed in the same cycle cannot also write their
     * subordinate's review — the conflict is what opens the exception.
     */
    public function test_a_supervisor_under_review_in_the_cycle_is_an_admin_exception(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');
        [$admin] = $this->user('admin', $adminEmp);

        $cycle = $this->cycle($adminEmp);
        $this->review($boss, $cycle, $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $cycle,
            'review_type' => 'standard',
            'exception_reason' => 'Head nurse is a subject of this cycle.',
        ])->assertRedirect();

        $this->assertSame('supervisor_is_subject', DB::table('performance_reviews')
            ->where('employee_id', $report)->value('exception_basis'));
    }

    /** An out-of-chain review without a stated reason is not recordable. */
    public function test_an_exception_review_needs_a_reason(): void
    {
        $orphan = $this->employee($this->department('ICU'), 'orphan@example.org');
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');
        [$admin] = $this->user('admin', $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $orphan,
            'cycle_id' => $this->cycle($adminEmp),
            'review_type' => 'standard',
            'exception_reason' => '   ',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('performance_reviews', 0);
    }

    // ── No self-review, at any level ─────────────────────────

    public function test_nobody_can_open_a_review_on_themselves(): void
    {
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');
        [$admin] = $this->user('admin', $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $adminEmp,
            'cycle_id' => $this->cycle($adminEmp),
            'review_type' => 'standard',
            'exception_reason' => 'Nobody else available.',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('performance_reviews', 0);
    }

    /**
     * A review whose reviewer_id somehow equals its employee_id — legacy data, a
     * direct DB edit — must still not be scoreable.
     */
    public function test_a_self_pointing_review_is_not_scoreable(): void
    {
        $employee = $this->employee($this->department('ICU'), 'both@example.org');
        [$supervisor] = $this->user('supervisor', $employee);

        $review = $this->review($employee, $this->cycle($employee), $employee);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::FINISHED,
        ])->assertForbidden();

        $this->assertNull(DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
    }

    // ── One reviewer, one review ─────────────────────────────

    /** A repeat create is an edit: it lands on the existing record, not a copy. */
    public function test_a_repeat_create_routes_to_the_existing_review(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss);
        $payload = ['employee_id' => $report, 'cycle_id' => $cycle, 'review_type' => 'standard'];

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), $payload);
        $first = DB::table('performance_reviews')->value('review_id');

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), $payload)
            ->assertRedirect(route('performance.reviews.score', $first));

        $this->assertDatabaseCount('performance_reviews', 1);
    }

    /**
     * A different review *type* is no longer a second review. One account holds
     * one opinion of one person in one cycle, so opening a "promotion" review
     * after a "standard" one lands back on the first rather than filing a second
     * verdict from the same voice.
     */
    public function test_a_second_review_type_lands_on_the_same_review(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss);

        foreach (['standard', 'probationary'] as $type) {
            $this->actingAs($supervisor)->post(route('performance.reviews.store'), [
                'employee_id' => $report,
                'cycle_id' => $cycle,
                'review_type' => $type,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('performance_reviews', 1);
        $this->assertSame('standard', DB::table('performance_reviews')->value('review_type'));
    }

    /**
     * Two *different* reviewers on the same employee and cycle are still two
     * reviews — the uniqueness is per account, not per employee, so a covering
     * admin does not overwrite the supervisor's record.
     */
    public function test_a_different_reviewer_gets_their_own_review(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        $adminEmp = $this->employee($this->department('Admin'), 'admin@example.org');

        [$supervisor] = $this->user('supervisor', $boss);
        [$admin] = $this->user('admin', $adminEmp);

        $cycle = $this->cycle($boss);

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $cycle,
            'review_type' => 'standard',
        ])->assertRedirect();

        // The admin is out of chain, so they need a basis — the supervisor is a
        // subject of this cycle once someone reviews them.
        $this->review($boss, $cycle, $adminEmp);

        $this->actingAs($admin)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $cycle,
            'review_type' => 'standard',
            'exception_reason' => 'Head nurse is a subject of this cycle.',
        ])->assertRedirect();

        $this->assertSame(2, DB::table('performance_reviews')
            ->where('employee_id', $report)->where('cycle_id', $cycle)->count());
    }

    // ── Who may score ────────────────────────────────────────

    /**
     * The named reviewer writes `supervisor_score`, and the review keeps the
     * status they chose. There is no other rating column left to protect.
     */
    public function test_the_reviewer_writes_the_score(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 4]],
            'status' => ReviewStatus::DRAFT,
        ])->assertRedirect();

        $row = DB::table('review_kpi_scores')->where('score_id', $score)->first();

        $this->assertSame(4.0, (float) $row->supervisor_score);
        $this->assertSame(4.0, (float) $row->weighted_score, 'weighted_score is the reviewer score, copied');
        $this->assertSame(ReviewStatus::DRAFT, DB::table('performance_reviews')->where('review_id', $review)->value('status'));
    }

    public function test_score_comment_and_status_changes_are_audited_to_the_named_reviewer(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => [
                'supervisor_score' => 4,
                'comments' => 'Consistently meets the medication-safety standard.',
            ]],
            'strengths_text' => 'Reliable clinical practice.',
            'improvements_text' => 'Continue leadership development.',
            'status' => ReviewStatus::FINISHED,
        ])->assertRedirect();

        $audit = DB::table('audit_trails')
            ->where('action', 'review_scores_updated')
            ->where('resource_id', $review)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($boss, $audit->employee_id);
        $this->assertSame((string) $supervisor->id, (string) $audit->user_id);

        $before = json_decode($audit->before_state, true);
        $after = json_decode($audit->after_state, true);

        $this->assertNull($before['kpi_scores'][0]['supervisor_score']);
        $this->assertSame(4.0, (float) $after['kpi_scores'][0]['supervisor_score']);
        $this->assertSame('Consistently meets the medication-safety standard.', $after['kpi_scores'][0]['comments']);
        $this->assertSame(ReviewStatus::DRAFT, $before['review']['status']);
        $this->assertSame(ReviewStatus::FINISHED, $after['review']['status']);
        $this->assertNotNull($after['review']['signed_at']);
    }

    /** The subject of a review has no route into it — they are not a reviewer. */
    public function test_the_subject_cannot_score_their_own_review(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$staff] = $this->user('staff', $report);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        // Blocked by the route's role middleware before the record is even read.
        $this->actingAs($staff)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::FINISHED,
        ])->assertForbidden();

        $this->assertNull(DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
    }

    public function test_the_subject_can_respond_to_a_finished_review_without_changing_scores(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$staff] = $this->user('staff', $report);

        $review = $this->review($report, $this->cycle($boss), $boss, ['status' => ReviewStatus::FINISHED]);
        $score = $this->kpiScore($review, ['supervisor_score' => 3, 'weighted_score' => 3]);

        $this->actingAs($staff)->post(route('performance.reviews.employee-response.store', $review), [
            'employee_response' => 'I request that the medication audit evidence be attached.',
            'scores' => [$score => ['supervisor_score' => 5]],
        ])->assertRedirect();

        $stored = DB::table('performance_reviews')->where('review_id', $review)->first();
        $this->assertSame('I request that the medication audit evidence be attached.', $stored->employee_response);
        $this->assertNotNull($stored->employee_response_submitted_at);
        $this->assertNull($stored->employee_acknowledged_at);
        $this->assertSame(3.0, (float) DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'review_employee_response',
            'resource_id' => $review,
            'employee_id' => $report,
        ]);
    }

    public function test_nobody_else_can_submit_the_employee_response(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);
        $review = $this->review($report, $this->cycle($boss), $boss, ['status' => ReviewStatus::FINISHED]);

        $this->actingAs($supervisor)->post(route('performance.reviews.employee-response.store', $review), [
            'employee_response' => 'Written by the wrong person.',
        ])->assertForbidden();

        $this->assertNull(DB::table('performance_reviews')->where('review_id', $review)->value('employee_response'));
    }

    public function test_a_completed_review_can_be_acknowledged_once_and_then_locks_the_response(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$staff] = $this->user('staff', $report);
        $cycle = $this->cycle($boss, [
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $review = $this->review($report, $cycle, $boss, ['status' => ReviewStatus::FINISHED]);

        $this->actingAs($staff)->post(route('performance.reviews.employee-response.store', $review), [
            'employee_response' => 'I acknowledge receipt and preserve my appeal on staffing levels.',
            'acknowledge' => '1',
        ])->assertRedirect();

        $acknowledgedAt = DB::table('performance_reviews')->where('review_id', $review)->value('employee_acknowledged_at');
        $this->assertNotNull($acknowledgedAt);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'review_acknowledged',
            'resource_id' => $review,
            'employee_id' => $report,
        ]);

        $this->actingAs($staff)->post(route('performance.reviews.employee-response.store', $review), [
            'employee_response' => 'Attempted rewrite after acknowledgement.',
            'acknowledge' => '1',
        ])->assertSessionHas('error');

        $this->assertSame(
            'I acknowledge receipt and preserve my appeal on staffing levels.',
            DB::table('performance_reviews')->where('review_id', $review)->value('employee_response')
        );
    }

    /**
     * `finished` is a signature and stays editable; `draft` withdraws it. The
     * signature is re-stamped on every finish, because a finished review can
     * still change and an old signature would attest to an earlier version.
     */
    public function test_finishing_signs_the_review_and_leaves_it_editable(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 4]],
            'status' => ReviewStatus::FINISHED,
        ])->assertRedirect();

        $stored = DB::table('performance_reviews')->where('review_id', $review)->first();
        $this->assertSame(ReviewStatus::FINISHED, $stored->status);
        $this->assertNotNull($stored->signed_at);
        $this->assertNotNull($stored->digital_signature);

        // Still editable — and moving back to draft withdraws the sign-off with it.
        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::DRAFT,
        ])->assertRedirect();

        $reopened = DB::table('performance_reviews')->where('review_id', $review)->first();
        $this->assertSame(ReviewStatus::DRAFT, $reopened->status);
        $this->assertNull($reopened->signed_at);
        $this->assertNull($reopened->digital_signature);
        $this->assertSame(5.0, (float) DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
    }

    /**
     * `completed` is the cycle's word, not a person's. Posting it is a validation
     * failure rather than a silent no-op, so a hand-crafted request gets told.
     */
    public function test_completed_cannot_be_chosen_by_hand(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 4]],
            'status' => ReviewStatus::COMPLETED,
        ])->assertSessionHasErrors('status');

        $this->assertSame(ReviewStatus::DRAFT, DB::table('performance_reviews')->where('review_id', $review)->value('status'));
    }

    /**
     * Past the cycle's end date the review is evidence. Even its own reviewer is
     * turned away — sent to the read-only show page rather than 403'd, because
     * that page is the thing they came to look at.
     */
    public function test_a_review_freezes_when_its_cycle_ends(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss, [
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'status' => 'closed',
        ]);

        $review = $this->review($report, $cycle, $boss, ['status' => ReviewStatus::FINISHED]);
        $score = $this->kpiScore($review, ['supervisor_score' => 3]);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::DRAFT,
        ])->assertRedirect(route('performance.show', $review));

        $this->assertSame(3.0, (float) DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
        $this->assertSame(ReviewStatus::FINISHED, DB::table('performance_reviews')->where('review_id', $review)->value('status'),
            'the stored status is untouched — the freeze is derived from the date, never written');
    }

    /**
     * The dashboard's "Pending Reviews" tile has to freeze with the review.
     *
     * It used to read `whereNotIn('status', ['completed'])`, which was correct
     * only while something wrote that value. Nothing does any more — completed is
     * derived from the cycle's end date — so the filter excluded nothing and every
     * frozen review in every closed cycle stayed on the tile as outstanding work,
     * permanently, while the review screen showed it as Completed and refused
     * edits. The count joins `review_cycles` now and asks the date.
     */
    public function test_the_dashboard_does_not_count_frozen_reviews_as_pending(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$admin] = $this->user('admin', $boss);

        $live = $this->cycle($boss);
        $ended = $this->cycle($boss, [
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->review($report, $live, $boss);
        $this->review($report, $ended, $boss, ['status' => ReviewStatus::FINISHED]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['pending_reviews'] === 1);
    }

    /** A cycle ending today is still open: it closes at the end of that day. */
    public function test_a_cycle_ending_today_is_still_editable(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss, ['end_date' => now()->toDateString()]);
        $review = $this->review($report, $cycle, $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 4]],
            'status' => ReviewStatus::FINISHED,
        ])->assertRedirect(route('performance.show', $review));

        $this->assertSame(4.0, (float) DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
    }

    /**
     * The end date freezes the review even while the cycle column still says
     * 'active' — which it always does, because nothing ever rewrites it.
     *
     * This is the case that was reported: `review_cycles.status` is hand-set, so
     * a cycle that ended yesterday still reads 'active' until an admin happens to
     * edit it, and the freeze test above passed only because its fixture set the
     * column to 'closed' by hand. Nothing in the app does that. The date is the
     * rule; the column is not consulted.
     */
    public function test_the_end_date_freezes_the_review_even_while_the_cycle_still_says_active(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss, [
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'status' => CycleStatus::ACTIVE,
        ]);

        $review = $this->review($report, $cycle, $boss, ['status' => ReviewStatus::FINISHED]);
        $score = $this->kpiScore($review, ['supervisor_score' => 3]);

        $this->actingAs($supervisor)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::DRAFT,
        ])->assertRedirect(route('performance.show', $review));

        $this->assertSame(3.0, (float) DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'),
            'the score was rewritten after the cycle ended');

        $this->assertSame(CycleStatus::CLOSED, CycleStatus::of(CycleStatus::ACTIVE, now()->subDay()->toDateString()),
            'the cycle reads closed however its column is set');
    }

    /**
     * A review cannot be opened in a cycle that has already ended.
     *
     * It would be born frozen — ReviewStatus reads it as completed the moment it
     * exists — so it is a row nobody could ever score. The create form hides
     * ended cycles, but a POST is not obliged to have used the form.
     */
    public function test_a_review_cannot_be_opened_in_a_cycle_that_has_ended(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $cycle = $this->cycle($boss, [
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'status' => CycleStatus::ACTIVE,
        ]);

        $this->actingAs($supervisor)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $cycle,
            'review_type' => 'standard',
        ])->assertRedirect();

        $this->assertDatabaseCount('performance_reviews', 0);
    }

    /**
     * A supervisor-role account that is not the named reviewer holds no column,
     * however senior — the route middleware lets them in, the record does not.
     */
    public function test_a_supervisor_who_is_not_the_reviewer_cannot_score(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$other] = $this->user('supervisor', $this->employee($dept, 'other@example.org'));

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($other)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::FINISHED,
        ])->assertForbidden();

        $this->assertNull(DB::table('review_kpi_scores')->where('score_id', $score)->value('supervisor_score'));
    }

    /**
     * An account with no linked employee record is nobody's reviewer, so it can
     * neither open a review nor score one — even holding the admin role.
     */
    public function test_an_unlinked_account_cannot_review_at_all(): void
    {
        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$unlinked] = $this->user('admin', null);

        $this->actingAs($unlinked)->post(route('performance.reviews.store'), [
            'employee_id' => $report,
            'cycle_id' => $this->cycle($boss),
            'review_type' => 'standard',
            'exception_reason' => 'Nobody else available.',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('performance_reviews', 0);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $score = $this->kpiScore($review);

        $this->actingAs($unlinked)->put(route('performance.reviews.score.save', $review), [
            'scores' => [$score => ['supervisor_score' => 5]],
            'status' => ReviewStatus::FINISHED,
        ])->assertForbidden();
    }

    /**
     * The scoring screen refuses anyone who is not the named reviewer, so a
     * supervisor cannot even read a colleague's scoring form.
     *
     * @group mysql
     */
    public function test_the_score_screen_is_closed_to_an_uninvolved_supervisor(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$other] = $this->user('supervisor', $this->employee($dept, 'other@example.org'));

        $review = $this->review($report, $this->cycle($boss), $boss);

        $this->actingAs($other)->get(route('performance.reviews.score', $review))->assertForbidden();
    }

    // ── Reading a review ─────────────────────────────────────

    /**
     * `performance.show` stays reachable by every authenticated account; what it
     * returns is own / authored / direct-report only. An HR account with no
     * involvement is not an exception to that.
     *
     * @group mysql
     */
    public function test_show_is_scoped_to_own_authored_and_direct_reports(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);

        $review = $this->review($report, $this->cycle($boss), $boss);

        [$subject] = $this->user('staff', $report);
        [$reviewer] = $this->user('supervisor', $boss);
        [$hr] = $this->user('hr_manager', $this->employee($this->department('HR'), 'hr@example.org'));
        [$stranger] = $this->user('staff', $this->employee($dept, 'stranger@example.org'));

        $this->actingAs($subject)->get(route('performance.show', $review))->assertOk();
        $this->actingAs($reviewer)->get(route('performance.show', $review))->assertOk();
        $this->actingAs($hr)->get(route('performance.show', $review))->assertForbidden();
        $this->actingAs($stranger)->get(route('performance.show', $review))->assertForbidden();
    }

    /**
     * The listing follows the same rule, so no row leaks through the index.
     *
     * @group mysql
     */
    public function test_the_review_listing_shows_only_involved_records(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org', ['last_name' => 'Bossperson']);
        $report = $this->employee($dept, 'report@example.org', ['last_name' => 'Reportsto', 'supervisor_id' => $boss]);
        $stranger = $this->employee($dept, 'stranger@example.org', ['last_name' => 'Uninvolved']);

        $cycle = $this->cycle($boss);
        $this->review($report, $cycle, $boss);
        $this->review($stranger, $cycle, $this->employee($dept, 'someone@example.org'));

        [$supervisor] = $this->user('supervisor', $boss);

        $this->actingAs($supervisor)->get(route('performance.reviews.index'))
            ->assertOk()
            ->assertSee('Reportsto')
            ->assertDontSee('Uninvolved');
    }

    // ── What the two review screens say about the numbers ────

    /**
     * The scoring form states each KPI's share of the final score rather than its
     * raw weight, and no longer carries a "Weighted" column.
     *
     * That column printed the reviewer's own rating under a heading claiming a
     * weighting it never applied — a 4.00 at weight 0.60 displayed 4.00 — so the
     * screen showed the same figure twice and mislabelled one of them. This
     * asserts both halves: the duplicate is gone, and what replaced the bare
     * weight is the percentage the score is actually calculated with.
     *
     * @group mysql
     */
    public function test_the_scoring_form_states_each_kpis_share_instead_of_its_weight(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);

        // 1.00 and 0.60 of a 1.60 total — exactly 62.5% and 37.5%. Printed as 63
        // and 37, not 63 and 38: the two figures sit in one column a reviewer adds
        // up, so they are apportioned to total 100 rather than rounded apart.
        $this->kpiScore($review, ['supervisor_score' => 4], ['weight' => 1.00]);
        $this->kpiScore($review, ['supervisor_score' => 3], ['weight' => 0.60]);

        $this->actingAs($supervisor)->get(route('performance.reviews.score', $review))
            ->assertOk()
            ->assertSee('counts 63% of the final score')
            ->assertSee('counts 37% of the final score')
            ->assertSee('2 of 2 rated')
            ->assertDontSee('Weighted')
            ->assertDontSee('weight 0.6');
    }

    /**
     * An unrated KPI is left out of the roll-up entirely, and the form now says
     * so — with the remaining KPIs' shares grown to fill the gap.
     *
     * cleanScore() has always turned a blank box into a null rather than a zero,
     * which means a half-finished sheet produced a finished-looking Final Score
     * with nothing on screen admitting it. The warning and the rated count are
     * what make that visible.
     *
     * @group mysql
     */
    public function test_the_scoring_form_warns_that_unrated_kpis_are_left_out(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$supervisor] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);

        $this->kpiScore($review, ['supervisor_score' => 4], ['weight' => 1.00]);
        $this->kpiScore($review, [], ['weight' => 1.00]);

        $this->actingAs($supervisor)->get(route('performance.reviews.score', $review))
            ->assertOk()
            ->assertSee('1 of 2 rated')
            ->assertSee('1 KPI is still unrated')
            ->assertSee('does not count as a zero', false)
            // The one rated KPI now carries the whole score, not half of it.
            ->assertSee('counts 100% of the final score')
            ->assertSee('not counted until you rate it');
    }

    /**
     * The review detail page labels its two roll-ups by what each averages, and
     * explains why they differ.
     *
     * "Supervisor Rating" beside "Final Score" read as two names for one thing;
     * nothing on the page said one was a plain mean and the other weighted.
     *
     * @group mysql
     */
    public function test_the_review_page_explains_the_two_roll_ups(): void
    {
        $this->requiresMysql();

        $dept = $this->department('ICU');
        $boss = $this->employee($dept, 'boss@example.org');
        $report = $this->employee($dept, 'report@example.org', ['supervisor_id' => $boss]);
        [$reviewer] = $this->user('supervisor', $boss);

        $review = $this->review($report, $this->cycle($boss), $boss);
        $this->kpiScore($review, ['supervisor_score' => 4, 'weighted_score' => 4], ['weight' => 1.00]);

        $this->actingAs($reviewer)->get(route('performance.show', $review))
            ->assertOk()
            ->assertSee('Average of KPI ratings')
            ->assertSee('weighted by KPI importance')
            ->assertSee('Share of final score')
            ->assertSee('1 of 1 rated')
            ->assertDontSee('Supervisor Rating')
            ->assertDontSee('Weighted');
    }

    // ── Fixtures ─────────────────────────────────────────────

    /** @return array{0: User} */
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
    private function role(): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => 'Staff Nurse '.Str::random(4),
            'role_slug' => 'staff-nurse-'.Str::lower(Str::random(6)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(string $departmentId, string $email, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(6)),
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $this->role(),
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    /** review_cycles.created_by is NOT NULL, so a cycle needs an author. */
    private function cycle(string $createdBy, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('review_cycles')->insert(array_merge([
            'cycle_id' => $id,
            'cycle_name' => 'Cycle '.Str::random(4),
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    /** A review in the state storeReview() leaves behind. */
    private function review(string $employeeId, string $cycleId, ?string $reviewerId, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('performance_reviews')->insert(array_merge([
            'review_id' => $id,
            'employee_id' => $employeeId,
            'cycle_id' => $cycleId,
            'reviewer_id' => $reviewerId,
            'review_type' => 'standard',
            'status' => ReviewStatus::DRAFT,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    /**
     * An attached KPI row, unscored unless the caller says otherwise.
     *
     * `$kpiOverrides` reaches the library row behind it — the weight lives there,
     * not on the score, and the share the review screens print is a ratio between
     * two of them, so a test about shares needs to set them unequal.
     */
    private function kpiScore(string $reviewId, array $overrides = [], array $kpiOverrides = []): string
    {
        $kpiId = (string) Str::uuid();
        DB::table('kpi_library')->insert(array_merge([
            'kpi_id' => $kpiId,
            'kpi_name' => 'Hand Hygiene '.Str::random(4),
            'kpi_category' => 'quality',
            'weight' => 1.00,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], $kpiOverrides));

        $id = (string) Str::uuid();
        DB::table('review_kpi_scores')->insert(array_merge([
            'score_id' => $id,
            'review_id' => $reviewId,
            'kpi_id' => $kpiId,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }
}
