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
                     'rc.cycle_name');

        $reviews = $this->scopeToVisibleEmployees($reviews)
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
        $this->authorizeEmployeeAccess($review->employee_id);

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
            'created_by' => $this->currentEmployeeId() ?? DB::table('employees')->value('employee_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('performance.index')->with('success','Review cycle created successfully.');
    }

    /* ── SHOW CYCLE ── */
    public function showCycle($id)
    {
        $cycle = DB::table('review_cycles as rc')
            ->leftJoin('employees as c','rc.created_by','=','c.employee_id')
            ->where('rc.cycle_id',$id)
            ->select('rc.*', DB::raw("CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) as created_by_name"))
            ->first();

        abort_if(!$cycle, 404);

        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->leftJoin('employees as r','pr.reviewer_id','=','r.employee_id')
            ->where('pr.cycle_id',$id)
            ->select('pr.review_id','pr.status','pr.overall_score','pr.self_rating','pr.supervisor_rating','pr.review_type',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title','d.name as department_name',
                DB::raw("CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) as reviewer_name"));

        $reviews = $this->scopeToVisibleEmployees($reviews)
            ->orderBy('e.last_name')->get();

        $stats = [
            'total'     => $reviews->count(),
            'completed' => $reviews->where('status','completed')->count(),
            'in_progress' => $reviews->whereNotIn('status',['completed','draft'])->count(),
            'avg_score' => round((float) $reviews->whereNotNull('overall_score')->avg('overall_score'), 2),
        ];

        return view('performance.cycles.show', compact('cycle','reviews','stats'));
    }

    /* ── EDIT CYCLE ── */
    public function editCycle($id)
    {
        $cycle = DB::table('review_cycles')->where('cycle_id',$id)->first();
        abort_if(!$cycle, 404);

        return view('performance.cycles.edit', compact('cycle'));
    }

    /* ── UPDATE CYCLE ── */
    public function updateCycle(Request $request, $id)
    {
        abort_if(! DB::table('review_cycles')->where('cycle_id',$id)->exists(), 404);

        $request->validate([
            'cycle_name'  => 'required|string|max:100',
            'cycle_type'  => 'required|in:annual,semi_annual,quarterly,probationary',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'status'      => 'required|in:planned,active,closed,archived',
        ]);

        DB::table('review_cycles')->where('cycle_id',$id)->update([
            'cycle_name' => $request->cycle_name,
            'cycle_type' => $request->cycle_type,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'status'     => $request->status,
            'updated_at' => now(),
        ]);

        return redirect()->route('performance.cycles.show',$id)->with('success','Review cycle updated.');
    }

    /* ── REVIEWS INDEX ── */
    public function reviewsIndex()
    {
        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->select('pr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                'e.position_title','rc.cycle_name');

        $reviews = $this->scopeToVisibleEmployees($reviews)
            ->orderByDesc('pr.updated_at')->paginate(20);

        return view('performance.reviews.index', compact('reviews'));
    }

    /* ── CREATE REVIEW FORM ── */
    public function createReview(Request $request)
    {
        $employees = DB::table('employees as e')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->where('e.employment_status','active')
            ->select('e.employee_id','e.first_name','e.last_name','e.position_title','d.name as department_name');

        $employees = $this->scopeToVisibleEmployees($employees)
            ->orderBy('e.first_name')->get();

        return view('performance.reviews.create', [
            'employees' => $employees,
            'cycles'    => DB::table('review_cycles')->whereIn('status',['planned','active'])->orderByDesc('start_date')->get(),
            'reviewers' => DB::table('employees')->where('employment_status','active')->orderBy('first_name')->get(),
            'kpis'      => DB::table('kpi_library')->where('is_active',true)->orderBy('kpi_category')->orderBy('kpi_name')->get(),
            'selectedCycle' => $request->query('cycle'),
        ]);
    }

    /* ── STORE REVIEW ── */
    public function storeReview(Request $request)
    {
        $request->validate([
            'employee_id'  => 'required|string|exists:employees,employee_id',
            'cycle_id'     => 'required|string|exists:review_cycles,cycle_id',
            'reviewer_id'  => 'nullable|string|exists:employees,employee_id',
            'review_type'  => 'required|in:standard,probationary,promotion,360',
            'kpi_ids'      => 'nullable|array',
            'kpi_ids.*'    => 'string|exists:kpi_library,kpi_id',
        ]);

        $this->authorizeEmployeeAccess($request->employee_id);

        $duplicate = DB::table('performance_reviews')
            ->where('employee_id',$request->employee_id)
            ->where('cycle_id',$request->cycle_id)
            ->exists();

        if ($duplicate) {
            return back()->withInput()->with('error','That employee already has a review in this cycle.');
        }

        $reviewId = (string) Str::uuid();

        DB::transaction(function () use ($request, $reviewId) {
            DB::table('performance_reviews')->insert([
                'review_id'   => $reviewId,
                'employee_id' => $request->employee_id,
                'cycle_id'    => $request->cycle_id,
                'reviewer_id' => $request->reviewer_id ?: $this->currentEmployeeId(),
                'review_type' => $request->review_type,
                'status'      => 'self_assessment',
                'created_at'  => now(), 'updated_at' => now(),
            ]);

            // Pre-attach the chosen KPIs so the scoring form has rows to fill.
            foreach ((array) $request->kpi_ids as $kpiId) {
                DB::table('review_kpi_scores')->insert([
                    'score_id'   => (string) Str::uuid(),
                    'review_id'  => $reviewId,
                    'kpi_id'     => $kpiId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('performance.reviews.score',$reviewId)->with('success','Review created. Enter KPI scores below.');
    }

    /* ── SCORING FORM ── */
    public function scoreReview($id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->where('pr.review_id',$id)
            ->select('pr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title','rc.cycle_name','rc.status as cycle_status')
            ->first();

        abort_if(!$review, 404);
        $this->authorizeEmployeeAccess($review->employee_id);

        $scores = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k','rks.kpi_id','=','k.kpi_id')
            ->where('rks.review_id',$id)
            ->select('rks.*','k.kpi_name','k.kpi_category','k.description','k.target_value','k.unit','k.weight')
            ->orderBy('k.kpi_category')->orderBy('k.kpi_name')->get();

        $availableKpis = DB::table('kpi_library')
            ->where('is_active',true)
            ->whereNotIn('kpi_id', $scores->pluck('kpi_id')->all() ?: [''])
            ->orderBy('kpi_category')->orderBy('kpi_name')->get();

        return view('performance.reviews.score', compact('review','scores','availableKpis'));
    }

    /* ── SAVE SCORES ── */
    public function saveScores(Request $request, $id)
    {
        $review = DB::table('performance_reviews')->where('review_id',$id)->first();
        abort_if(!$review, 404);
        $this->authorizeEmployeeAccess($review->employee_id);

        $request->validate([
            'scores'                     => 'nullable|array',
            'scores.*.self_score'        => 'nullable|numeric|min:1|max:5',
            'scores.*.supervisor_score'  => 'nullable|numeric|min:1|max:5',
            'scores.*.peer_score'        => 'nullable|numeric|min:1|max:5',
            'scores.*.comments'          => 'nullable|string|max:1000',
            'add_kpi_ids'                => 'nullable|array',
            'add_kpi_ids.*'              => 'string|exists:kpi_library,kpi_id',
            'strengths_text'             => 'nullable|string|max:2000',
            'improvements_text'          => 'nullable|string|max:2000',
            'status'                     => 'required|in:draft,self_assessment,supervisor_review,calibration,completed',
        ]);

        DB::transaction(function () use ($request, $id) {
            foreach ((array) $request->input('add_kpi_ids') as $kpiId) {
                $exists = DB::table('review_kpi_scores')->where('review_id',$id)->where('kpi_id',$kpiId)->exists();
                if (! $exists) {
                    DB::table('review_kpi_scores')->insert([
                        'score_id'   => (string) Str::uuid(),
                        'review_id'  => $id,
                        'kpi_id'     => $kpiId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            foreach ((array) $request->input('scores') as $scoreId => $row) {
                $target = DB::table('review_kpi_scores')->where('score_id',$scoreId)->where('review_id',$id)->first();
                if (! $target) {
                    continue;
                }

                $self       = $row['self_score']       ?? null;
                $supervisor = $row['supervisor_score'] ?? null;
                $peer       = $row['peer_score']       ?? null;

                DB::table('review_kpi_scores')->where('score_id',$scoreId)->update([
                    'self_score'       => $self !== null && $self !== '' ? $self : null,
                    'supervisor_score' => $supervisor !== null && $supervisor !== '' ? $supervisor : null,
                    'peer_score'       => $peer !== null && $peer !== '' ? $peer : null,
                    'weighted_score'   => $this->weightedScore($self, $supervisor, $peer),
                    'comments'         => $row['comments'] ?? null,
                    'updated_at'       => now(),
                ]);
            }

            $this->recalculateReviewTotals($id, $request);
        });

        return redirect()->route('performance.show',$id)->with('success','Scores saved.');
    }

    /**
     * Supervisor rating carries the most weight, then self, then peer.
     * Falls back gracefully when only some of the three are present.
     */
    private function weightedScore($self, $supervisor, $peer): ?float
    {
        $weights = ['supervisor' => 0.5, 'self' => 0.3, 'peer' => 0.2];
        $values  = ['supervisor' => $supervisor, 'self' => $self, 'peer' => $peer];

        $sum = 0.0;
        $totalWeight = 0.0;

        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $sum += ((float) $value) * $weights[$key];
                $totalWeight += $weights[$key];
            }
        }

        return $totalWeight > 0 ? round($sum / $totalWeight, 2) : null;
    }

    /**
     * Roll the per-KPI scores up onto the parent review, honouring each KPI's
     * weight from kpi_library.
     */
    private function recalculateReviewTotals(string $reviewId, Request $request): void
    {
        $rows = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k','rks.kpi_id','=','k.kpi_id')
            ->where('rks.review_id',$reviewId)
            ->select('rks.self_score','rks.supervisor_score','rks.peer_score','rks.weighted_score','k.weight')
            ->get();

        $avg = function (string $column) use ($rows): ?float {
            $vals = $rows->pluck($column)->filter(fn ($v) => $v !== null);

            return $vals->isEmpty() ? null : round((float) $vals->avg(), 2);
        };

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($rows as $row) {
            if ($row->weighted_score !== null) {
                $w = (float) ($row->weight ?: 1);
                $weightedSum += ((float) $row->weighted_score) * $w;
                $weightTotal += $w;
            }
        }

        $overall = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;

        $update = [
            'self_rating'        => $avg('self_score'),
            'supervisor_rating'  => $avg('supervisor_score'),
            'peer_rating'        => $avg('peer_score'),
            'overall_score'      => $overall,
            'strengths_text'     => $request->input('strengths_text'),
            'improvements_text'  => $request->input('improvements_text'),
            'status'             => $request->input('status'),
            'updated_at'         => now(),
        ];

        if ($request->input('status') === 'completed') {
            $update['signed_at'] = now();
            $update['digital_signature'] = hash('sha256', $reviewId.'|'.($this->currentEmployeeId() ?? 'system').'|'.now()->toIso8601String());
        }

        DB::table('performance_reviews')->where('review_id',$reviewId)->update($update);
    }
}
