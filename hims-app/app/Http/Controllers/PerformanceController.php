<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use App\Support\CycleStatus;
use App\Support\KpiWeighting;
use App\Support\ReviewStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceController extends Controller
{
    /* ── REVIEW AUTHORITY ──────────────────────────────────────────────
     |
     | Who may read, open and score a review is decided by identity — the
     | reporting line in `employees.supervisor_id` — not by `users.role`.
     | A role opens the door to the screens; it never decides whose review
     | you may touch.
     |
     | A review is one reviewer's assessment of one employee. There is no
     | self-assessment and no peer input: the only person who writes scores is
     | the account named in `reviewer_id`, which is always the account that
     | created the review. That account must be linked to an employee record —
     | an unlinked login cannot review anyone, because there would be nobody to
     | hold responsible for what it wrote.
     */

    /**
     * Constrain a review listing to what the acting account may legitimately
     * see: their own reviews, reviews they authored, and reviews of the people
     * who report to them.
     *
     * This deliberately does NOT defer to scopeToVisibleEmployees(): admin and
     * HR hold no blanket read over performance reviews. An HR account that
     * opened an exception review still sees it — through `pr.reviewer_id`,
     * because they wrote it, not because of their role.
     *
     * Requires the query to join `performance_reviews as pr` and
     * `employees as e`, which every review listing here already does.
     */
    private function scopeToVisibleReviews($query)
    {
        $actor = (string) ($this->currentEmployeeId() ?? '');

        return $query->where(function ($q) use ($actor) {
            $q->where('pr.employee_id', $actor)
                ->orWhere('pr.reviewer_id', $actor)
                ->orWhere('e.supervisor_id', $actor);
        });
    }

    /**
     * The single-record form of the rule above.
     */
    private function canViewReview(object $review): bool
    {
        $actor = $this->currentEmployeeId();

        if (! $actor) {
            return false;
        }

        if ($review->employee_id === $actor || ($review->reviewer_id ?? null) === $actor) {
            return true;
        }

        return DB::table('employees')->where('employee_id', $review->employee_id)->value('supervisor_id') === $actor;
    }

    /**
     * May this account write the scores on this review?
     *
     * Exactly one identity can: the employee named in `reviewer_id`. Not their
     * department, not an admin, not the subject. `employee_id !== $actor` is
     * checked again here even though storeReview() already refuses it, because a
     * `reviewer_id` that somehow came to equal `employee_id` must still never be
     * scoreable — this is the check that runs on every save.
     *
     * Says nothing about the cycle being open. Call reviewIsFrozen() for that;
     * the two are separate questions and the error messages differ.
     */
    private function canScoreReview(object $review): bool
    {
        $actor = $this->currentEmployeeId();

        return $actor !== null
            && ($review->reviewer_id ?? null) === $actor
            && $review->employee_id !== $actor;
    }

    /**
     * Has this review's cycle ended, making it read-only for good?
     *
     * The single place the freeze is decided, so the score screen and the save
     * handler can never disagree about it.
     *
     * @see ReviewStatus for why the rule is the date rather than a stored value
     */
    private function reviewIsFrozen(object $review): bool
    {
        return ReviewStatus::cycleHasEnded($review->cycle_end_date ?? null);
    }

    /**
     * Stamp each row of a review listing with its effective status and whether
     * the acting identity can still score it, so a list shows a Score button
     * only where the button would actually work.
     *
     * Both come out of the same place the save handler uses, which is what keeps
     * a table from offering an action that 403s or a badge that contradicts the
     * screen behind it.
     *
     * Requires the listing to select `rc.end_date as cycle_end_date` — without
     * it every row reads as an open cycle. All three callers do.
     */
    private function markScoreable($reviews)
    {
        $stamp = function ($review) {
            $review->effective_status = ReviewStatus::of($review->status ?? null, $review->cycle_end_date ?? null);
            $review->can_score = $review->effective_status !== ReviewStatus::COMPLETED
                && $this->canScoreReview($review);

            return $review;
        };

        // `through()` on a paginator, `map()` on a plain collection — both list
        // shapes appear here and both need the same stamp.
        return method_exists($reviews, 'through') ? $reviews->through($stamp) : $reviews->map($stamp);
    }

    /**
     * Stamps each cycle with its effective status.
     *
     * The stored column cannot see the calendar, so anything that displays a
     * cycle or counts one goes through here. Kept beside markScoreable(), which
     * does the same job for a review, for the same reason.
     */
    private function markCycleStatus($cycles)
    {
        $stamp = function ($cycle) {
            $cycle->effective_status = CycleStatus::of($cycle->status ?? null, $cycle->end_date ?? null);

            return $cycle;
        };

        return method_exists($cycles, 'through') ? $cycles->through($stamp) : $cycles->map($stamp);
    }

    /* ── INDEX ── */
    public function index()
    {
        $stats = [
            // Cycles that are open today — the stored column says 'active' until
            // somebody edits it, which for a cycle that ended in March is never.
            'active' => CycleStatus::whereNotEnded(
                DB::table('review_cycles')->where('status', CycleStatus::ACTIVE)
            )->count(),
            // Drafts somebody can still act on. A draft whose cycle has closed is
            // frozen half-written and will never be finished, so counting it here
            // would be a permanent nag about work nobody is allowed to do.
            'pending' => DB::table('performance_reviews as pr')
                ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
                ->where('pr.status', ReviewStatus::DRAFT)
                ->where(function ($q) {
                    $q->whereNull('rc.end_date')->orWhere('rc.end_date', '>=', ReviewStatus::today());
                })
                ->count(),
            'pips' => DB::table('performance_improvement_plans')->where('status', 'active')->count(),
        ];

        $cycles = $this->markCycleStatus(
            DB::table('review_cycles as rc')
                ->leftJoin('performance_reviews as pr', 'rc.cycle_id', '=', 'pr.cycle_id')
                ->select('rc.*', DB::raw('COUNT(pr.review_id) as reviews_count'))
                ->groupBy('rc.cycle_id')
                ->orderByDesc('rc.start_date')->get()
        );

        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as r', 'pr.reviewer_id', '=', 'r.employee_id')
            ->select('pr.review_id', 'pr.employee_id', 'pr.reviewer_id', 'pr.status', 'pr.overall_score', 'pr.review_type',
                'pr.is_exception_review', 'pr.exception_basis', 'pr.exception_reason',
                'e.first_name as employee_first', 'e.last_name as employee_last', 'e.position_title',
                DB::raw("CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) as reviewer_name"),
                'rc.cycle_name', 'rc.end_date as cycle_end_date');

        $reviews = $this->markScoreable(
            $this->scopeToVisibleReviews($reviews)->orderByDesc('pr.updated_at')->limit(20)->get()
        );

        return view('performance.index', compact('stats', 'cycles', 'reviews'));
    }

    /* ── SHOW REVIEW ── */
    public function show($id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as r', 'pr.reviewer_id', '=', 'r.employee_id')
            ->where('pr.review_id', $id)
            ->select('pr.*', 'e.first_name', 'e.last_name', 'e.position_title', 'e.department_id',
                'rc.cycle_name', 'rc.cycle_type', 'rc.end_date as cycle_end_date',
                DB::raw("CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) as reviewer_name"))
            ->first();

        abort_if(! $review, 404);

        // The route is open to all authenticated users; what it returns is not.
        // Reading a review means it is yours, you wrote it, or the subject
        // reports to you — a role grants no extra reach here.
        abort_unless($this->canViewReview($review), 403, 'You do not have access to that review.');

        $kpi_scores = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k', 'rks.kpi_id', '=', 'k.kpi_id')
            ->where('rks.review_id', $id)
            // Named rather than `select *`: the view reads k.weight, and an
            // unqualified join hands it whichever same-named column comes last.
            // `rks.weighted_score` is deliberately absent — the page stopped
            // displaying it, and a selected column nothing reads is the start of
            // the next stale-column bug. It is still written by saveScores() and
            // still read by the gap analysis; it is just not this page's business.
            ->select('rks.score_id', 'rks.supervisor_score',
                'rks.comments', 'k.kpi_name', 'k.kpi_category', 'k.weight')
            ->get();

        // What each KPI's weight is actually worth on this review, and how much of
        // the sheet has been filled in. Both are properties of the set rather than
        // of any one row, so they cannot be derived inside the @foreach.
        // displayShares(), not shares(): the column is read down and added up, so
        // the printed integers have to total 100 rather than each rounding alone.
        $weight_shares = KpiWeighting::displayShares($kpi_scores);
        $rated_count = KpiWeighting::ratedCount($kpi_scores);

        $goals = DB::table('review_goals')->where('review_id', $id)->get();

        $pip = DB::table('performance_improvement_plans')->where('triggered_by_review', $id)->first();

        $effective_status = ReviewStatus::of($review->status, $review->cycle_end_date);

        // Whether to offer the Score button at all. A frozen review has no button
        // even for its own reviewer — the page they would land on is read-only.
        $can_score = $effective_status !== ReviewStatus::COMPLETED && $this->canScoreReview($review);
        $is_subject = $review->employee_id === $this->currentEmployeeId();
        $can_respond = $is_subject
            && $effective_status !== ReviewStatus::DRAFT
            && ! $review->employee_acknowledged_at;

        return view('performance.show', compact('review', 'kpi_scores', 'goals', 'pip',
            'can_score', 'can_respond', 'is_subject', 'effective_status', 'weight_shares', 'rated_count'));
    }

    /** Save the subject's response and optionally acknowledge a completed review. */
    public function storeEmployeeResponse(Request $request, $id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where('pr.review_id', $id)
            ->select('pr.*', 'rc.end_date as cycle_end_date')
            ->first();

        abort_if(! $review, 404);
        abort_unless(
            $review->employee_id === $this->currentEmployeeId(),
            403,
            'Only the employee named on this review can respond to it.'
        );

        $effectiveStatus = ReviewStatus::of($review->status, $review->cycle_end_date);

        if ($effectiveStatus === ReviewStatus::DRAFT) {
            return back()->with('error', 'You can respond after the reviewer marks the review as finished.');
        }

        if ($review->employee_acknowledged_at) {
            return back()->with('error', 'This review has already been acknowledged and the employee response is locked.');
        }

        $validated = $request->validate([
            'employee_response' => ['nullable', 'string', 'max:4000'],
            'acknowledge' => ['nullable', 'boolean'],
        ]);
        $acknowledge = (bool) ($validated['acknowledge'] ?? false);

        if ($acknowledge && $effectiveStatus !== ReviewStatus::COMPLETED) {
            return back()->withInput()->with('error', 'The review can be acknowledged after the cycle closes.');
        }

        $response = trim((string) ($validated['employee_response'] ?? '')) ?: null;
        $now = now();
        $before = [
            'employee_response' => $review->employee_response,
            'employee_response_submitted_at' => $review->employee_response_submitted_at,
            'employee_acknowledged_at' => $review->employee_acknowledged_at,
        ];
        $after = [
            'employee_response' => $response,
            'employee_response_submitted_at' => $response !== null ? $now : null,
            'employee_acknowledged_at' => $acknowledge ? $now : null,
        ];

        DB::table('performance_reviews')->where('review_id', $id)->update($after + ['updated_at' => $now]);

        AuditTrail::record(
            $acknowledge ? 'review_acknowledged' : 'review_employee_response',
            'performance_reviews',
            $id,
            beforeState: $before,
            afterState: $after,
        );

        return back()->with('success', $acknowledge ? 'Review acknowledged.' : 'Employee response saved.');
    }

    /* ── STORE CYCLE ── */
    public function storeCycle(Request $request)
    {
        $validated = $request->validate([
            'cycle_name' => 'required|string|max:100',
            'cycle_type' => 'required|in:annual,semi_annual,quarterly,probationary',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $creatorId = $this->currentEmployeeId();
        if (! $creatorId) {
            return back()->withInput()->with('error', 'Your account is not linked to an employee record, so it cannot create a review cycle.');
        }

        $cycleId = (string) Str::uuid();
        $after = [
            'cycle_id' => $cycleId,
            'cycle_name' => $validated['cycle_name'],
            'cycle_type' => $validated['cycle_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'planned',
            'created_by' => $creatorId,
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::transaction(function () use ($cycleId, $after) {
            DB::table('review_cycles')->insert($after);
            AuditTrail::record('review_cycle_created', 'review_cycles', $cycleId, afterState: $after);
        });

        return redirect()->route('performance.index')->with('success', 'Review cycle created successfully.');
    }

    /* ── SHOW CYCLE ── */
    public function showCycle($id)
    {
        $cycle = DB::table('review_cycles as rc')
            ->leftJoin('employees as c', 'rc.created_by', '=', 'c.employee_id')
            ->where('rc.cycle_id', $id)
            ->select('rc.*', DB::raw("CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) as created_by_name"))
            ->first();

        abort_if(! $cycle, 404);

        $cycle->effective_status = CycleStatus::of($cycle->status, $cycle->end_date);

        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as r', 'pr.reviewer_id', '=', 'r.employee_id')
            ->where('pr.cycle_id', $id)
            ->select('pr.review_id', 'pr.employee_id', 'pr.reviewer_id', 'pr.status', 'pr.overall_score',
                'pr.supervisor_rating', 'pr.review_type',
                'pr.is_exception_review', 'pr.exception_basis', 'pr.exception_reason',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title', 'd.name as department_name', 'rc.end_date as cycle_end_date',
                DB::raw("CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) as reviewer_name"));

        $reviews = $this->markScoreable(
            $this->scopeToVisibleReviews($reviews)->orderBy('e.last_name')->get()
        );

        // Finished vs. draft, not completed vs. in-progress: every review in a
        // cycle shares one end date, so a completed count would read 0 for the
        // whole cycle and then flip to 100% overnight — true, and useless. What a
        // cycle owner actually wants to know is how much of the work is done.
        $stats = [
            'total' => $reviews->count(),
            'finished' => $reviews->where('status', ReviewStatus::FINISHED)->count(),
            'draft' => $reviews->where('status', '!=', ReviewStatus::FINISHED)->count(),
            'avg_score' => round((float) $reviews->whereNotNull('overall_score')->avg('overall_score'), 2),
        ];

        return view('performance.cycles.show', compact('cycle', 'reviews', 'stats'));
    }

    /* ── UPDATE CYCLE ── */
    public function updateCycle(Request $request, $id)
    {
        $before = DB::table('review_cycles')->where('cycle_id', $id)->first();
        abort_if(! $before, 404);

        $validated = $request->validate([
            'cycle_name' => 'required|string|max:100',
            'cycle_type' => 'required|in:annual,semi_annual,quarterly,probationary',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:planned,active,closed,archived',
        ]);

        $after = [
            'cycle_name' => $validated['cycle_name'],
            'cycle_type' => $validated['cycle_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($id, $before, $after) {
            DB::table('review_cycles')->where('cycle_id', $id)->update($after);
            AuditTrail::record('review_cycle_updated', 'review_cycles', $id,
                beforeState: (array) $before, afterState: $after);
        });

        // Back to the Performance index, not the cycle: the edit is launched from
        // a modal on whichever page the user was already on, so landing them on a
        // different page than they started from reads as a redirect they did not ask for.
        return redirect()->route('performance.index')->with('success', 'Review cycle updated.');
    }

    /* ── REVIEWS INDEX ── */
    public function reviewsIndex()
    {
        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as r', 'pr.reviewer_id', '=', 'r.employee_id')
            ->select('pr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                DB::raw("CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) AS reviewer_name"),
                'e.position_title', 'rc.cycle_name', 'rc.end_date as cycle_end_date');

        $reviews = $this->markScoreable(
            $this->scopeToVisibleReviews($reviews)->orderByDesc('pr.updated_at')->paginate(20)
        );

        return view('performance.reviews.index', compact('reviews'));
    }

    /* ── CREATE REVIEW FORM ── */
    public function createReview(Request $request)
    {
        return view('performance.reviews.create', [
            'employees' => $this->reviewableEmployees(),
            // Ended cycles are excluded, not just non-active ones: a review
            // opened inside a finished cycle is born frozen — ReviewStatus would
            // read it as completed the moment it was saved — so the dropdown must
            // not offer one.
            'cycles' => CycleStatus::whereNotEnded(
                DB::table('review_cycles')->whereIn('status', CycleStatus::OPEN_FOR_REVIEWS)
            )->orderByDesc('start_date')->get(),
            'kpis' => DB::table('kpi_library')->where('is_active', true)->orderBy('kpi_category')->orderBy('kpi_name')->get(),
            'selectedCycle' => $request->query('cycle'),
        ]);
    }

    /**
     * The people this account may start a review for.
     *
     * Everyone gets their direct reports. Admin and HR additionally get the
     * employees the chain cannot cover — no supervisor recorded, a supervisor
     * who is away, whose HIMS account is incomplete, or who is themselves under
     * review in a live cycle.
     * The list is a convenience; storeReview() re-checks the rule against the
     * cycle that was actually chosen, which is stricter than anything that can
     * be known here.
     */
    private function reviewableEmployees()
    {
        $actor = (string) ($this->currentEmployeeId() ?? '');

        $employees = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->leftJoin('employees as s', 'e.supervisor_id', '=', 's.employee_id')
            ->where('e.employment_status', 'active')
            ->where('e.employee_id', '!=', $actor)
            ->select('e.employee_id', 'e.first_name', 'e.last_name', 'e.position_title',
                'e.supervisor_id', 'd.name as department_name',
                DB::raw("CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')) as supervisor_name"));

        $seesAll = auth()->user()?->seesWholeOrganisation() ?? false;

        $employees->where(function ($q) use ($actor, $seesAll) {
            $q->where('e.supervisor_id', $actor);

            if (! $seesAll) {
                return;
            }

            $q->orWhereNull('e.supervisor_id')
                ->orWhereIn('s.employment_status', ['on_leave', 'suspended', 'resigned'])
                ->orWhereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('users as su')
                        ->whereColumn('su.employee_id', 'e.supervisor_id')
                        ->whereIn('su.role', ['supervisor', 'hr_manager', 'admin']);
                })
                ->orWhereExists(function ($sub) {
                    CycleStatus::whereNotEnded(
                        $sub->select(DB::raw(1))
                            ->from('performance_reviews as spr')
                            ->join('review_cycles as src', 'spr.cycle_id', '=', 'src.cycle_id')
                            ->whereColumn('spr.employee_id', 'e.supervisor_id')
                            ->whereIn('src.status', CycleStatus::OPEN_FOR_REVIEWS),
                        'src.end_date'
                    );
                });
        });

        return $employees->orderBy('e.first_name')->get()->map(function ($employee) use ($actor) {
            // Flagged here rather than recomputed in Blade so the form can warn
            // before submission that this one will be recorded as an exception.
            $employee->outside_chain = $employee->supervisor_id !== $actor;

            return $employee;
        });
    }

    /* ── STORE REVIEW ── */
    public function storeReview(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'cycle_id' => 'required|string|exists:review_cycles,cycle_id',
            'review_type' => 'required|in:standard,probationary,promotion',
            'exception_reason' => 'nullable|string|max:500',
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'string|exists:kpi_library,kpi_id',
        ]);

        // The creator is the reviewer, always. There is no "assign someone else"
        // field: authority to write a review is the acting identity's, and it
        // cannot be handed to a person who never agreed to hold it.
        $reviewerId = $this->currentEmployeeId();

        if (! $reviewerId) {
            return back()->withInput()->with('error', 'Your account is not linked to an employee record, so it cannot author a review.');
        }

        if ($reviewerId === $request->employee_id) {
            return back()->withInput()->with('error', 'You cannot review yourself.');
        }

        // The dropdown already hides ended cycles, but the dropdown is a
        // convenience and a POST is not obliged to have used it. A review created
        // in a cycle whose end date has passed is frozen the instant it exists —
        // ReviewStatus would read it as completed and no one could ever score it —
        // so it is refused here rather than created as a dead row.
        if (CycleStatus::hasEnded(DB::table('review_cycles')->where('cycle_id', $request->cycle_id)->value('end_date'))) {
            return back()->withInput()->with('error', 'That review cycle has ended, so no new reviews can be opened in it.');
        }

        $exceptionBasis = null;

        if (! $this->isLegitimateReviewerFor($request->employee_id)) {
            $exceptionBasis = $this->reviewExceptionBasis($request->employee_id, $request->cycle_id);

            if (! $exceptionBasis) {
                return back()->withInput()->with('error', 'You are not that employee\'s supervisor, so you cannot open their review.');
            }

            if (! trim((string) $request->exception_reason)) {
                return back()->withInput()->with('error', 'Reviewing outside the reporting line needs a reason on the record.');
            }
        }

        // One reviewer, one review, per employee per cycle — whatever the type.
        // A repeat create is an edit: the existing record is where the work goes,
        // not a second row nobody can tell apart from the first. Deliberately
        // ignores `review_type`, so an account cannot file a "standard" and then a
        // "promotion" review on the same person in the same cycle and end up with
        // two of its own opinions counted as two people's.
        $existing = DB::table('performance_reviews')
            ->where('employee_id', $request->employee_id)
            ->where('cycle_id', $request->cycle_id)
            ->where('reviewer_id', $reviewerId)
            ->value('review_id');

        if ($existing) {
            return redirect()->route('performance.reviews.score', $existing)
                ->with('success', 'You already opened a review for this employee in this cycle — continuing where you left off.');
        }

        $reviewId = (string) Str::uuid();

        DB::transaction(function () use ($request, $reviewId, $reviewerId, $exceptionBasis) {
            $reviewState = [
                'review_id' => $reviewId,
                'employee_id' => $request->employee_id,
                'cycle_id' => $request->cycle_id,
                'reviewer_id' => $reviewerId,
                'review_type' => $request->review_type,
                'is_exception_review' => (bool) $exceptionBasis,
                'exception_basis' => $exceptionBasis,
                'exception_reason' => $exceptionBasis ? trim((string) $request->exception_reason) : null,
                'status' => ReviewStatus::DRAFT,
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('performance_reviews')->insert($reviewState);

            // Pre-attach the chosen KPIs so the scoring form has rows to fill.
            foreach ((array) $request->kpi_ids as $kpiId) {
                DB::table('review_kpi_scores')->insert([
                    'score_id' => (string) Str::uuid(),
                    'review_id' => $reviewId,
                    'kpi_id' => $kpiId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            AuditTrail::record('review_created', 'performance_reviews', $reviewId, afterState: [
                'employee_id' => $reviewState['employee_id'],
                'cycle_id' => $reviewState['cycle_id'],
                'reviewer_id' => $reviewState['reviewer_id'],
                'review_type' => $reviewState['review_type'],
                'is_exception_review' => $reviewState['is_exception_review'],
                'status' => $reviewState['status'],
                'kpi_ids' => array_values((array) $request->kpi_ids),
            ]);
        });

        if ($exceptionBasis) {
            // Logged through the existing audit trail, not a second system.
            AuditTrail::record('review_exception', 'performance_reviews', $reviewId, afterState: [
                'employee_id' => $request->employee_id,
                'cycle_id' => $request->cycle_id,
                'reviewer_id' => $reviewerId,
                'exception_basis' => $exceptionBasis,
                'exception_reason' => trim((string) $request->exception_reason),
            ]);
        }

        return redirect()->route('performance.reviews.score', $reviewId)->with('success', 'Review created. Enter KPI scores below.');
    }

    /* ── SCORING FORM ── */
    public function scoreReview($id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where('pr.review_id', $id)
            ->select('pr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'e.position_title', 'rc.cycle_name',
                'rc.end_date as cycle_end_date')
            ->first();

        abort_if(! $review, 404);

        // One review, one reviewer. Role got you as far as the route; your name
        // on this row is what gets you the form. Everyone else — the subject,
        // their colleagues, an admin who is not the reviewer — reads it on the
        // show page instead.
        abort_unless(
            $this->canScoreReview($review),
            403,
            'Only the reviewer named on this review can score it.'
        );

        // Past the cycle's end date the scores are evidence, not a draft. Sent to
        // the show page rather than 403'd because that page is the read-only view
        // of exactly what they came to look at.
        if ($this->reviewIsFrozen($review)) {
            return redirect()->route('performance.show', $id)
                ->with('error', 'This review closed when its cycle ended and can no longer be edited.');
        }

        $scores = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k', 'rks.kpi_id', '=', 'k.kpi_id')
            ->where('rks.review_id', $id)
            ->select('rks.*', 'k.kpi_name', 'k.kpi_category', 'k.description', 'k.target_value', 'k.unit', 'k.weight')
            ->orderBy('k.kpi_category')->orderBy('k.kpi_name')->get();

        $availableKpis = DB::table('kpi_library')
            ->where('is_active', true)
            ->whereNotIn('kpi_id', $scores->pluck('kpi_id')->all() ?: [''])
            ->orderBy('kpi_category')->orderBy('kpi_name')->get();

        // The shares are of the scores as last saved, not as currently typed —
        // there is no JS recalculation on this form. That is why each row reads
        // "counts N% of the score" rather than promising what it will become.
        //
        // displayShares(), not shares(): a reviewer reads these figures down the
        // column and adds them up, so the printed integers have to total 100
        // rather than each rounding on its own.
        $weight_shares = KpiWeighting::displayShares($scores);
        $rated_count = KpiWeighting::ratedCount($scores);

        return view('performance.reviews.score', compact('review', 'scores', 'availableKpis',
            'weight_shares', 'rated_count'));
    }

    /* ── SAVE SCORES ── */
    public function saveScores(Request $request, $id)
    {
        $review = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where('pr.review_id', $id)
            ->select('pr.*', 'rc.end_date as cycle_end_date')
            ->first();

        abort_if(! $review, 404);

        // Being admin, HR or a supervisor got you through the route middleware.
        // Writing the scores needs your name on this review.
        abort_unless(
            $this->canScoreReview($review),
            403,
            'Only the reviewer named on this review can score it.'
        );

        // Checked here as well as on the form, and this is the one that matters:
        // the form check is a courtesy to whoever left a tab open, this is what
        // makes the freeze real against a replayed POST.
        if ($this->reviewIsFrozen($review)) {
            return redirect()->route('performance.show', $id)
                ->with('error', 'This review closed when its cycle ended and can no longer be edited.');
        }

        $request->validate([
            'scores' => 'nullable|array',
            'scores.*.supervisor_score' => 'nullable|numeric|min:1|max:5',
            'scores.*.comments' => 'nullable|string|max:1000',
            'add_kpi_ids' => 'nullable|array',
            'add_kpi_ids.*' => 'string|exists:kpi_library,kpi_id',
            'strengths_text' => 'nullable|string|max:2000',
            'improvements_text' => 'nullable|string|max:2000',
            // Only the two a person may set. `completed` is not on this list
            // because it is not anybody's to choose — the cycle's end date
            // decides it. Posting it fails validation rather than being quietly
            // ignored, so a hand-crafted request gets an answer instead of a
            // silent no-op.
            'status' => 'required|in:'.implode(',', ReviewStatus::SETTABLE),
        ]);

        DB::transaction(function () use ($request, $id) {
            $before = $this->scoreAuditState($id);

            foreach ((array) $request->input('add_kpi_ids') as $kpiId) {
                $exists = DB::table('review_kpi_scores')->where('review_id', $id)->where('kpi_id', $kpiId)->exists();
                if (! $exists) {
                    DB::table('review_kpi_scores')->insert([
                        'score_id' => (string) Str::uuid(),
                        'review_id' => $id,
                        'kpi_id' => $kpiId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            foreach ((array) $request->input('scores') as $scoreId => $row) {
                $target = DB::table('review_kpi_scores')->where('score_id', $scoreId)->where('review_id', $id)->first();
                if (! $target) {
                    continue;
                }

                $supervisor = $this->cleanScore($row['supervisor_score'] ?? null);

                DB::table('review_kpi_scores')->where('score_id', $scoreId)->update([
                    'supervisor_score' => $supervisor,
                    // `weighted_score` is the reviewer's score, unchanged. The
                    // column outlives the 50/30/20 blend it was named for because
                    // CompetencyGapAnalysisService and the gap-analysis employee
                    // view read it as "the score for this KPI", which is still
                    // exactly what it holds.
                    'weighted_score' => $supervisor,
                    'comments' => $row['comments'] ?? null,
                    'updated_at' => now(),
                ]);
            }

            $this->recalculateReviewTotals($id, [
                'strengths_text' => $request->input('strengths_text'),
                'improvements_text' => $request->input('improvements_text'),
                'status' => $request->input('status'),
            ]);

            // Score, comments, roll-ups and status move together. Capturing the
            // two snapshots in this transaction means the named reviewer owns
            // one coherent change instead of several partial audit events.
            AuditTrail::record(
                'review_scores_updated',
                'performance_reviews',
                $id,
                beforeState: $before,
                afterState: $this->scoreAuditState($id),
            );
        });

        $message = $request->input('status') === ReviewStatus::FINISHED
            ? 'Review marked as finished. You can still edit it until the cycle ends.'
            : 'Draft saved.';

        return redirect()->route('performance.show', $id)->with('success', $message);
    }

    /** The reviewer-controlled portion of a review, kept as one audit payload. */
    private function scoreAuditState(string $reviewId): array
    {
        $review = DB::table('performance_reviews')->where('review_id', $reviewId)->first([
            'review_id', 'status', 'supervisor_rating', 'overall_score',
            'strengths_text', 'improvements_text', 'signed_at', 'digital_signature',
        ]);

        $scores = DB::table('review_kpi_scores')
            ->where('review_id', $reviewId)
            ->orderBy('score_id')
            ->get(['score_id', 'kpi_id', 'supervisor_score', 'weighted_score', 'comments'])
            ->map(fn ($score) => (array) $score)
            ->all();

        return [
            'review' => $review ? (array) $review : null,
            'kpi_scores' => $scores,
        ];
    }

    /**
     * Empty form fields are absent scores, not zeroes.
     */
    private function cleanScore($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Roll the per-KPI scores up onto the parent review, honouring each KPI's
     * weight from kpi_library.
     *
     * `supervisor_rating` is never posted — it is the plain average of the
     * per-KPI scores, while `overall_score` is the same numbers weighted by each
     * KPI's importance. Two questions, two answers: "how did they do across the
     * board" and "how did they do on what matters".
     *
     * `$extra` carries only the fields the reviewer is entitled to set. A field
     * absent from the array is left exactly as stored.
     */
    private function recalculateReviewTotals(string $reviewId, array $extra = []): void
    {
        $rows = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k', 'rks.kpi_id', '=', 'k.kpi_id')
            ->where('rks.review_id', $reviewId)
            ->select('rks.supervisor_score', 'rks.weighted_score', 'k.weight')
            ->get();

        $scores = $rows->pluck('supervisor_score')->filter(fn ($v) => $v !== null);

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($rows as $row) {
            if ($row->weighted_score !== null) {
                // KpiWeighting owns the `weight ?: 1` rule so the percentage the
                // review screens print is the same coefficient applied here.
                $w = KpiWeighting::effectiveWeight($row->weight);
                $weightedSum += ((float) $row->weighted_score) * $w;
                $weightTotal += $w;
            }
        }

        $update = [
            'supervisor_rating' => $scores->isEmpty() ? null : round((float) $scores->avg(), 2),
            'overall_score' => $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null,
            'updated_at' => now(),
        ];

        if (array_key_exists('strengths_text', $extra)) {
            $update['strengths_text'] = $extra['strengths_text'];
        }

        if (array_key_exists('improvements_text', $extra)) {
            $update['improvements_text'] = $extra['improvements_text'];
        }

        if (array_key_exists('status', $extra)) {
            $update['status'] = $extra['status'];
        }

        // Signed on `finished`, which is the reviewer saying they are done — the
        // last moment a person actively attests to the contents. `completed`
        // cannot sign anything: no human is present when a date rolls over, and a
        // signature nobody stood behind is worse than none. Re-signed on every
        // finish, because a finished review stays editable and the signature must
        // cover what it currently says rather than an earlier version of it.
        if (($extra['status'] ?? null) === ReviewStatus::FINISHED) {
            $update['signed_at'] = now();
            $update['digital_signature'] = hash('sha256', $reviewId.'|'.($this->currentEmployeeId() ?? 'system').'|'.now()->toIso8601String());
        }

        // Back to draft withdraws the sign-off with it. Leaving the old signature
        // in place would leave a review that reads as attested while its author
        // has openly reopened it.
        if (($extra['status'] ?? null) === ReviewStatus::DRAFT) {
            $update['signed_at'] = null;
            $update['digital_signature'] = null;
        }

        DB::table('performance_reviews')->where('review_id', $reviewId)->update($update);
    }
}
