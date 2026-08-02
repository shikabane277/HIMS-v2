<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuccessionController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'critical_positions' => DB::table('critical_positions')->where('is_critical',true)->count(),
            'ready_now'          => DB::table('succession_candidates')->where('readiness_level','ready_now')->count(),
            'in_development'     => DB::table('succession_candidates')->whereIn('readiness_level',['1_2_years','2_5_years'])->count(),
            'high_risk'          => DB::table('critical_positions')->whereIn('vacancy_risk',['high','critical'])->count(),
        ];

        $positions = DB::table('critical_positions as cp')
            ->join('departments as d','cp.department_id','=','d.department_id')
            ->leftJoin('employees as eh','cp.current_holder_id','=','eh.employee_id')
            ->leftJoin('succession_candidates as sc','cp.position_id','=','sc.position_id')
            ->select('cp.*','d.name as department_name',
                DB::raw("CONCAT(eh.first_name,' ',eh.last_name) AS current_holder_name"),
                DB::raw('COUNT(sc.candidate_id) as candidates_count'))
            ->groupBy('cp.position_id','d.name','eh.first_name','eh.last_name')
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium','low')")
            ->get();

        // 9-Box counts
        $box_counts = DB::table('succession_candidates')
            ->select('nine_box_label', DB::raw('COUNT(*) as cnt'))
            ->groupBy('nine_box_label')->get()
            ->pluck('cnt','nine_box_label')->toArray();

        // Pipeline filter: ?position_id=<uuid>. Validated against the real list
        // so a hand-typed value cannot leak into the query.
        $filterPositionId = $request->query('position_id');
        if ($filterPositionId && ! $positions->contains('position_id', $filterPositionId)) {
            $filterPositionId = null;
        }

        $candidates = DB::table('succession_candidates as sc')
            ->join('employees as e','sc.employee_id','=','e.employee_id')
            ->join('critical_positions as cp','sc.position_id','=','cp.position_id')
            ->leftJoin('leadership_development_paths as ldp','sc.candidate_id','=','ldp.candidate_id')
            ->when($filterPositionId, fn ($q) => $q->where('sc.position_id', $filterPositionId))
            ->select(
                'sc.candidate_id','sc.position_id','sc.employee_id',
                'sc.performance_score','sc.potential_score','sc.nine_box_label',
                'sc.readiness_level','sc.status','sc.mentor_id',
                'e.first_name','e.last_name','e.position_title as position_title_current',
                'cp.position_title as target_position',
                // Percentage of this candidate's milestones that are complete.
                // COUNT(ldp.path_id) ignores the NULL row a candidate with no
                // milestones produces via the LEFT JOIN, so 0 milestones reads
                // as 0% instead of dividing by zero.
                DB::raw('COALESCE(ROUND(100.0 * SUM(CASE WHEN ldp.status = "completed" THEN 1 ELSE 0 END)
                                        / NULLIF(COUNT(ldp.path_id), 0)), 0) as dev_progress'))
            ->groupBy(
                'sc.candidate_id','sc.position_id','sc.employee_id',
                'sc.performance_score','sc.potential_score','sc.nine_box_label',
                'sc.readiness_level','sc.status','sc.mentor_id',
                'e.first_name','e.last_name','e.position_title',
                'cp.position_title')
            ->orderByDesc('sc.performance_score')->get();

        return view('succession.index', compact('stats','positions','box_counts','candidates','filterPositionId'));
    }

    public function positionsIndex()
    {
        $positions = DB::table('critical_positions as cp')
            ->join('departments as d','cp.department_id','=','d.department_id')
            ->leftJoin('employees as eh','cp.current_holder_id','=','eh.employee_id')
            ->select('cp.*','d.name as department_name',
                DB::raw("CONCAT(COALESCE(eh.first_name,''),' ',COALESCE(eh.last_name,'')) as current_holder_name"))
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium','low')")
            ->paginate(20);
        return view('succession.positions.index', compact('positions'));
    }

    public function createPosition()
    {
        return view('succession.positions.create', [
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'employees'   => DB::table('employees')->orderBy('first_name')->get(),
        ]);
    }

    public function storePosition(\Illuminate\Http\Request $request)
    {
        $request->validate(['position_title'=>'required|string|max:200','department_id'=>'required|string']);
        DB::table('critical_positions')->insert([
            'position_id'            => \Illuminate\Support\Str::uuid(),
            'position_title'         => $request->position_title,
            'department_id'          => $request->department_id,
            'current_holder_id'      => $request->current_holder_id ?: null,
            'vacancy_risk'           => $request->vacancy_risk ?? 'medium',
            'estimated_vacancy_date' => $request->estimated_vacancy_date ?: null,
            'is_critical'            => true,
            'created_at'             => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('succession.index')->with('success','Critical position added.');
    }

    public function showPosition($id)
    {
        $position = DB::table('critical_positions as cp')
            ->join('departments as d','cp.department_id','=','d.department_id')
            ->leftJoin('employees as eh','cp.current_holder_id','=','eh.employee_id')
            ->where('cp.position_id',$id)
            ->select('cp.*','d.name as department_name',
                DB::raw("CONCAT(COALESCE(eh.first_name,''),' ',COALESCE(eh.last_name,'')) as current_holder_name"))
            ->first();
        abort_if(!$position, 404);
        $candidates = DB::table('succession_candidates as sc')
            ->join('employees as e','sc.employee_id','=','e.employee_id')
            ->where('sc.position_id',$id)
            ->select('sc.*',DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
            ->get();
        return view('succession.positions.show', compact('position','candidates'));
    }

    public function createCandidate()
    {
        return view('succession.candidates.create', [
            'employees' => DB::table('employees')->orderBy('first_name')->get(),
            'positions' => DB::table('critical_positions')->orderBy('position_title')->get(),
        ]);
    }

    public function storeCandidate(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'employee_id'       => 'required|string|exists:employees,employee_id',
            'position_id'       => 'required|string|exists:critical_positions,position_id',
            'performance_score' => 'required|integer|min:1|max:5',
            'potential_score'   => 'required|integer|min:1|max:5',
            'readiness_level'   => 'nullable|in:ready_now,1_2_years,2_5_years,long_term',
            'mentor_id'         => 'nullable|string|exists:employees,employee_id',
        ]);

        // (position_id, employee_id) is unique — report it instead of a 500.
        $already = DB::table('succession_candidates')
            ->where('position_id', $request->position_id)
            ->where('employee_id', $request->employee_id)
            ->exists();

        if ($already) {
            return back()->withInput()->with('error', 'That employee is already nominated for this position.');
        }

        // This table has no timestamps(); it tracks nominated_at/reviewed_at/approved_at.
        DB::table('succession_candidates')->insert([
            'candidate_id'      => \Illuminate\Support\Str::uuid(),
            'employee_id'       => $request->employee_id,
            'position_id'       => $request->position_id,
            'performance_score' => (int) $request->performance_score,
            'potential_score'   => (int) $request->potential_score,
            // Always derived — never taken from the request. See nineBoxLabel().
            'nine_box_label'    => $this->nineBoxLabel((int) $request->performance_score, (int) $request->potential_score),
            'readiness_level'   => $request->readiness_level ?: '1_2_years',
            'mentor_id'         => $request->mentor_id ?: null,
            'status'            => 'proposed',
            'nominated_by'      => $this->currentEmployeeId(),
            'nominated_at'      => now(),
        ]);

        return redirect()->route('succession.index')->with('success','Candidate nominated.');
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

        return match ($band($performance) . '_' . $band($potential)) {
            'high_high' => 'star',
            'high_med'  => 'high',
            'high_low'  => 'solid',
            'med_high'  => 'potential',
            'med_med'   => 'core',
            'med_low'   => 'avg',
            'low_high'  => 'diamond',
            'low_med'   => 'inconsist',
            default     => 'under',
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
    public function updateCandidate(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'performance_score' => 'required|integer|min:1|max:5',
            'potential_score'   => 'required|integer|min:1|max:5',
            'readiness_level'   => 'required|in:ready_now,1_2_years,2_5_years,long_term',
            'mentor_id'         => 'nullable|string|exists:employees,employee_id',
        ]);

        $exists = DB::table('succession_candidates')->where('candidate_id', $id)->exists();
        abort_if(! $exists, 404);

        DB::table('succession_candidates')->where('candidate_id', $id)->update([
            'performance_score' => (int) $request->performance_score,
            'potential_score'   => (int) $request->potential_score,
            'nine_box_label'    => $this->nineBoxLabel((int) $request->performance_score, (int) $request->potential_score),
            'readiness_level'   => $request->readiness_level,
            'mentor_id'         => $request->mentor_id ?: null,
            'reviewed_at'       => now(),
        ]);

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
        $candidate = DB::table('succession_candidates')->where('candidate_id', $id)->first();
        abort_if(! $candidate, 404);

        DB::transaction(function () use ($id) {
            DB::table('leadership_development_paths')->where('candidate_id', $id)->delete();
            DB::table('succession_candidates')->where('candidate_id', $id)->delete();
        });

        return redirect()->route('succession.index')->with('success', 'Candidate withdrawn from the pipeline.');
    }

    /** Add a development milestone to a candidate's leadership path. */
    public function storeMilestone(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'milestone_title' => 'required|string|max:200',
            'milestone_type'  => 'nullable|in:course,assignment,mentoring,rotation,certification,project',
            'description'     => 'nullable|string|max:1000',
            'target_date'     => 'nullable|date',
        ]);

        $exists = DB::table('succession_candidates')->where('candidate_id', $id)->exists();
        abort_if(! $exists, 404);

        DB::table('leadership_development_paths')->insert([
            'path_id'         => \Illuminate\Support\Str::uuid(),
            'candidate_id'    => $id,
            'milestone_title' => $request->milestone_title,
            'milestone_type'  => $request->milestone_type ?: null,
            'description'     => $request->description ?: null,
            'target_date'     => $request->target_date ?: null,
            'status'          => 'not_started',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return back()->with('success', 'Development milestone added.');
    }

    /**
     * Advance a milestone: not_started -> in_progress -> completed.
     * completed_date is stamped on completion and cleared if it moves back, so
     * the Dev Progress percentage always reflects genuinely finished work.
     */
    public function updateMilestone(\Illuminate\Http\Request $request, $id, $pathId)
    {
        $request->validate(['status' => 'required|in:not_started,in_progress,completed']);

        $milestone = DB::table('leadership_development_paths')
            ->where('path_id', $pathId)->where('candidate_id', $id)->first();

        abort_if(! $milestone, 404);

        DB::table('leadership_development_paths')->where('path_id', $pathId)->update([
            'status'         => $request->status,
            'completed_date' => $request->status === 'completed' ? now()->toDateString() : null,
            'updated_at'     => now(),
        ]);

        return back()->with('success', 'Milestone updated.');
    }

    /** Remove a milestone from a candidate's development path. */
    public function destroyMilestone($id, $pathId)
    {
        $milestone = DB::table('leadership_development_paths')
            ->where('path_id', $pathId)->where('candidate_id', $id)->first();

        abort_if(! $milestone, 404);

        DB::table('leadership_development_paths')->where('path_id', $pathId)->delete();

        return back()->with('success', 'Milestone removed.');
    }

    public function showCandidate($id)
    {
        $candidate = DB::table('succession_candidates as sc')
            ->join('employees as e','sc.employee_id','=','e.employee_id')
            ->join('critical_positions as cp','sc.position_id','=','cp.position_id')
            ->where('sc.candidate_id',$id)
            ->select('sc.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title as current_title',
                'cp.position_title as target_title')
            ->first();
        abort_if(!$candidate, 404);
        $dev_paths = DB::table('leadership_development_paths')->where('candidate_id',$id)->get();
        return view('succession.candidates.show', compact('candidate','dev_paths'));
    }
}
