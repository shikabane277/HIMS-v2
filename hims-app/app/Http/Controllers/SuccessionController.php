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
            ->select('sc.*','e.first_name','e.last_name','e.position_title as position_title_current',
                     'cp.position_title as target_position',
                     DB::raw('ROUND(AVG(CASE WHEN ldp.status = "completed" THEN 100 ELSE 0 END)) as dev_progress'))
            ->groupBy('sc.candidate_id','e.first_name','e.last_name','e.position_title','cp.position_title')
            ->orderByDesc('sc.performance_score')->get();

        return view('succession.index', compact('stats','positions','box_counts','candidates'));
    }
}
