<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuccessionController extends Controller
{
    public function index()
    {
        $stats = [
            'critical_positions' => DB::table('critical_positions')->where('is_critical',true)->count(),
            'ready_now'          => DB::table('succession_candidates')->where('readiness_level','ready_now')->count(),
            'in_development'     => DB::table('succession_candidates')->whereIn('readiness_level',['1_2_years','2_5_years'])->count(),
            'high_risk'          => DB::table('critical_positions')->where('vacancy_risk','high')->orWhere('vacancy_risk','critical')->count(),
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

        $candidates = DB::table('succession_candidates as sc')
            ->join('employees as e','sc.employee_id','=','e.employee_id')
            ->join('critical_positions as cp','sc.position_id','=','cp.position_id')
            ->leftJoin('leadership_development_paths as ldp','sc.candidate_id','=','ldp.candidate_id')
            ->select(
                'sc.candidate_id','sc.position_id','sc.employee_id',
                'sc.performance_score','sc.potential_score','sc.nine_box_label',
                'sc.readiness_level','sc.status','sc.mentor_id',
                'e.first_name','e.last_name','e.position_title as position_title_current',
                'cp.position_title as target_position',
                DB::raw('ROUND(AVG(CASE WHEN ldp.status = "completed" THEN 100 ELSE 0 END)) as dev_progress'))
            ->groupBy(
                'sc.candidate_id','sc.position_id','sc.employee_id',
                'sc.performance_score','sc.potential_score','sc.nine_box_label',
                'sc.readiness_level','sc.status','sc.mentor_id',
                'e.first_name','e.last_name','e.position_title',
                'cp.position_title')
            ->orderByDesc('sc.performance_score')->get();

        return view('succession.index', compact('stats','positions','box_counts','candidates'));
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
        $request->validate(['employee_id'=>'required','position_id'=>'required']);
        DB::table('succession_candidates')->insert([
            'candidate_id'     => \Illuminate\Support\Str::uuid(),
            'employee_id'      => $request->employee_id,
            'position_id'      => $request->position_id,
            'nine_box_label'   => $request->nine_box_label ?? 'core',
            'readiness_level'  => $request->readiness_level ?? '1_2_years',
            'performance_score'=> $request->performance_score,
            'potential_score'  => $request->potential_score,
            'status'           => 'proposed',
            'created_at'       => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('succession.index')->with('success','Candidate nominated.');
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
