<?php

namespace App\Services;

use App\Contracts\AiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AI-Assisted Competency Gap Analysis.
 *
 * Combines three signals the hospital already records — performance review
 * scores, competency assessments measured against the requirements of the
 * employee's job role, and the training actually received — then asks the
 * configured AI provider to turn that evidence into missing-skill findings and
 * development suggestions.
 *
 * The deterministic analysis stands on its own: every gap, priority and
 * recommended action below is computed from the database. The AI layer adds a
 * narrative and sequencing on top, so the feature still works (and still tells
 * the truth) when no API key is configured.
 */
class CompetencyGapAnalysisService
{
    public function __construct(private AiProvider $ai) {}

    /**
     * Full analysis for one employee.
     *
     * @return array<string, mixed>
     */
    public function analyseEmployee(string $employeeId, bool $withAi = true): array
    {
        $employee = $this->employeeProfile($employeeId);

        if (! $employee) {
            return ['error' => 'Employee not found.'];
        }

        $requirements = $this->roleRequirements($employee);
        $assessments  = $this->assessments($employeeId);
        $performance  = $this->performanceSignal($employeeId);
        $training     = $this->trainingReceived($employeeId);
        $credentials  = $this->credentialRisks($employeeId);

        $gaps = $this->computeGaps($requirements, $assessments, $training);

        $recommendations = $this->recommendedCourses($gaps, $employee, $training);

        $summary = [
            'competencies_required'  => $requirements->count(),
            'competencies_assessed'  => $assessments->count(),
            'unassessed'             => $gaps->where('status', 'unassessed')->count(),
            'critical_gaps'          => $gaps->where('severity', 'critical')->count(),
            'moderate_gaps'          => $gaps->where('severity', 'moderate')->count(),
            'met'                    => $gaps->where('status', 'met')->count(),
            'readiness_pct'          => $this->readinessPercentage($gaps),
            'cpd_hours_last_year'    => $training['cpd_hours_last_year'],
            'trainings_attended'     => $training['sessions_attended'],
            'courses_completed'      => $training['courses_completed'],
            'latest_overall_score'   => $performance['latest_overall_score'],
        ];

        $result = [
            'employee'        => $employee,
            'summary'         => $summary,
            'gaps'            => $gaps->values()->all(),
            'performance'     => $performance,
            'training'        => $training,
            'credentials'     => $credentials,
            'recommendations' => $recommendations,
            'ai'              => null,
            'generated_at'    => now(),
        ];

        if ($withAi) {
            $result['ai'] = $this->aiNarrative($result);
        }

        return $result;
    }

    /**
     * Department-level rollup: which competencies are weakest across a team.
     *
     * @return array<string, mixed>
     */
    public function analyseDepartment(?string $departmentId = null, bool $withAi = true): array
    {
        $employees = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->where('e.employment_status', 'active')
            ->select('e.employee_id', 'e.first_name', 'e.last_name', 'e.role_id', 'e.position_title', 'd.name as department_name')
            ->orderBy('e.first_name')
            ->get();

        $weakest = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->join('employees as e', 'ca.employee_id', '=', 'e.employee_id')
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->select(
                'c.competency_id',
                'c.competency_name',
                'c.required_proficiency',
                'c.is_mandatory',
                DB::raw('COUNT(DISTINCT ca.employee_id) as assessed_employees'),
                DB::raw('ROUND(AVG(ca.current_proficiency), 2) as avg_proficiency'),
                DB::raw('ROUND(AVG(ca.gap), 2) as avg_gap'),
                DB::raw('SUM(CASE WHEN ca.gap < 0 THEN 1 ELSE 0 END) as employees_below')
            )
            ->groupBy('c.competency_id', 'c.competency_name', 'c.required_proficiency', 'c.is_mandatory')
            ->havingRaw('AVG(ca.gap) < 0')
            ->orderBy('avg_gap')
            ->limit(15)
            ->get();

        $department = $departmentId
            ? DB::table('departments')->where('department_id', $departmentId)->first()
            : null;

        $result = [
            'department'      => $department,
            'headcount'       => $employees->count(),
            'weakest'         => $weakest,
            'training_demand' => $this->trainingDemand($weakest),
            'ai'              => null,
            'generated_at'    => now(),
        ];

        if ($withAi) {
            $result['ai'] = $this->aiDepartmentNarrative($result);
        }

        return $result;
    }

