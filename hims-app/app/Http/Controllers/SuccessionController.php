<?php

namespace App\Http\Controllers;

use App\Services\RenewalCycleService;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuccessionController extends Controller
{
    private function canSeeConfidentialSuccessionData(): bool
    {
        return auth()->user()?->hasRole('admin', 'hr_manager') ?? false;
    }

    private function scopeToRelevantCandidates($query, string $employeeAlias = 'e')
    {
        $user = auth()->user();

        if (! $user || $user->seesWholeOrganisation()) {
            return $query;
        }

        return $query->where($employeeAlias.'.supervisor_id', $user->employee_id ?? '');
    }

    private function scopeToRelevantPositions($query, string $positionAlias = 'cp')
    {
        $user = auth()->user();

        if (! $user || $user->seesWholeOrganisation()) {
            return $query;
        }

        $employeeId = $user->employee_id ?? '';

        return $query->whereExists(function ($sub) use ($employeeId, $positionAlias) {
            $sub->selectRaw('1')
                ->from('succession_candidates as scoped_candidate')
                ->join('employees as scoped_employee', 'scoped_candidate.employee_id', '=', 'scoped_employee.employee_id')
                ->whereColumn('scoped_candidate.position_id', $positionAlias.'.position_id')
                ->where('scoped_employee.supervisor_id', $employeeId);
        });
    }

    private function successionCandidate(string $id): object
    {
        $candidate = DB::table('succession_candidates')->where('candidate_id', $id)->first();
        abort_if(! $candidate, 404);

        if (! $this->canSeeConfidentialSuccessionData()) {
            $supervisorId = DB::table('employees')->where('employee_id', $candidate->employee_id)->value('supervisor_id');
            abort_unless($supervisorId === $this->currentEmployeeId(), 403, 'You do not have access to that succession record.');
        }

        return $candidate;
    }

    private function redactConfidentialCandidate(object $candidate): object
    {
        if ($this->canSeeConfidentialSuccessionData()) {
            return $candidate;
        }

        foreach (['performance_score', 'potential_score', 'nine_box_label', 'readiness_level', 'mentor_id', 'status'] as $field) {
            $candidate->{$field} = null;
        }

        return $candidate;
    }

    public function index(Request $request)
    {
        $canSeeConfidential = $this->canSeeConfidentialSuccessionData();
        $positionStats = $this->scopeToRelevantPositions(DB::table('critical_positions as cp'));
        $candidateStats = $this->scopeToRelevantCandidates(
            DB::table('succession_candidates as sc')->join('employees as e', 'sc.employee_id', '=', 'e.employee_id')
        );

        $stats = [
            'critical_positions' => (clone $positionStats)->where('cp.is_critical', true)->count(),
            'ready_now' => $canSeeConfidential
                ? (clone $candidateStats)->where('sc.readiness_level', 'ready_now')->count()
                : null,
            'in_development' => $canSeeConfidential
                ? (clone $candidateStats)->whereIn('sc.readiness_level', ['1_2_years', '2_5_years'])->count()
                : null,
            'high_risk' => $canSeeConfidential
                ? (clone $positionStats)->whereIn('cp.vacancy_risk', ['high', 'critical'])->count()
                : null,
        ];

        $positions = $this->scopeToRelevantPositions(DB::table('critical_positions as cp')
            ->join('departments as d', 'cp.department_id', '=', 'd.department_id')
            ->leftJoin('employees as eh', 'cp.current_holder_id', '=', 'eh.employee_id')
            ->leftJoin('succession_candidates as sc', 'cp.position_id', '=', 'sc.position_id')
            ->select('cp.*', 'd.name as department_name',
                DB::raw("CONCAT(eh.first_name,' ',eh.last_name) AS current_holder_name"),
                DB::raw('COUNT(sc.candidate_id) as candidates_count'))
            ->groupBy('cp.position_id', 'd.name', 'eh.first_name', 'eh.last_name'))
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium','low')")
            ->get()
            ->each(function ($position) use ($canSeeConfidential) {
                if (! $canSeeConfidential) {
                    $position->vacancy_risk = null;
                    $position->risk_factors = null;
                    $position->estimated_vacancy_date = null;
                }
            });

        // 9-Box counts
        $box_counts = $canSeeConfidential
            ? DB::table('succession_candidates')->select('nine_box_label', DB::raw('COUNT(*) as cnt'))
                ->groupBy('nine_box_label')->get()->pluck('cnt', 'nine_box_label')->toArray()
            : [];

        // Pipeline filter: ?position_id=<uuid>. Validated against the real list
        // so a hand-typed value cannot leak into the query.
        $filterPositionId = $request->query('position_id');
        if ($filterPositionId && ! $positions->contains('position_id', $filterPositionId)) {
            $filterPositionId = null;
        }

        $candidatesQuery = DB::table('succession_candidates as sc')
            ->join('employees as e', 'sc.employee_id', '=', 'e.employee_id')
            ->join('critical_positions as cp', 'sc.position_id', '=', 'cp.position_id')
            ->leftJoin('leadership_development_paths as ldp', 'sc.candidate_id', '=', 'ldp.candidate_id')
            ->when($filterPositionId, fn ($q) => $q->where('sc.position_id', $filterPositionId))
            ->select(
                'sc.candidate_id', 'sc.position_id', 'sc.employee_id',
                'sc.performance_score', 'sc.potential_score', 'sc.nine_box_label',
                'sc.readiness_level', 'sc.status', 'sc.mentor_id',
                'e.first_name', 'e.last_name', 'e.position_title as position_title_current',
                'cp.position_title as target_position',
                DB::raw('COALESCE(ROUND(100.0 * SUM(CASE WHEN ldp.status = "completed" THEN 1 ELSE 0 END)
                                        / NULLIF(COUNT(ldp.path_id), 0)), 0) as dev_progress'))
            ->groupBy(
                'sc.candidate_id', 'sc.position_id', 'sc.employee_id',
                'sc.performance_score', 'sc.potential_score', 'sc.nine_box_label',
                'sc.readiness_level', 'sc.status', 'sc.mentor_id',
                'e.first_name', 'e.last_name', 'e.position_title',
                'cp.position_title')
            ->orderByDesc('sc.performance_score');

        $candidates = $this->scopeToRelevantCandidates($candidatesQuery)->get()
            ->map(fn ($candidate) => $this->redactConfidentialCandidate($candidate));

        $employees = $canSeeConfidential ? DB::table('employees')->orderBy('first_name')->get() : collect();

        return view('succession.index', compact(
            'stats', 'positions', 'box_counts', 'candidates', 'filterPositionId', 'employees', 'canSeeConfidential'
        ));
    }

    public function positionsIndex()
    {
        $canSeeConfidential = $this->canSeeConfidentialSuccessionData();
        $positions = $this->scopeToRelevantPositions(DB::table('critical_positions as cp')
            ->join('departments as d', 'cp.department_id', '=', 'd.department_id')
            ->leftJoin('employees as eh', 'cp.current_holder_id', '=', 'eh.employee_id')
            ->select('cp.*', 'd.name as department_name',
                DB::raw("CONCAT(COALESCE(eh.first_name,''),' ',COALESCE(eh.last_name,'')) as current_holder_name"))
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium','low')"))
            ->paginate(20);

        if (! $canSeeConfidential) {
            $positions->getCollection()->each(function ($position) {
                $position->vacancy_risk = null;
                $position->risk_factors = null;
                $position->estimated_vacancy_date = null;
            });
        }

        return view('succession.positions.index', compact('positions', 'canSeeConfidential'));
    }

    public function createPosition()
    {
        return view('succession.positions.create', [
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'employees' => DB::table('employees')->orderBy('first_name')->get(),
        ]);
    }

    public function storePosition(Request $request)
    {
        $request->validate([
            'position_title' => 'required|string|max:200',
            'department_id' => 'required|string|exists:departments,department_id',
            'vacancy_risk' => 'nullable|in:critical,high,medium,low',
            'estimated_vacancy_date' => 'nullable|date',
        ]);
        $positionId = (string) Str::uuid();
        DB::table('critical_positions')->insert([
            'position_id' => $positionId,
            'position_title' => $request->position_title,
            'department_id' => $request->department_id,
            'current_holder_id' => $request->current_holder_id ?: null,
            'vacancy_risk' => $request->vacancy_risk ?? 'medium',
            'estimated_vacancy_date' => $request->estimated_vacancy_date ?: null,
            'is_critical' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AuditTrail::record('succession_position_created', 'critical_positions', $positionId, afterState: [
            'position_title' => $request->position_title,
            'department_id' => $request->department_id,
            'vacancy_risk' => $request->vacancy_risk ?? 'medium',
        ]);

        return redirect()->route('succession.index')->with('success', 'Critical position added.');
    }

    public function reviewPosition(Request $request, $id)
    {
        $validated = $request->validate([
            'quarterly_review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $position = DB::table('critical_positions')->where('position_id', $id)->first();
        abort_if(! $position, 404);

        $after = [
            'last_reviewed_at' => now(),
            'last_reviewed_by' => $this->currentEmployeeId(),
            'quarterly_review_notes' => trim((string) ($validated['quarterly_review_notes'] ?? '')) ?: null,
            'updated_at' => now(),
        ];

        DB::table('critical_positions')->where('position_id', $id)->update($after);
        AuditTrail::record('succession_position_review', 'critical_positions', $id,
            beforeState: [
                'last_reviewed_at' => $position->last_reviewed_at,
                'last_reviewed_by' => $position->last_reviewed_by,
                'quarterly_review_notes' => $position->quarterly_review_notes,
            ],
            afterState: $after,
        );

        return back()->with('success', 'Quarterly position review recorded.');
    }

    public function showPosition($id)
    {
        $canSeeConfidential = $this->canSeeConfidentialSuccessionData();
        $position = $this->scopeToRelevantPositions(DB::table('critical_positions as cp')
            ->join('departments as d', 'cp.department_id', '=', 'd.department_id')
            ->leftJoin('employees as eh', 'cp.current_holder_id', '=', 'eh.employee_id')
            ->leftJoin('employees as reviewer', 'cp.last_reviewed_by', '=', 'reviewer.employee_id')
            ->where('cp.position_id', $id)
            ->select('cp.*', 'd.name as department_name',
                DB::raw("CONCAT(COALESCE(eh.first_name,''),' ',COALESCE(eh.last_name,'')) as current_holder_name"),
                DB::raw("CONCAT(COALESCE(reviewer.first_name,''),' ',COALESCE(reviewer.last_name,'')) as last_reviewer_name")))
            ->first();
        abort_if(! $position, 404);
        $candidates = $this->scopeToRelevantCandidates(DB::table('succession_candidates as sc')
            ->join('employees as e', 'sc.employee_id', '=', 'e.employee_id')
            ->where('sc.position_id', $id)
            ->select('sc.*', DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name")))
            ->get()->map(fn ($candidate) => $this->redactConfidentialCandidate($candidate));

        if (! $canSeeConfidential) {
            $position->vacancy_risk = null;
            $position->risk_factors = null;
            $position->estimated_vacancy_date = null;
        }

        return view('succession.positions.show', compact('position', 'candidates', 'canSeeConfidential'));
    }

    public function createCandidate()
    {
        return view('succession.candidates.create', [
            'employees' => $this->scopeToVisibleEmployees(DB::table('employees')->orderBy('first_name'), 'employee_id')->get(),
            'positions' => DB::table('critical_positions')->orderBy('position_title')->get(),
        ]);
    }

    public function storeCandidate(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'position_id' => 'required|string|exists:critical_positions,position_id',
            'performance_score' => 'nullable|integer|min:1|max:5',
            'potential_score' => 'nullable|integer|min:1|max:5',
            'readiness_level' => 'nullable|in:ready_now,1_2_years,2_5_years,long_term',
            'mentor_id' => 'nullable|string|exists:employees,employee_id',
        ]);

        $this->authorizeEmployeeAccess($request->employee_id);

        $already = DB::table('succession_candidates')
            ->where('position_id', $request->position_id)
            ->where('employee_id', $request->employee_id)
            ->exists();

        if ($already) {
            return back()->withInput()->with('error', 'That employee is already nominated for this position.');
        }

        $perfScore = (int) ($request->performance_score ?: 3);
        $potScore = (int) ($request->potential_score ?: 3);
        $candidateId = (string) Str::uuid();

        DB::table('succession_candidates')->insert([
            'candidate_id' => $candidateId,
            'employee_id' => $request->employee_id,
            'position_id' => $request->position_id,
            'performance_score' => $perfScore,
            'potential_score' => $potScore,
            'nine_box_label' => $this->nineBoxLabel($perfScore, $potScore),
            'readiness_level' => $request->readiness_level ?: '1_2_years',
            'mentor_id' => $request->mentor_id ?: null,
            'status' => 'proposed',
            'nominated_by' => $this->currentEmployeeId(),
            'nominated_at' => now(),
        ]);

        AuditTrail::record('succession_candidate_added', 'succession_candidates', $candidateId, afterState: [
            'employee_id' => $request->employee_id,
            'position_id' => $request->position_id,
            'performance_score' => $perfScore,
            'potential_score' => $potScore,
            'readiness_level' => $request->readiness_level ?: '1_2_years',
            'mentor_id' => $request->mentor_id ?: null,
        ]);

        return redirect()->route('succession.index')->with('success', 'Candidate nominated.');
    }

    /**
     * Standard 9-box placement from a 1-5 performance/potential pair.
     * Each axis collapses to low (1-2), medium (3), high (4-5).
     *
     * This is the single source of the label: it is computed on every write and
     * never accepted from user input, so the badge can never contradict the
     * scores shown beside it. (The column is a plain varchar, not a MySQL
     * GENERATED column, so the guarantee has to live here.)
     */
    private function nineBoxLabel(int $performance, int $potential): string
    {
        $band = fn (int $v) => $v >= 4 ? 'high' : ($v === 3 ? 'med' : 'low');

        return match ($band($performance).'_'.$band($potential)) {
            'high_high' => 'star',
            'high_med' => 'high',
            'high_low' => 'solid',
            'med_high' => 'potential',
            'med_med' => 'core',
            'med_low' => 'avg',
            'low_high' => 'diamond',
            'low_med' => 'inconsist',
            default => 'under',
        };
    }

    /** Edit form for an existing nomination. */
    public function editCandidate($id)
    {
        $candidate = DB::table('succession_candidates as sc')
            ->join('employees as e', 'sc.employee_id', '=', 'e.employee_id')
            ->join('critical_positions as cp', 'sc.position_id', '=', 'cp.position_id')
            ->where('sc.candidate_id', $id)
            ->select('sc.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'cp.position_title as target_title')
            ->first();

        abort_if(! $candidate, 404);

        return view('succession.candidates.edit', [
            'candidate' => $candidate,
            'employees' => DB::table('employees')->orderBy('first_name')->get(),
        ]);
    }

    /**
     * Update scores, readiness, and mentor. The 9-box label is recomputed here
     * rather than submitted, so an edit can never desynchronise it.
     */
    public function updateCandidate(Request $request, $id)
    {
        $request->validate([
            'performance_score' => 'required|integer|min:1|max:5',
            'potential_score' => 'required|integer|min:1|max:5',
            'readiness_level' => 'required|in:ready_now,1_2_years,2_5_years,long_term',
            'mentor_id' => 'nullable|string|exists:employees,employee_id',
        ]);

        $before = $this->successionCandidate($id);
        $after = [
            'performance_score' => (int) $request->performance_score,
            'potential_score' => (int) $request->potential_score,
            'nine_box_label' => $this->nineBoxLabel((int) $request->performance_score, (int) $request->potential_score),
            'readiness_level' => $request->readiness_level,
            'mentor_id' => $request->mentor_id ?: null,
            'reviewed_at' => now(),
        ];

        DB::table('succession_candidates')->where('candidate_id', $id)->update($after);
        AuditTrail::record('succession_candidate_updated', 'succession_candidates', $id,
            beforeState: (array) $before, afterState: $after);

        return redirect()->route('succession.candidates.show', $id)->with('success', 'Candidate updated.');
    }

    /**
     * Remove a nomination from the pipeline.
     *
     * A hard delete: succession_candidates has no soft-delete column, and the
     * (position_id, employee_id) unique key would otherwise block re-nominating
     * the same person later. Development milestones are removed first — the FK
     * would block the parent delete, and an orphaned path row belongs to nobody.
     */
    public function withdrawCandidate($id)
    {
        $candidate = $this->successionCandidate($id);

        DB::transaction(function () use ($id) {
            DB::table('leadership_development_paths')->where('candidate_id', $id)->delete();
            DB::table('succession_candidates')->where('candidate_id', $id)->delete();
        });

        AuditTrail::record('succession_candidate_removed', 'succession_candidates', $id,
            beforeState: (array) $candidate);

        return redirect()->route('succession.index')->with('success', 'Candidate withdrawn from the pipeline.');
    }

    /** Add a development milestone to a candidate's leadership path. */
    public function storeMilestone(Request $request, $id)
    {
        $request->validate([
            'milestone_title' => 'required|string|max:200',
            'milestone_type' => 'nullable|in:course,assignment,mentoring,rotation,certification,project',
            'description' => 'nullable|string|max:1000',
            'target_date' => 'nullable|date',
        ]);

        $this->successionCandidate($id);
        $pathId = (string) Str::uuid();

        DB::table('leadership_development_paths')->insert([
            'path_id' => $pathId,
            'candidate_id' => $id,
            'milestone_title' => $request->milestone_title,
            'milestone_type' => $request->milestone_type ?: null,
            'description' => $request->description ?: null,
            'target_date' => $request->target_date ?: null,
            'status' => 'not_started',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::record('succession_milestone_created', 'leadership_development_paths', $pathId, afterState: [
            'candidate_id' => $id,
            'milestone_title' => $request->milestone_title,
            'milestone_type' => $request->milestone_type ?: null,
            'target_date' => $request->target_date ?: null,
            'status' => 'not_started',
        ]);

        return back()->with('success', 'Development milestone added.');
    }

    /**
     * Advance a milestone: not_started -> in_progress -> completed.
     * completed_date is stamped on completion and cleared if it moves back, so
     * the Dev Progress percentage always reflects genuinely finished work.
     */
    public function updateMilestone(Request $request, $id, $pathId)
    {
        $request->validate(['status' => 'required|in:not_started,in_progress,completed']);
        $this->successionCandidate($id);

        $milestone = DB::table('leadership_development_paths')
            ->where('path_id', $pathId)->where('candidate_id', $id)->first();

        abort_if(! $milestone, 404);

        $after = [
            'status' => $request->status,
            'completed_date' => $request->status === 'completed' ? now()->toDateString() : null,
            'updated_at' => now(),
        ];
        DB::table('leadership_development_paths')->where('path_id', $pathId)->update($after);
        AuditTrail::record('succession_milestone_updated', 'leadership_development_paths', $pathId,
            beforeState: (array) $milestone, afterState: $after);

        return back()->with('success', 'Milestone updated.');
    }

    /** Remove a milestone from a candidate's development path. */
    public function destroyMilestone($id, $pathId)
    {
        $this->successionCandidate($id);
        $milestone = DB::table('leadership_development_paths')
            ->where('path_id', $pathId)->where('candidate_id', $id)->first();

        abort_if(! $milestone, 404);

        DB::table('leadership_development_paths')->where('path_id', $pathId)->delete();

        AuditTrail::record('succession_milestone_removed', 'leadership_development_paths', $pathId,
            beforeState: (array) $milestone);

        return back()->with('success', 'Milestone removed.');
    }

    public function showCandidate($id)
    {
        $this->successionCandidate($id);
        $candidate = DB::table('succession_candidates as sc')
            ->join('employees as e', 'sc.employee_id', '=', 'e.employee_id')
            ->join('critical_positions as cp', 'sc.position_id', '=', 'cp.position_id')
            ->where('sc.candidate_id', $id)
            ->select('sc.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title as current_title',
                'cp.position_title as target_title')
            ->first();
        abort_if(! $candidate, 404);
        $candidate = $this->redactConfidentialCandidate($candidate);
        $dev_paths = DB::table('leadership_development_paths')->where('candidate_id', $id)->get();

        // Keyed `evidence`, not `readiness` — the view already binds $readiness
        // to the candidate's readiness_level string.
        return view('succession.candidates.show', compact('candidate', 'dev_paths') + [
            'evidence' => $this->readinessEvidence($candidate->employee_id),
            'canSeeConfidential' => $this->canSeeConfidentialSuccessionData(),
        ]);
    }

    /**
     * What the candidate has actually completed, pulled from Learning and
     * Competency.
     *
     * Readiness was a judgement typed into a form; the pathway and proficiency
     * figures behind it lived one module away and had to be looked up by hand.
     * Surfacing them here makes a promotion decision reviewable against evidence
     * rather than against somebody's recollection.
     */
    private function readinessEvidence(string $employeeId): array
    {
        // Pathway completion: courses in each pathway against the ones this
        // employee has finished.
        $pathways = DB::table('learning_pathways as lp')
            ->join('pathway_courses as pc', 'pc.pathway_id', '=', 'lp.pathway_id')
            ->leftJoin('course_enrollments as ce', function ($join) use ($employeeId) {
                $join->on('ce.course_id', '=', 'pc.course_id')
                    ->where('ce.employee_id', '=', $employeeId);
            })
            ->groupBy('lp.pathway_id', 'lp.pathway_name')
            ->select('lp.pathway_id', 'lp.pathway_name')
            ->selectRaw('COUNT(pc.course_id) as total_courses')
            ->selectRaw("SUM(CASE WHEN ce.status = 'completed' THEN 1 ELSE 0 END) as completed_courses")
            ->selectRaw('SUM(CASE WHEN ce.enrollment_id IS NOT NULL THEN 1 ELSE 0 END) as enrolled_courses')
            ->orderBy('lp.pathway_name')
            ->get()
            ->map(function ($pathway) {
                $pathway->pct = $pathway->total_courses > 0
                    ? round($pathway->completed_courses / $pathway->total_courses * 100)
                    : 0;

                return $pathway;
            });

        // Latest assessment per competency, newest first — the same reduction
        // CompetencyGapAnalysisService does, since reassessments stack.
        $competencies = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'c.competency_id', '=', 'ca.competency_id')
            ->join('competency_categories as cc', 'cc.category_id', '=', 'c.category_id')
            ->where('ca.employee_id', $employeeId)
            ->select('ca.competency_id', 'ca.current_proficiency', 'ca.assessed_date',
                'c.competency_name', 'c.required_proficiency', 'cc.jci_standard_code')
            ->orderByDesc('ca.assessed_date')
            ->get()
            ->unique('competency_id')
            ->values();

        $training = DB::table('course_enrollments')
            ->where('employee_id', $employeeId)
            ->selectRaw('COUNT(*) as enrolled')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status <> 'completed' AND assignment_id IS NOT NULL THEN 1 ELSE 0 END) as mandatory_outstanding")
            ->selectRaw('SUM(COALESCE(cpd_hours_earned, 0)) as cpd_hours')
            ->first();

        return [
            'pathways' => $pathways,
            'competencies' => $competencies,
            'at_target' => $competencies->filter(
                fn ($c) => $c->current_proficiency >= $c->required_proficiency
            )->count(),
            'training' => $training,
            'cycles' => app(RenewalCycleService::class)->cyclesFor($employeeId),
        ];
    }
}
