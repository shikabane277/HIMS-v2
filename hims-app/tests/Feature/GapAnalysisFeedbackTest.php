<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Models\User;
use App\Services\Ai\NullAiProvider;
use App\Services\CompetencyGapAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The gap analysis must send the AI the words a supervisor wrote, not just the
 * numbers they scored.
 *
 * THE BUG THIS LOCKS SHUT: performance_reviews.strengths_text,
 * improvements_text and review_kpi_scores.comments were all being selected by
 * performanceSignal() and then never interpolated into the prompt. The queries
 * looked correct, every existing test passed, and the model was being asked for
 * "evidence" and "root_causes" while holding three numbers and a cycle name. A
 * column that is fetched and dropped is invisible to any test that only checks
 * the query, so these tests assert against the prompt string itself.
 *
 * Uses MySQL-only SQL — roleRequirements() calls GREATEST(), which sqlite has
 * no function for, and the query runs whether or not it matches any rows.
 *
 * @group mysql
 */
class GapAnalysisFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private string $employeeId;

    private string $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('CompetencyGapAnalysisService uses GREATEST(), which requires MySQL.');
        }

        $departmentId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $departmentId,
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

        $this->employeeId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $this->employeeId,
            'employee_code' => 'EMP-FEEDBACK',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.org',
            'department_id' => $departmentId,
            'role_id' => $roleId,
            'position_title' => 'Staff Nurse',
            'hire_date' => '2022-03-01',
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cycleId = $this->cycle('2026 Annual', '2026-01-01', '2026-06-30');
    }

    private function cycle(string $name, string $start, string $end): string
    {
        $cycleId = (string) Str::uuid();

        DB::table('review_cycles')->insert([
            'cycle_id' => $cycleId,
            'cycle_name' => $name,
            'cycle_type' => 'annual',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'closed',
            'created_by' => $this->employeeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cycleId;
    }

    private function review(string $cycleId, array $overrides = []): string
    {
        $reviewId = (string) Str::uuid();

        DB::table('performance_reviews')->insert(array_merge([
            'review_id' => $reviewId,
            'employee_id' => $this->employeeId,
            'cycle_id' => $cycleId,
            'review_type' => 'standard',
            'status' => 'finished',
            'supervisor_rating' => 3.40,
            'overall_score' => 3.28,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $reviewId;
    }

    private function kpiScore(string $reviewId, string $kpiName, float $score, ?string $comment): void
    {
        $kpiId = (string) Str::uuid();

        DB::table('kpi_library')->insert([
            'kpi_id' => $kpiId,
            'kpi_name' => $kpiName,
            'kpi_category' => 'clinical',
            'weight' => 1.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('review_kpi_scores')->insert([
            'score_id' => (string) Str::uuid(),
            'review_id' => $reviewId,
            'kpi_id' => $kpiId,
            'supervisor_score' => $score,
            'weighted_score' => $score,
            'comments' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Swap the provider for one that records the prompt it was handed, then run
     * the analysis and hand the prompt back.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function runAnalysis(): array
    {
        $spy = new class implements AiProvider
        {
            public string $prompt = '';

            public function ask(string $prompt, array $history = [], ?string $scope = null): string
            {
                $this->prompt = $prompt;

                return '{"headline":"ok"}';
            }
        };

        $this->app->instance(AiProvider::class, $spy);

        $result = $this->app->make(CompetencyGapAnalysisService::class)
            ->analyseEmployee($this->employeeId, withAi: true);

        return [$spy->prompt, $result];
    }

    public function test_the_written_narrative_reaches_the_prompt(): void
    {
        $this->review($this->cycleId, [
            'strengths_text' => 'Calm and methodical during the code blue in March.',
            'improvements_text' => 'Charting is consistently late by the end of a night shift.',
        ]);

        [$prompt] = $this->runAnalysis();

        $this->assertStringContainsString('Calm and methodical during the code blue in March.', $prompt);
        $this->assertStringContainsString('Charting is consistently late', $prompt);
    }

    public function test_a_per_kpi_comment_reaches_the_prompt(): void
    {
        $reviewId = $this->review($this->cycleId);
        $this->kpiScore($reviewId, 'Medication Administration', 2.50,
            'Struggles with the new infusion pump interface, not with dosing knowledge.');

        [$prompt] = $this->runAnalysis();

        $this->assertStringContainsString('Medication Administration', $prompt);
        $this->assertStringContainsString('infusion pump interface, not with dosing knowledge', $prompt);
        $this->assertStringContainsString('rated 2.50/5', $prompt);
    }

    public function test_a_comment_on_a_strong_kpi_reaches_the_prompt_too(): void
    {
        // Praise is evidence: the analysis is asked to report strengths, and the
        // weak-KPI list (< 3.5) would never surface this one.
        $reviewId = $this->review($this->cycleId);
        $this->kpiScore($reviewId, 'Patient Communication', 4.75,
            'Families ask for her by name; explains discharge instructions clearly.');

        [$prompt, $result] = $this->runAnalysis();

        $this->assertCount(0, $result['performance']['weak_kpis']);
        $this->assertStringContainsString('Families ask for her by name', $prompt);
    }

    public function test_comments_from_an_earlier_cycle_reach_the_prompt(): void
    {
        // weak_kpis is latest-review-only; the feedback digest spans the window,
        // which is what lets the model say a theme has persisted or improved.
        // The only test that needs a second cycle, so it makes its own.
        $olderCycleId = $this->cycle('2025 Annual', '2025-01-01', '2025-06-30');
        $older = $this->review($olderCycleId, ['improvements_text' => 'Hesitant with IV cannulation.']);
        $this->kpiScore($older, 'IV Therapy', 2.00, 'Needed supervision on every attempt.');

        $this->review($this->cycleId, ['improvements_text' => 'IV cannulation much improved.']);

        [$prompt] = $this->runAnalysis();

        $this->assertStringContainsString('2025 Annual', $prompt);
        $this->assertStringContainsString('Needed supervision on every attempt.', $prompt);
        $this->assertStringContainsString('IV cannulation much improved.', $prompt);
    }

    public function test_every_comment_is_counted_for_the_page_and_the_summary(): void
    {
        $reviewId = $this->review($this->cycleId, [
            'strengths_text' => 'Reliable.',
            'improvements_text' => 'Charting.',
        ]);
        $this->kpiScore($reviewId, 'Hand Hygiene', 4.00, 'Consistently correct.');

        [, $result] = $this->runAnalysis();

        $this->assertSame(3, $result['summary']['written_feedback']);
        $this->assertSame('Reliable.', $result['performance']['feedback'][0]['strengths']);
        $this->assertSame('Consistently correct.', $result['performance']['feedback'][0]['kpi_comments'][0]['comment']);
    }

    public function test_the_model_is_asked_to_summarise_the_feedback(): void
    {
        $reviewId = $this->review($this->cycleId, ['strengths_text' => 'Reliable.']);
        $this->kpiScore($reviewId, 'Hand Hygiene', 4.00, 'Consistently correct.');

        [$prompt] = $this->runAnalysis();

        $this->assertStringContainsString('WRITTEN REVIEW FEEDBACK', $prompt);
        $this->assertStringContainsString('"feedback_summary"', $prompt);
        $this->assertStringContainsString('recurring_themes', $prompt);
    }

    public function test_an_absence_of_feedback_is_stated_not_left_blank(): void
    {
        $reviewId = $this->review($this->cycleId);
        $this->kpiScore($reviewId, 'Hand Hygiene', 4.00, null);

        [$prompt, $result] = $this->runAnalysis();

        $this->assertSame(0, $result['summary']['written_feedback']);
        $this->assertStringContainsString('not one of them carries any written comment', $prompt);
    }

    public function test_an_employee_with_no_reviews_says_so(): void
    {
        [$prompt, $result] = $this->runAnalysis();

        $this->assertSame(0, $result['summary']['written_feedback']);
        $this->assertSame([], $result['performance']['feedback']);
        $this->assertStringContainsString('No performance reviews on record', $prompt);
    }

    public function test_the_page_renders_the_feedback_it_summarised(): void
    {
        $reviewId = $this->review($this->cycleId, ['strengths_text' => 'Calm under pressure.']);
        $this->kpiScore($reviewId, 'Medication Administration', 2.50, 'Infusion pump interface, not dosing.');

        $user = DB::table('users')->insertGetId([
            'name' => 'HR Manager',
            'email' => 'hr@example.org',
            'password' => bcrypt('password'),
            'role' => 'hr_manager',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NullAiProvider is the production "AI unavailable" driver — it already
        // returns the ⚠️-prefixed string parseAiJson() keys on, so there is no
        // reason for this test to hand-roll a second one.
        $this->app->instance(AiProvider::class, new NullAiProvider('none'));

        $response = $this->actingAs(User::find($user))
            ->get(route('competency.gap.employee', $this->employeeId));

        $response->assertOk();
        // Deterministic section: present even with the AI layer unavailable.
        $response->assertSee('Written Feedback on Record');
        $response->assertSee('Calm under pressure.');
        $response->assertSee('Infusion pump interface, not dosing.');
    }
}