    /* ─────────────────────────── data gathering ─────────────────────────── */

    private function employeeProfile(string $employeeId): ?object
    {
        return DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->where('e.employee_id', $employeeId)
            ->select(
                'e.employee_id', 'e.employee_code', 'e.first_name', 'e.last_name',
                'e.position_title', 'e.hire_date', 'e.employment_status', 'e.role_id',
                'd.department_id', 'd.name as department_name',
                'r.role_name'
            )
            ->first();
    }

    /**
     * What the employee's job role demands. role_competency_requirements is the
     * authoritative source; competencies flagged is_mandatory apply hospital-wide
     * and are folded in so mandatory items are never missed.
     */
    private function roleRequirements(object $employee): Collection
    {
        $roleSpecific = DB::table('role_competency_requirements as rcr')
            ->join('competencies as c', 'rcr.competency_id', '=', 'c.competency_id')
            ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->leftJoin('competency_domains as cd', 'cc.domain_id', '=', 'cd.domain_id')
            ->where('rcr.role_id', $employee->role_id)
            ->select(
                'c.competency_id', 'c.competency_name', 'c.competency_code', 'c.description',
                'cc.category_name', 'cd.domain_name',
                DB::raw('GREATEST(rcr.minimum_proficiency, c.required_proficiency) as required_proficiency'),
                'rcr.is_critical',
                'c.is_mandatory'
            )
            ->get();

        $mandatory = DB::table('competencies as c')
            ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->leftJoin('competency_domains as cd', 'cc.domain_id', '=', 'cd.domain_id')
            ->where('c.is_mandatory', true)
            ->whereNotIn('c.competency_id', $roleSpecific->pluck('competency_id')->all() ?: [''])
            ->select(
                'c.competency_id', 'c.competency_name', 'c.competency_code', 'c.description',
                'cc.category_name', 'cd.domain_name',
                'c.required_proficiency',
                DB::raw('0 as is_critical'),
                'c.is_mandatory'
            )
            ->get();

        return $roleSpecific->concat($mandatory)->keyBy('competency_id');
    }

    /**
     * Most recent assessment per competency.
     */
    private function assessments(string $employeeId): Collection
    {
        return DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->where('ca.employee_id', $employeeId)
            ->select(
                'ca.competency_id', 'ca.current_proficiency', 'ca.gap',
                'ca.assessed_date', 'ca.assessment_method', 'ca.notes',
                'ca.next_assessment_due', 'c.competency_name'
            )
            ->orderByDesc('ca.assessed_date')
            ->get()
            ->unique('competency_id')
            ->keyBy('competency_id');
    }

    private function performanceSignal(string $employeeId): array
    {
        $reviews = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where('pr.employee_id', $employeeId)
            ->select('pr.review_id', 'pr.status', 'pr.overall_score', 'pr.self_rating',
                     'pr.supervisor_rating', 'pr.peer_rating', 'pr.strengths_text',
                     'pr.improvements_text', 'rc.cycle_name', 'rc.end_date')
            ->orderByDesc('rc.end_date')
            ->limit(3)
            ->get();

        $latest = $reviews->first();

        $weakKpis = $latest
            ? DB::table('review_kpi_scores as rks')
                ->join('kpi_library as k', 'rks.kpi_id', '=', 'k.kpi_id')
                ->where('rks.review_id', $latest->review_id)
                ->whereNotNull('rks.weighted_score')
                ->where('rks.weighted_score', '<', 3.5)
                ->select('k.kpi_name', 'k.kpi_category', 'rks.weighted_score', 'rks.comments')
                ->orderBy('rks.weighted_score')
                ->get()
            : collect();

        return [
            'reviews'              => $reviews,
            'latest_overall_score' => $latest->overall_score ?? null,
            'latest_cycle'         => $latest->cycle_name ?? null,
            'weak_kpis'            => $weakKpis,
            'strengths_text'       => $latest->strengths_text ?? null,
            'improvements_text'    => $latest->improvements_text ?? null,
        ];
    }

