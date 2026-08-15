<?php

namespace App\Http\Controllers;

use App\Services\TrainingAssignmentService;
use App\Support\CredentialStatus;
use App\Support\CycleStatus;
use Illuminate\Support\Facades\DB;

/**
 * Objective 7 — decision-support dashboards.
 *
 * One route, four audiences. Admin and HR see the whole organisation, a
 * supervisor (department head) sees only their own department, and staff see
 * their own development picture. The view switches on $role.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role ?: 'staff';

        return match (true) {
            $user->isAdmin() => view('dashboard', $this->adminData()),
            $user->isHrManager() => view('dashboard', $this->hrData()),
            $user->isSupervisor() => view('dashboard', $this->supervisorData($user->departmentId())),
            default => view('dashboard', $this->staffData($user->employee_id)),
        };
    }

    /* ────────────────────────────── admin ────────────────────────────── */

    /**
     * Organisation-wide operational health: workforce totals, compliance risk,
     * module activity, and system accounts.
     */
    private function adminData(): array
    {
        $data = $this->organisationWideData();

        $data['scope'] = 'Whole organisation';
        $data['role'] = 'admin';
        $data['heading'] = 'Administrator Dashboard';
        $data['subheading'] = 'Hospital-wide workforce, compliance, and system overview.';

        $data['system'] = [
            'user_accounts' => DB::table('users')->count(),
            'unlinked_accounts' => DB::table('users')->whereNull('employee_id')->count(),
            'admins' => DB::table('users')->where('role', 'admin')->count(),
            'departments' => DB::table('departments')->count(),
        ];

        $data['accounts_by_role'] = DB::table('users')
            ->select('role', DB::raw('COUNT(*) as cnt'))
            ->groupBy('role')->orderByDesc('cnt')->get();

        return $data;
    }

    /* ──────────────────────────────── HR ────────────────────────────── */

    /**
     * HR cares about the people pipeline: reviews to chase, credentials about to
     * lapse, training uptake, and succession cover.
     */
    private function hrData(): array
    {
        $data = $this->organisationWideData();

        $data['scope'] = 'Whole organisation';
        $data['role'] = 'hr_manager';
        $data['heading'] = 'HR Dashboard';
        $data['subheading'] = 'Workforce records, performance cycles, competency and training compliance.';

        $data['hiring'] = [
            'new_hires_90d' => DB::table('employees')->whereDate('hire_date', '>=', now()->subDays(90)->toDateString())->count(),
            'on_leave' => DB::table('employees')->where('employment_status', 'on_leave')->count(),
            'terminated' => DB::table('employees')->where('employment_status', 'terminated')->count(),
        ];

        // Progress on work somebody can still do. A cycle past its end date is
        // closed however its column reads, and its reviews are frozen — charting
        // them as "in progress" would show a bar nobody can move.
        $data['review_progress'] = CycleStatus::whereNotEnded(
            DB::table('performance_reviews as pr')
                ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
                ->where('rc.status', CycleStatus::ACTIVE),
            'rc.end_date'
        )->select('pr.status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('pr.status')->get();

        $data['unassessed_employees'] = DB::table('employees as e')
            ->leftJoin('competency_assessments as ca', 'e.employee_id', '=', 'ca.employee_id')
            ->where('e.employment_status', 'active')
            ->whereNull('ca.assessment_id')
            ->count();

        return $data;
    }

    /**
     * Shared organisation-wide blocks for admin and HR.
     *
     * "Pending" is a statement about the cycle, not about the status column.
     * Nothing writes 'completed' any more — a review is completed because its
     * cycle's end date has passed (see App\Support\ReviewStatus) — so filtering
     * the column alone would count every frozen review in every closed cycle as
     * outstanding work, forever. The supervisor tile below counts the same way.
     *
     * @return array<string, mixed>
     */
    private function organisationWideData(): array
    {
        $stats = [
            'headcount' => DB::table('employees')->where('employment_status', 'active')->count(),
            'pending_reviews' => CycleStatus::whereNotEnded(
                DB::table('performance_reviews as pr')
                    ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id'),
                'rc.end_date'
            )->count(),
            'expiring_credentials' => CredentialStatus::whereExpiring(DB::table('employee_credentials'))->count(),
            'expired_credentials' => CredentialStatus::whereExpired(DB::table('employee_credentials'))->count(),
            'active_enrollments' => DB::table('course_enrollments')->whereIn('status', ['enrolled', 'in_progress'])->count(),
            'critical_gaps' => DB::table('competency_assessments')->where('gap', '<=', -2)->count(),
            'upcoming_sessions' => DB::table('training_sessions')
                ->where('status', 'scheduled')
                ->whereDate('session_date', '>=', now()->toDateString())
                ->count(),
            // Deliberately delegated rather than written here: the Required
            // Training tab already decides what "overdue" means, and two
            // definitions of one word drift.
            'overdue_training' => app(TrainingAssignmentService::class)->overdueCount(),
        ];

        $headcount_by_department = DB::table('departments as d')
            ->leftJoin('employees as e', function ($j) {
                $j->on('d.department_id', '=', 'e.department_id')
                    ->where('e.employment_status', '=', 'active');
            })
            ->select('d.department_id', 'd.name', DB::raw('COUNT(e.employee_id) as headcount'))
            ->groupBy('d.department_id', 'd.name')
            ->orderByDesc('headcount')->get();

        $competency_hotspots = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->select('c.competency_name', 'c.required_proficiency',
                DB::raw('ROUND(AVG(ca.current_proficiency),2) as avg_proficiency'),
                DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                DB::raw('COUNT(DISTINCT ca.employee_id) as assessed'))
            ->groupBy('c.competency_id', 'c.competency_name', 'c.required_proficiency')
            ->havingRaw('AVG(ca.gap) < 0')
            ->orderBy('avg_gap')->limit(6)->get();

        $recent_reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->select('pr.review_id', 'pr.status', 'pr.overall_score',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                'rc.cycle_name')
            ->orderByDesc('pr.updated_at')->limit(5)->get();

        $risk_positions = DB::table('critical_positions as cp')
            ->join('departments as d', 'cp.department_id', '=', 'd.department_id')
            ->leftJoin('succession_candidates as sc', 'cp.position_id', '=', 'sc.position_id')
            ->select('cp.position_id', 'cp.position_title', 'cp.vacancy_risk', 'd.name as department_name',
                DB::raw('COUNT(sc.candidate_id) as candidates'),
                DB::raw("SUM(CASE WHEN sc.readiness_level = 'ready_now' THEN 1 ELSE 0 END) as ready_successors"))
            ->whereIn('cp.vacancy_risk', ['high', 'critical'])
            ->groupBy('cp.position_id', 'cp.position_title', 'cp.vacancy_risk', 'd.name')
            ->havingRaw("SUM(CASE WHEN sc.readiness_level = 'ready_now' THEN 1 ELSE 0 END) = 0")
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium')")
            ->limit(5)->get();

        return [
            'stats' => $stats,
            'headcount_by_department' => $headcount_by_department,
            'competency_hotspots' => $competency_hotspots,
            'recent_reviews' => $recent_reviews,
            'risk_positions' => $risk_positions,

            'credential_alerts' => $this->credentialAlerts(),
        ];
    }

    /* ────────────────────────── department head ───────────────────────── */

    /**
     * A supervisor's dashboard is the same shape, restricted to their own
     * department — this is what makes "department heads can check performance"
     * meaningful rather than everyone seeing everything.
     */
    private function supervisorData(?string $departmentId): array
    {
        if (! $departmentId) {
            return [
                'role' => 'supervisor',
                'scope' => 'No department linked',
                'heading' => 'Department Dashboard',
                'subheading' => 'Your account is not linked to a department yet, so there is nothing to show.',
                'stats' => [],
                'team' => collect(),
                'recent_reviews' => collect(),
                'competency_hotspots' => collect(),
                'credential_alerts' => collect(),

                'upcoming_sessions' => collect(),
            ];
        }

        $department = DB::table('departments')->where('department_id', $departmentId)->first();

        $teamSizeQuery = DB::table('employees as e')->where('e.employment_status', 'active');
        $pendingReviewsQuery = CycleStatus::whereNotEnded(
            DB::table('performance_reviews as pr')
                ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
                ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id'),
            'rc.end_date'
        );
        $expiringCredentialsQuery = CredentialStatus::whereExpiring(
            DB::table('employee_credentials as ec')
                ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id'),
            'ec.expiry_date'
        );
        $criticalGapsQuery = DB::table('competency_assessments as ca')
            ->join('employees as e', 'ca.employee_id', '=', 'e.employee_id')
            ->where('ca.gap', '<=', -2);
        $avgTeamScoreQuery = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->whereNotNull('pr.overall_score');

        $stats = [
            'team_size' => $this->scopeToVisibleEmployees($teamSizeQuery, 'e.employee_id')->count(),
            'pending_reviews' => $this->scopeToVisibleEmployees($pendingReviewsQuery, 'e.employee_id')->count(),
            'expiring_credentials' => $this->scopeToVisibleEmployees($expiringCredentialsQuery, 'e.employee_id')->count(),
            'critical_gaps' => $this->scopeToVisibleEmployees($criticalGapsQuery, 'e.employee_id')->count(),
            'avg_team_score' => round((float) $this->scopeToVisibleEmployees($avgTeamScoreQuery, 'e.employee_id')->avg('pr.overall_score'), 2),
        ];

        $teamQuery = DB::table('employees as e')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->leftJoin('performance_reviews as pr', function ($j) {
                $j->on('e.employee_id', '=', 'pr.employee_id');
            })
            ->leftJoin('competency_assessments as ca', 'e.employee_id', '=', 'ca.employee_id')
            ->where('e.employment_status', 'active')
            ->select('e.employee_id', 'e.first_name', 'e.last_name', 'e.position_title', 'r.role_name',
                DB::raw('MAX(pr.overall_score) as latest_score'),
                DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                DB::raw('COUNT(DISTINCT ca.assessment_id) as assessments'))
            ->groupBy('e.employee_id', 'e.first_name', 'e.last_name', 'e.position_title', 'r.role_name')
            ->orderBy('e.last_name');

        $team = $this->scopeToVisibleEmployees($teamQuery, 'e.employee_id')->get();

        $hotspotsQuery = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->join('employees as e', 'ca.employee_id', '=', 'e.employee_id')
            ->select('c.competency_name', 'c.required_proficiency',
                DB::raw('ROUND(AVG(ca.current_proficiency),2) as avg_proficiency'),
                DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                DB::raw('COUNT(DISTINCT ca.employee_id) as assessed'))
            ->groupBy('c.competency_id', 'c.competency_name', 'c.required_proficiency')
            ->havingRaw('AVG(ca.gap) < 0')
            ->orderBy('avg_gap');

        $competency_hotspots = $this->scopeToVisibleEmployees($hotspotsQuery, 'e.employee_id')->limit(6)->get();

        $recentReviewsQuery = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->select('pr.review_id', 'pr.status', 'pr.overall_score',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                'rc.cycle_name')
            ->orderByDesc('pr.updated_at');

        $recent_reviews = $this->scopeToVisibleEmployees($recentReviewsQuery, 'e.employee_id')->limit(5)->get();

        return [
            'role' => 'supervisor',
            'scope' => $department->name ?? 'Your team',
            'heading' => ($department->name ?? 'Team').' Dashboard',
            'subheading' => 'Performance, competency and training status for your direct reports.',
            'stats' => $stats,
            'team' => $team,
            'competency_hotspots' => $competency_hotspots,
            'recent_reviews' => $recent_reviews,
            'credential_alerts' => $this->credentialAlerts($departmentId),
            'upcoming_sessions' => $this->upcomingSessions(),
        ];
    }

    /* ─────────────────────────────── staff ───────────────────────────── */

    /**
     * A staff member's own development picture — no one else's data.
     */
    private function staffData(?string $employeeId): array
    {
        if (! $employeeId) {
            return [
                'role' => 'staff',
                'scope' => 'No employee profile linked',
                'heading' => 'My Dashboard',
                'subheading' => 'Your login is not linked to an employee profile yet. Ask HR to link it so your records appear here.',
                'stats' => [],
                'my_reviews' => collect(),
                'my_gaps' => collect(),
                'my_courses' => collect(),
                'my_credentials' => collect(),

                'upcoming_sessions' => $this->upcomingSessions(),
            ];
        }

        $stats = [
            'my_reviews' => DB::table('performance_reviews')->where('employee_id', $employeeId)->count(),
            'latest_score' => DB::table('performance_reviews')
                ->where('employee_id', $employeeId)
                ->whereNotNull('overall_score')
                ->orderByDesc('updated_at')->value('overall_score'),
            'courses_completed' => DB::table('course_enrollments')->where('employee_id', $employeeId)->where('status', 'completed')->count(),
            'courses_active' => DB::table('course_enrollments')->where('employee_id', $employeeId)->whereIn('status', ['enrolled', 'in_progress'])->count(),
            'cpd_hours_year' => round((float) DB::table('cpd_records')
                ->where('employee_id', $employeeId)
                ->whereDate('date_earned', '>=', now()->subYear()->toDateString())
                ->sum('cpd_hours'), 1),
            'open_gaps' => DB::table('competency_assessments')->where('employee_id', $employeeId)->where('gap', '<', 0)->count(),
        ];

        $my_reviews = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where('pr.employee_id', $employeeId)
            ->select('pr.review_id', 'pr.status', 'pr.overall_score', 'pr.supervisor_rating', 'rc.cycle_name', 'rc.end_date')
            ->orderByDesc('rc.end_date')->limit(5)->get();

        $my_gaps = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->where('ca.employee_id', $employeeId)
            ->where('ca.gap', '<', 0)
            ->select('c.competency_name', 'c.required_proficiency', 'ca.current_proficiency', 'ca.gap', 'ca.assessed_date')
            ->orderBy('ca.gap')->limit(8)->get();

        $my_courses = DB::table('course_enrollments as ce')
            ->join('courses as c', 'ce.course_id', '=', 'c.course_id')
            ->where('ce.employee_id', $employeeId)
            ->select('c.course_id', 'c.title', 'c.cpd_hours', 'ce.status', 'ce.progress_pct', 'ce.due_date')
            ->orderByRaw("FIELD(ce.status,'in_progress','enrolled','completed')")
            ->limit(8)->get();

        $my_credentials = DB::table('employee_credentials')
            ->where('employee_id', $employeeId)
            ->select('credential_id', 'credential_type', 'issuing_body', 'expiry_date', 'verified_at')
            ->selectRaw(CredentialStatus::caseSql('expiry_date').' as status', CredentialStatus::caseBindings())
            ->orderBy('expiry_date')->get();

        return [
            'role' => 'staff',
            'scope' => 'My records',
            'heading' => 'My Dashboard',
            'subheading' => 'Your performance, competencies, and learning progress.',
            'stats' => $stats,
            'my_reviews' => $my_reviews,
            'my_gaps' => $my_gaps,
            'my_courses' => $my_courses,
            'my_credentials' => $my_credentials,
            'upcoming_sessions' => $this->upcomingSessions(),
        ];
    }

    /* ───────────────────────────── shared blocks ──────────────────────── */

    private function credentialAlerts(?string $departmentId = null)
    {
        // Everything already expired plus everything inside the warning window —
        // i.e. anything that is not "active" and not "no expiry".
        return DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->whereNotNull('ec.expiry_date')
            ->whereDate('ec.expiry_date', '<=', CredentialStatus::windowEnd())
            ->select('ec.credential_id', 'ec.credential_type', 'ec.expiry_date', 'ec.employee_id',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status', CredentialStatus::caseBindings())
            ->orderBy('ec.expiry_date')->limit(6)->get();
    }

    private function upcomingSessions()
    {
        return DB::table('training_sessions as ts')
            ->leftJoin('training_venues as tv', 'ts.venue_id', '=', 'tv.venue_id')
            ->where('ts.status', 'scheduled')
            ->whereDate('ts.session_date', '>=', now()->toDateString())
            ->select('ts.session_id', 'ts.title', 'ts.session_date', 'ts.start_time', 'ts.cpd_hours', 'tv.venue_name')
            ->orderBy('ts.session_date')->limit(5)->get();
    }
}
