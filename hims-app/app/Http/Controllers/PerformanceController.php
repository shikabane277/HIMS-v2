<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceController extends Controller
{
    /* ── INDEX ── */
    public function index()
    {
        $stats = [
            'active'  => DB::table('review_cycles')->where('status','active')->count(),
            'pending' => DB::table('performance_reviews')->whereIn('status',['draft','self_assessment','supervisor_review'])->count(),
            'pips'    => DB::table('performance_improvement_plans')->where('status','active')->count(),
        ];

        $cycles = DB::table('review_cycles as rc')
            ->leftJoin('performance_reviews as pr','rc.cycle_id','=','pr.cycle_id')
            ->select('rc.*', DB::raw('COUNT(pr.review_id) as reviews_count'))
            ->groupBy('rc.cycle_id')
            ->orderByDesc('rc.start_date')->get();

        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->select('pr.review_id','pr.status','pr.overall_score','pr.review_type',
                     'e.first_name as employee_first','e.last_name as employee_last','e.position_title',
                     'rc.cycle_name')
            ->orderByDesc('pr.updated_at')->limit(20)->get();

        return view('performance.index', compact('stats','cycles','reviews'));
    }

    /* ── SHOW REVIEW ── */
    public function show($id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->where('pr.review_id',$id)
            ->select('pr.*','e.first_name','e.last_name','e.position_title','e.department_id',
                     'rc.cycle_name','rc.cycle_type')
            ->first();

        abort_if(!$review, 404);

        $kpi_scores = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k','rks.kpi_id','=','k.kpi_id')
            ->where('rks.review_id',$id)->get();

        $goals = DB::table('review_goals')->where('review_id',$id)->get();

        $peer_reviews = DB::table('peer_reviews as p')
            ->join('employees as e','p.peer_employee_id','=','e.employee_id')
            ->where('p.review_id',$id)
            ->select('p.*',DB::raw("CASE WHEN p.is_anonymous THEN 'Anonymous Peer' ELSE CONCAT(e.first_name,' ',e.last_name) END as reviewer"))
            ->get();

        $pip = DB::table('performance_improvement_plans')->where('triggered_by_review',$id)->first();

        return view('performance.show', compact('review','kpi_scores','goals','peer_reviews','pip'));
    }

    /* ── CREATE CYCLE FORM ── */
    public function createCycle() { return view('performance.cycles.create'); }

    /* ── STORE CYCLE ── */
    public function storeCycle(Request $request)
    {
        $request->validate([
            'cycle_name'  => 'required|string|max:100',
            'cycle_type'  => 'required|in:annual,semi_annual,quarterly,probationary',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
        ]);

        DB::table('review_cycles')->insert([
            'cycle_id'   => Str::uuid(),
            'cycle_name' => $request->cycle_name,
            'cycle_type' => $request->cycle_type,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'status'     => 'planned',
            'created_by' => auth()->user()->employee_id ?? DB::table('employees')->value('employee_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('performance.index')->with('success','Review cycle created successfully.');
    }

    /* ── REVIEWS INDEX ── */
    public function reviewsIndex()
    {
        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->select('pr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                'e.position_title','rc.cycle_name')
            ->orderByDesc('pr.updated_at')->paginate(20);

        return view('performance.reviews.index', compact('reviews'));
    }
}