    /**
     * Training actually received — completed courses, attended sessions, CPD.
     * linked_competencies on a session tells us which gaps a session addressed.
     */
    private function trainingReceived(string $employeeId): array
    {
        $courses = DB::table('course_enrollments as ce')
            ->join('courses as c', 'ce.course_id', '=', 'c.course_id')
            ->where('ce.employee_id', $employeeId)
            ->select('c.course_id', 'c.title', 'c.category', 'c.cpd_hours',
                     'ce.status', 'ce.progress_pct', 'ce.completed_at', 'ce.cpd_hours_earned')
            ->orderByDesc('ce.completed_at')
            ->get();

        $sessions = DB::table('training_registrations as tr')
            ->join('training_sessions as ts', 'tr.session_id', '=', 'ts.session_id')
            ->where('tr.employee_id', $employeeId)
            ->select('ts.session_id', 'ts.title', 'ts.category', 'ts.session_date',
                     'ts.cpd_hours', 'ts.linked_competencies', 'ts.linked_course_id', 'tr.status')
            ->orderByDesc('ts.session_date')
            ->get();

        $cpdLastYear = (float) DB::table('cpd_records')
            ->where('employee_id', $employeeId)
            ->whereDate('date_earned', '>=', now()->subYear()->toDateString())
            ->sum('cpd_hours');

        // Which competencies has training already touched?
        $competenciesTrained = [];
        foreach ($sessions->where('status', 'attended') as $session) {
            foreach ($this->decodeJsonList($session->linked_competencies) as $competencyId) {
                $competenciesTrained[$competencyId] = $session->title;
            }
        }

        return [
            'courses'               => $courses,
            'sessions'              => $sessions,
            'courses_completed'     => $courses->where('status', 'completed')->count(),
            'courses_in_progress'   => $courses->whereIn('status', ['enrolled', 'in_progress'])->count(),
            'sessions_attended'     => $sessions->where('status', 'attended')->count(),
            'cpd_hours_last_year'   => round($cpdLastYear, 1),
            'competencies_trained'  => $competenciesTrained,
        ];
    }

