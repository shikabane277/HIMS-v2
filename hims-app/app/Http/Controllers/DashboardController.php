<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            $user->isAdmin()      => view('dashboard', $this->adminData()),
            $user->isHrManager()  => view('dashboard', $this->hrData()),
            $user->isSupervisor() => view('dashboard', $this->supervisorData($user->departmentId())),
            default               => view('dashboard', $this->staffData($user->employee_id)),
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

        $data['scope']       = 'Whole organisation';
        $data['role']        = 'admin';
        $data['heading']     = 'Administrator Dashboard';
        $data['subheading']  = 'Hospital-wide workforce, compliance, and system overview.';

        $data['system'] = [
            'user_accounts'    => DB::table('users')->count(),
            'unlinked_accounts'=> DB::table('users')->whereNull('employee_id')->count(),
            'admins'           => DB::table('users')->where('role','admin')->count(),
            'departments'      => DB::table('departments')->count(),
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

        $data['scope']      = 'Whole organisation';
        $data['role']       = 'hr_manager';
        $data['heading']    = 'HR Dashboard';
        $data['subheading'] = 'Workforce records, performance cycles, competency and training compliance.';

        $data['hiring'] = [
            'new_hires_90d' => DB::table('employees')->whereDate('hire_date','>=',now()->subDays(90)->toDateString())->count(),
            'on_leave'      => DB::table('employees')->where('employment_status','on_leave')->count(),
            'terminated'    => DB::table('employees')->where('employment_status','terminated')->count(),
        ];

        $data['review_progress'] = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->where('rc.status','active')
            ->select('pr.status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('pr.status')->get();

        $data['unassessed_employees'] = DB::table('employees as e')
            ->leftJoin('competency_assessments as ca','e.employee_id','=','ca.employee_id')
            ->where('e.employment_status','active')
            ->whereNull('ca.assessment_id')
            ->count();

        return $data;
    }

    /**
     * Shared organisation-wide blocks for admin and HR.
     *
     * @return array<string, mixed>
     */
    private function organisationWideData(): array
    {
        $stats = [
            'headcount'                 => DB::table('employees')->where('employment_status','active')->count(),
            'pending_reviews'           => DB::table('performance_reviews')->whereNotIn('status',['completed'])->count(),
            'expiring_credentials'      => DB::table('employee_credentials')
                                            ->whereNotNull('expiry_date')
                                            ->whereDate('expiry_date','<=',now()->addDays(30)->toDateString())
                                            ->whereDate('expiry_date','>=',now()->toDateString())
                                            ->count(),
            'expired_credentials'       => DB::table('employee_credentials')
                                            ->whereNotNull('expiry_date')
                                            ->whereDate('expiry_date','<',now()->toDateString())
                                            ->count(),
            'active_enrollments'        => DB::table('course_enrollments')->whereIn('status',['enrolled','in_progress'])->count(),
            'recognitions_this_month'   => DB::table('recognition_posts')
                                            ->where('moderation_status','approved')
                                            ->whereYear('created_at', now()->year)
                                            ->whereMonth('created_at', now()->month)
                                            ->count(),
            'critical_gaps'             => DB::table('competency_assessments')->where('gap','<=',-2)->count(),
            'upcoming_sessions'         => DB::table('training_sessions')
                                            ->where('status','scheduled')
                                            ->whereDate('session_date','>=',now()->toDateString())
                                            ->count(),
        ];

        $headcount_by_department = DB::table('departments as d')
            ->leftJoin('employees as e', function ($j) {
                $j->on('d.department_id','=','e.department_id')
                  ->where('e.employment_status','=','active');
            })
            ->select('d.department_id','d.name', DB::raw('COUNT(e.employee_id) as headcount'))
            ->groupBy('d.department_id','d.name')
            ->orderByDesc('headcount')->get();

        $competency_hotspots = DB::table('competency_assessments as ca')
            ->join('competencies as c','ca.competency_id','=','c.competency_id')
            ->select('c.competency_name','c.required_proficiency',
                     DB::raw('ROUND(AVG(ca.current_proficiency),2) as avg_proficiency'),
                     DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                     DB::raw('COUNT(DISTINCT ca.employee_id) as assessed'))
            ->groupBy('c.competency_id','c.competency_name','c.required_proficiency')
            ->havingRaw('AVG(ca.gap) < 0')
            ->orderBy('avg_gap')->limit(6)->get();

        $recent_reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->select('pr.review_id','pr.status','pr.overall_score',
                     DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                     'rc.cycle_name')
            ->orderByDesc('pr.updated_at')->limit(5)->get();

        $risk_positions = DB::table('critical_positions as cp')
            ->join('departments as d','cp.department_id','=','d.department_id')
            ->leftJoin('succession_candidates as sc','cp.position_id','=','sc.position_id')
            ->select('cp.position_id','cp.position_title','cp.vacancy_risk','d.name as department_name',
                     DB::raw('COUNT(sc.candidate_id) as candidates'))
            ->where('cp.vacancy_risk','!=','low')
            ->groupBy('cp.position_id','cp.position_title','cp.vacancy_risk','d.name')
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium')")
            ->limit(5)->get();

        return [
            'stats'                   => $stats,
            'headcount_by_department' => $headcount_by_department,
            'competency_hotspots'     => $competency_hotspots,
            'recent_reviews'          => $recent_reviews,
            'risk_positions'          => $risk_positions,
            'recent_recognition'      => $this->recentRecognition(),
            'credential_alerts'       => $this->credentialAlerts(),
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
                'role'       => 'supervisor',
                'scope'      => 'No department linked',
                'heading'    => 'Department Dashboard',
                'subheading' => 'Your account is not linked to a department yet, so there is nothing to show.',
                'stats'      => [],
                'team'       => collect(),
                'recent_reviews' => collect(),
                'competency_hotspots' => collect(),
                'credential_alerts' => collect(),
                'recent_recognition' => collect(),
                'upcoming_sessions' => collect(),
            ];
        }

        $department = DB::table('departments')->where('department_id',$departmentId)->first();

        $stats = [
            'team_size'            => DB::table('employees')->where('department_id',$departmentId)->where('employment_status','active')->count(),
            'pending_reviews'      => DB::table('performance_reviews as pr')
                                        ->join('employees as e','pr.employee_id','=','e.employee_id')
                                        ->where('e.department_id',$departmentId)
                                        ->whereNotIn('pr.status',['completed'])->count(),
            'expiring_credentials' => DB::table('employee_credentials as ec')
                                        ->join('employees as e','ec.employee_id','=','e.employee_id')
                                        ->where('e.department_id',$departmentId)
                                        ->whereNotNull('ec.expiry_date')
                                        ->whereDate('ec.expiry_date','<=',now()->addDays(30)->toDateString())
                                        ->whereDate('ec.expiry_date','>=',now()->toDateString())->count(),
            'critical_gaps'        => DB::table('competency_assessments as ca')
                                        ->join('employees as e','ca.employee_id','=','e.employee_id')
                                        ->where('e.department_id',$departmentId)
                                        ->where('ca.gap','<=',-2)->count(),
            'active_enrollments'   => DB::table('course_enrollments as ce')
                                        ->join('employees as e','ce.employee_id','=','e.employee_id')
                                        ->where('e.department_id',$departmentId)
                                        ->whereIn('ce.status',['enrolled','in_progress'])->count(),
            'avg_team_score'       => round((float) DB::table('performance_reviews as pr')
                                        ->join('employees as e','pr.employee_id','=','e.employee_id')
                                        ->where('e.department_id',$departmentId)
                                        ->whereNotNull('pr.overall_score')
                                        ->avg('pr.overall_score'), 2),
        ];

        $team = DB::table('employees as e')
            ->join('roles as r','e.role_id','=','r.role_id')
            ->leftJoin('performance_reviews as pr', function ($j) {
                $j->on('e.employee_id','=','pr.employee_id');
            })
            ->leftJoin('competency_assessments as ca','e.employee_id','=','ca.employee_id')
            ->where('e.department_id',$departmentId)
            ->where('e.employment_status','active')
            ->select('e.employee_id','e.first_name','e.last_name','e.position_title','r.role_name',
                     DB::raw('MAX(pr.overall_score) as latest_score'),
                     DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                     DB::raw('COUNT(DISTINCT ca.assessment_id) as assessments'))
            ->groupBy('e.employee_id','e.first_name','e.last_name','e.position_title','r.role_name')
            ->orderBy('e.last_name')->get();

        $competency_hotspots = DB::table('competency_assessments as ca')
            ->join('competencies as c','ca.competency_id','=','c.competency_id')
            ->join('employees as e','ca.employee_id','=','e.employee_id')
            ->where('e.department_id',$departmentId)
            ->select('c.competency_name','c.required_proficiency',
                     DB::raw('ROUND(AVG(ca.current_proficiency),2) as avg_proficiency'),
                     DB::raw('ROUND(AVG(ca.gap),2) as avg_gap'),
                     DB::raw('COUNT(DISTINCT ca.employee_id) as assessed'))
            ->groupBy('c.competency_id','c.competency_name','c.required_proficiency')
            ->havingRaw('AVG(ca.gap) < 0')
            ->orderBy('avg_gap')->limit(6)->get();

        $recent_reviews = DB::table('performance_reviews as pr')
            ->join('employees as e','pr.employee_id','=','e.employee_id')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->where('e.department_id',$departmentId)
            ->select('pr.review_id','pr.status','pr.overall_score',
                     DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                     'rc.cycle_name')
            ->orderByDesc('pr.updated_at')->limit(5)->get();

        return [
            'role'                => 'supervisor',
            'scope'               => $department->name ?? 'Your department',
            'heading'             => ($department->name ?? 'Department').' Dashboard',
            'subheading'          => 'Performance, competency and training status for your team.',
            'stats'               => $stats,
            'team'                => $team,
            'competency_hotspots' => $competency_hotspots,
            'recent_reviews'      => $recent_reviews,
            'credential_alerts'   => $this->credentialAlerts($departmentId),
            'recent_recognition'  => $this->recentRecognition($departmentId),
            'upcoming_sessions'   => $this->upcomingSessions(),
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
                'role'       => 'staff',
                'scope'      => 'No employee profile linked',
                'heading'    => 'My Dashboard',
                'subheading' => 'Your login is not linked to an employee profile yet. Ask HR to link it so your records appear here.',
                'stats'      => [],
                'my_reviews' => collect(),
                'my_gaps'    => collect(),
                'my_courses' => collect(),
                'my_credentials' => collect(),
                'my_recognition' => collect(),
                'upcoming_sessions' => $this->upcomingSessions(),
            ];
        }

        $stats = [
            'my_reviews'        => DB::table('performance_reviews')->where('employee_id',$employeeId)->count(),
            'latest_score'      => DB::table('performance_reviews')
                                    ->where('employee_id',$employeeId)
                                    ->whereNotNull('overall_score')
                                    ->orderByDesc('updated_at')->value('overall_score'),
            'courses_completed' => DB::table('course_enrollments')->where('employee_id',$employeeId)->where('status','completed')->count(),
            'courses_active'    => DB::table('course_enrollments')->where('employee_id',$employeeId)->whereIn('status',['enrolled','in_progress'])->count(),
            'cpd_hours_year'    => round((float) DB::table('cpd_records')
                                    ->where('employee_id',$employeeId)
                                    ->whereDate('date_earned','>=',now()->subYear()->toDateString())
                                    ->sum('cpd_hours'), 1),
            'open_gaps'         => DB::table('competency_assessments')->where('employee_id',$employeeId)->where('gap','<',0)->count(),
            'recognitions'      => DB::table('recognition_posts')->where('recipient_id',$employeeId)->where('moderation_status','approved')->count(),
        ];

        $my_reviews = DB::table('performance_reviews as pr')
            ->join('review_cycles as rc','pr.cycle_id','=','rc.cycle_id')
            ->where('pr.employee_id',$employeeId)
            ->select('pr.review_id','pr.status','pr.overall_score','pr.self_rating','rc.cycle_name','rc.end_date')
            ->orderByDesc('rc.end_date')->limit(5)->get();

        $my_gaps = DB::table('competency_assessments as ca')
            ->join('competencies as c','ca.competency_id','=','c.competency_id')
            ->where('ca.employee_id',$employeeId)
            ->where('ca.gap','<',0)
            ->select('c.competency_name','c.required_proficiency','ca.current_proficiency','ca.gap','ca.assessed_date')
            ->orderBy('ca.gap')->limit(8)->get();

        $my_courses = DB::table('course_enrollments as ce')
            ->join('courses as c','ce.course_id','=','c.course_id')
            ->where('ce.employee_id',$employeeId)
            ->select('c.course_id','c.title','c.cpd_hours','ce.status','ce.progress_pct','ce.due_date')
            ->orderByRaw("FIELD(ce.status,'in_progress','enrolled','completed')")
            ->limit(8)->get();

        $my_credentials = DB::table('employee_credentials')
            ->where('employee_id',$employeeId)
            ->select('credential_id','credential_type','issuing_body','expiry_date','verified_at')
            ->orderBy('expiry_date')->get();

        $my_recognition = DB::table('recognition_posts as rp')
            ->join('employees as author','rp.author_id','=','author.employee_id')
            ->leftJoin('recognition_badges as rb','rp.badge_id','=','rb.badge_id')
            ->where('rp.recipient_id',$employeeId)
            ->where('rp.moderation_status','approved')
            ->select('rp.post_id','rp.message','rp.created_at','rb.badge_name','rb.badge_icon',
                     DB::raw("CONCAT(author.first_name,' ',author.last_name) as author_name"))
            ->orderByDesc('rp.created_at')->limit(5)->get();

        return [
            'role'              => 'staff',
            'scope'             => 'My records',
            'heading'           => 'My Dashboard',
            'subheading'        => 'Your performance, competencies, learning progress and recognition.',
            'stats'             => $stats,
            'my_reviews'        => $my_reviews,
            'my_gaps'           => $my_gaps,
            'my_courses'        => $my_courses,
            'my_credentials'    => $my_credentials,
            'my_recognition'    => $my_recognition,
            'upcoming_sessions' => $this->upcomingSessions(),
        ];
    }

    /* ───────────────────────────── shared blocks ──────────────────────── */

    private function recentRecognition(?string $departmentId = null)
    {
        return DB::table('recognition_posts as rp')
            ->join('employees as author','rp.author_id','=','author.employee_id')
            ->join('employees as recip','rp.recipient_id','=','recip.employee_id')
            ->leftJoin('recognition_badges as rb','rp.badge_id','=','rb.badge_id')
            ->when($departmentId, fn ($q) => $q->where('recip.department_id',$departmentId))
            ->select(
                'rp.post_id','rp.message','rp.created_at',
                DB::raw("CONCAT(author.first_name,' ',author.last_name) AS author_name"),
                DB::raw("CONCAT(recip.first_name,' ',recip.last_name) AS recipient_name"),
                'rb.badge_name','rb.badge_icon'
            )
            ->where('rp.moderation_status','approved')
            ->orderByDesc('rp.created_at')->limit(5)->get();
    }

    private function credentialAlerts(?string $departmentId = null)
    {
        return DB::table('employee_credentials as ec')
            ->join('employees as e','ec.employee_id','=','e.employee_id')
            ->when($departmentId, fn ($q) => $q->where('e.department_id',$departmentId))
            ->whereNotNull('ec.expiry_date')
            ->whereDate('ec.expiry_date','<=',now()->addDays(30)->toDateString())
            ->select('ec.credential_id','ec.credential_type','ec.expiry_date','ec.employee_id',
                     DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                     DB::raw("CASE WHEN ec.expiry_date < CURDATE() THEN 'expired' ELSE 'expiring_soon' END as status"))
            ->orderBy('ec.expiry_date')->limit(6)->get();
    }

    private function upcomingSessions()
    {
        return DB::table('training_sessions as ts')
            ->leftJoin('training_venues as tv','ts.venue_id','=','tv.venue_id')
            ->where('ts.status','scheduled')
            ->whereDate('ts.session_date','>=',now()->toDateString())
            ->select('ts.session_id','ts.title','ts.session_date','ts.start_time','ts.cpd_hours','tv.venue_name')
            ->orderBy('ts.session_date')->limit(5)->get();
    }
}