    private function credentialRisks(string $employeeId): Collection
    {
        return DB::table('employee_credentials')
            ->where('employee_id', $employeeId)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())
            ->select('credential_id', 'credential_type', 'credential_number',
                     'issuing_body', 'expiry_date', 'verified_at')
            ->orderBy('expiry_date')
            ->get();
    }

    /* ───────────────────────────── the analysis ───────────────────────────── */

    /**
     * Compare requirement vs. latest assessment for every required competency.
     */
    private function computeGaps(Collection $requirements, Collection $assessments, array $training): Collection
    {
        return $requirements->map(function ($req) use ($assessments, $training) {
            $assessment = $assessments->get($req->competency_id);
            $required   = (int) $req->required_proficiency;

            if (! $assessment) {
                return [
                    'competency_id'    => $req->competency_id,
                    'competency_name'  => $req->competency_name,
                    'competency_code'  => $req->competency_code,
                    'domain'           => $req->domain_name,
                    'category'         => $req->category_name,
                    'required'         => $required,
                    'current'          => null,
                    'gap'              => null,
                    'status'           => 'unassessed',
                    'severity'         => $req->is_critical || $req->is_mandatory ? 'critical' : 'moderate',
                    'is_critical'      => (bool) $req->is_critical,
                    'is_mandatory'     => (bool) $req->is_mandatory,
                    'trained_by'       => $training['competencies_trained'][$req->competency_id] ?? null,
                    'assessed_date'    => null,
                    'priority'         => $req->is_critical || $req->is_mandatory ? 90 : 60,
                    'note'             => 'No assessment on record — proficiency is unknown.',
                ];
            }

            $current = (int) $assessment->current_proficiency;
            $gap     = $current - $required;

            $status   = $gap >= 0 ? 'met' : 'below';
            $severity = match (true) {
                $gap >= 0                            => 'none',
                $gap <= -2                           => 'critical',
                ($req->is_critical || $req->is_mandatory) => 'critical',
                default                              => 'moderate',
            };

            // A gap that persists despite training is a stronger signal than an
            // untrained gap, so it outranks it.
            $trainedBy = $training['competencies_trained'][$req->competency_id] ?? null;

            $priority = 0;
            if ($gap < 0) {
                $priority = min(100, abs($gap) * 25
                    + (($req->is_critical || $req->is_mandatory) ? 30 : 0)
                    + ($trainedBy ? 15 : 0));
            }

            return [
                'competency_id'    => $req->competency_id,
                'competency_name'  => $req->competency_name,
                'competency_code'  => $req->competency_code,
                'domain'           => $req->domain_name,
                'category'         => $req->category_name,
                'required'         => $required,
                'current'          => $current,
                'gap'              => $gap,
                'status'           => $status,
                'severity'         => $severity,
                'is_critical'      => (bool) $req->is_critical,
                'is_mandatory'     => (bool) $req->is_mandatory,
                'trained_by'       => $trainedBy,
                'assessed_date'    => $assessment->assessed_date,
                'priority'         => $priority,
                'note'             => $gap >= 0
                    ? 'Meets requirement.'
                    : ($trainedBy
                        ? "Still below requirement after attending \"{$trainedBy}\" — consider a different intervention."
                        : 'Below requirement, no related training recorded.'),
            ];
        })
        ->sortByDesc('priority');
    }

    private function readinessPercentage(Collection $gaps): int
    {
        if ($gaps->isEmpty()) {
            return 0;
        }

        return (int) round($gaps->where('status', 'met')->count() / $gaps->count() * 100);
    }

    /**
     * Map open gaps onto courses the hospital already offers.
     */
    private function recommendedCourses(Collection $gaps, object $employee, array $training): Collection
    {
        $openGaps = $gaps->whereIn('status', ['below', 'unassessed']);

        if ($openGaps->isEmpty()) {
            return collect();
        }

        $completedCourseIds = collect($training['courses'])
            ->where('status', 'completed')
            ->pluck('course_id')
            ->all();

        // Sessions explicitly tagged with a gap competency are the best match.
        $gapCompetencyIds = $openGaps->pluck('competency_id')->all();

        $sessions = DB::table('training_sessions')
            ->whereNotNull('linked_competencies')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->select('session_id', 'title', 'session_date', 'linked_competencies', 'cpd_hours', 'category')
            ->get()
            ->filter(function ($session) use ($gapCompetencyIds) {
                return array_intersect($this->decodeJsonList($session->linked_competencies), $gapCompetencyIds) !== [];
            })
            ->map(fn ($s) => [
                'type'       => 'training_session',
                'id'         => $s->session_id,
                'title'      => $s->title,
                'detail'     => 'Scheduled '.$s->session_date,
                'cpd_hours'  => $s->cpd_hours,
                'reason'     => 'Session is tagged to one of the open gap competencies.',
            ]);

        // Then pathways aimed at this employee's role.
        $pathwayCourses = DB::table('learning_pathways as lp')
            ->join('pathway_courses as pc', 'lp.pathway_id', '=', 'pc.pathway_id')
            ->join('courses as c', 'pc.course_id', '=', 'c.course_id')
            ->where('c.is_active', true)
            ->whereNotIn('c.course_id', $completedCourseIds ?: [''])
            ->select('c.course_id', 'c.title', 'c.category', 'c.cpd_hours',
                     'lp.pathway_name', 'lp.target_roles', 'pc.sequence_order')
            ->orderBy('pc.sequence_order')
            ->get()
            ->filter(function ($row) use ($employee) {
                $targets = $this->decodeJsonList($row->target_roles);

                return $targets === [] || in_array($employee->role_id, $targets, true);
            })
            ->map(fn ($row) => [
                'type'      => 'course',
                'id'        => $row->course_id,
                'title'     => $row->title,
                'detail'    => 'From pathway: '.$row->pathway_name,
                'cpd_hours' => $row->cpd_hours,
                'reason'    => 'Part of a learning pathway targeting this role.',
            ]);

        // Finally, uncompleted mandatory courses.
        $mandatory = DB::table('courses')
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->whereNotIn('course_id', $completedCourseIds ?: [''])
            ->select('course_id', 'title', 'category', 'cpd_hours')
            ->get()
            ->map(fn ($c) => [
                'type'      => 'course',
                'id'        => $c->course_id,
                'title'     => $c->title,
                'detail'    => 'Mandatory course, not yet completed',
                'cpd_hours' => $c->cpd_hours,
                'reason'    => 'Hospital-wide mandatory training still outstanding.',
            ]);

        return $sessions->concat($pathwayCourses)->concat($mandatory)->unique('id')->take(10)->values();
    }

    /**
     * Aggregate training need across a department's weakest competencies.
     */
    private function trainingDemand(Collection $weakest): Collection
    {
        if ($weakest->isEmpty()) {
            return collect();
        }

        return $weakest->take(5)->map(fn ($row) => [
            'competency_name'  => $row->competency_name,
            'employees_below'  => (int) $row->employees_below,
            'avg_gap'          => (float) $row->avg_gap,
            'suggested_format' => abs((float) $row->avg_gap) >= 2
                ? 'Instructor-led workshop with supervised practice'
                : 'Short refresher module plus reassessment',
        ]);
    }

    /* ───────────────────────────── the AI layer ───────────────────────────── */

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    private function aiNarrative(array $analysis): ?array
    {
        $prompt = $this->buildEmployeePrompt($analysis);
        $raw    = $this->ai->ask($prompt);

        return $this->parseAiJson($raw);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    private function aiDepartmentNarrative(array $analysis): ?array
    {
        if ($analysis['weakest']->isEmpty()) {
            return null;
        }

        $lines = $analysis['weakest']->take(10)->map(fn ($r) => sprintf(
            '- %s: avg proficiency %.2f vs required %d (avg gap %.2f); %d of %d assessed staff below requirement',
            $r->competency_name,
            (float) $r->avg_proficiency,
            (int) $r->required_proficiency,
            (float) $r->avg_gap,
            (int) $r->employees_below,
            (int) $r->assessed_employees
        ))->implode("\n");

        $deptName = $analysis['department']->name ?? 'the hospital';

        $prompt = <<<PROMPT
You are a hospital workforce development analyst. Below are the weakest measured
competencies for {$deptName} ({$analysis['headcount']} active staff).

{$lines}

Return ONLY a JSON object, no markdown fences, with this shape:
{
  "headline": "one sentence stating the single most important workforce risk",
  "themes": [{"theme": "short label", "explanation": "2 sentences", "competencies": ["name", "..."]}],
  "recommended_actions": [{"action": "concrete step", "rationale": "why", "timeframe": "e.g. next 30 days", "owner": "e.g. Nurse Educator"}],
  "patient_safety_note": "one sentence on any patient-safety implication, or null"
}
Be specific to the competencies listed. Do not invent data that is not shown.
PROMPT;

        return $this->parseAiJson($this->ai->ask($prompt));
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function buildEmployeePrompt(array $analysis): string
    {
        $e       = $analysis['employee'];
        $summary = $analysis['summary'];
        $gaps    = collect($analysis['gaps']);

        $gapLines = $gaps->whereIn('status', ['below', 'unassessed'])->take(12)->map(function ($g) {
            if ($g['status'] === 'unassessed') {
                return sprintf('- %s (requires level %d): NEVER ASSESSED%s',
                    $g['competency_name'], $g['required'], $g['is_mandatory'] ? ' [MANDATORY]' : '');
            }

            return sprintf('- %s: at level %d, requires %d (gap %d)%s%s',
                $g['competency_name'], $g['current'], $g['required'], $g['gap'],
                $g['is_critical'] || $g['is_mandatory'] ? ' [CRITICAL]' : '',
                $g['trained_by'] ? ' — already attended: '.$g['trained_by'] : ' — no related training');
        })->implode("\n");

        $gapLines = $gapLines ?: '- No open competency gaps.';

        $weakKpis = collect($analysis['performance']['weak_kpis'])
            ->map(fn ($k) => sprintf('- %s (%s): %.2f/5', $k->kpi_name, $k->kpi_category, (float) $k->weighted_score))
            ->implode("\n") ?: '- No weak KPIs recorded.';

        $trainingLines = collect($analysis['training']['courses'])
            ->take(10)
            ->map(fn ($c) => sprintf('- %s [%s] status=%s progress=%d%%', $c->title, $c->category, $c->status, (int) $c->progress_pct))
            ->implode("\n") ?: '- No course enrolments on record.';

        $credentialLines = collect($analysis['credentials'])
            ->map(fn ($c) => sprintf('- %s expires %s', $c->credential_type, $c->expiry_date))
            ->implode("\n") ?: '- No credentials expiring within 90 days.';

        $score = $summary['latest_overall_score'] !== null
            ? number_format((float) $summary['latest_overall_score'], 2).'/5'
            : 'no completed review';

        return <<<PROMPT
You are a hospital competency development advisor for a Philippine hospital.
Analyse the evidence below and identify this employee's missing skills and how
to close them. Ground every statement in the data given; do not invent facts.

EMPLOYEE
Name: {$e->first_name} {$e->last_name}
Position: {$e->position_title}
Job role: {$e->role_name}
Department: {$e->department_name}
Hired: {$e->hire_date}

PERFORMANCE
Latest overall review score: {$score} (cycle: {$analysis['performance']['latest_cycle']})
Weakest KPIs:
{$weakKpis}

COMPETENCY POSITION VS JOB REQUIREMENTS
Required competencies: {$summary['competencies_required']}
Assessed: {$summary['competencies_assessed']}, never assessed: {$summary['unassessed']}
Critical gaps: {$summary['critical_gaps']}, moderate gaps: {$summary['moderate_gaps']}
Requirement readiness: {$summary['readiness_pct']}%
Open gaps:
{$gapLines}

TRAINING RECEIVED
Courses completed: {$summary['courses_completed']}, sessions attended: {$summary['trainings_attended']}
CPD hours in the last 12 months: {$summary['cpd_hours_last_year']}
{$trainingLines}

CREDENTIALS AT RISK
{$credentialLines}

Return ONLY a JSON object, no markdown fences, with this exact shape:
{
  "headline": "one sentence overall assessment",
  "missing_skills": [
    {"skill": "name", "evidence": "which data point shows this", "impact": "consequence if unaddressed", "severity": "critical|moderate|low"}
  ],
  "root_causes": ["short phrase", "..."],
  "development_plan": [
    {"step": "specific action", "method": "training|mentoring|supervised practice|reassessment|e-learning", "timeframe": "e.g. within 30 days", "success_measure": "how to verify"}
  ],
  "training_already_tried": "note any gap that persisted despite training, or null",
  "strengths_to_leverage": ["short phrase", "..."]
}
Where a competency was never assessed, say assessment is the first step rather
than assuming weakness. Keep the whole response under 500 words.
PROMPT;
    }

    /**
     * The model is asked for bare JSON but may still wrap it in fences or prose.
     *
     * @return array<string, mixed>|null
     */
    private function parseAiJson(string $raw): ?array
    {
        // The service returns human-readable warnings prefixed with a warning
        // sign when the key is missing or the call failed.
        if ($raw === '' || str_starts_with(trim($raw), '⚠️')) {
            return ['unavailable' => true, 'message' => trim($raw) ?: 'AI analysis unavailable.'];
        }

        $cleaned = trim(preg_replace('/```(?:json)?|```/', '', $raw) ?? '');

        $decoded = json_decode($cleaned, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the outermost {...} block if the model added prose.
        if (preg_match('/\{.*\}/s', $cleaned, $m)) {
            $decoded = json_decode($m[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['unavailable' => true, 'message' => 'AI returned an unparseable response.', 'raw' => mb_substr($cleaned, 0, 800)];
    }

    /**
     * JSON columns arrive as strings from the query builder and may hold either
     * a bare list or a JSON-encoded string.
     *
     * @return array<int, string>
     */
    private function decodeJsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : [];
    }
}
