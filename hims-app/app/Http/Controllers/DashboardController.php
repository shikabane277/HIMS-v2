<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'pending_reviews'           => DB::table('performance_reviews')->where('status','!=','approved')->count(),
            'expiring_credentials'      => DB::table('employee_credentials')
                                            ->whereNotNull('expiry_date')
                                            ->whereDate('expiry_date', '<=', now()->addDays(30))
                                            ->whereDate('expiry_date', '>=', now())
                                            ->count(),
            'active_enrollments'        => DB::table('course_enrollments')->where('status','enrolled')->count(),
            'recognitions_this_month'   => DB::table('recognition_posts')
                                            ->whereMonth('created_at', now()->month)
                                            ->where('moderation_status','approved')
                                            ->count(),
        ];

        $recent_reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->select('pr.review_id','pr.status','pr.overall_score',
                     DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                     'rc.cycle_name')
            ->orderByDesc('pr.updated_at')
            ->limit(5)->get();

        $risk_positions = DB::table('critical_positions as cp')
            ->join('departments as d','cp.department_id','=','d.department_id')
            ->select('cp.position_id','cp.position_title','cp.vacancy_risk','d.name as department_name')
            ->where('cp.vacancy_risk','!=','low')
            ->orderByRaw("FIELD(cp.vacancy_risk,'critical','high','medium')")
            ->limit(5)->get();

        $recent_recognition = DB::table('recognition_posts as rp')
            ->join('employees as author','rp.author_id','=','author.employee_id')
            ->join('employees as recip','rp.recipient_id','=','recip.employee_id')
            ->leftJoin('recognition_badges as rb','rp.badge_id','=','rb.badge_id')
            ->select(
                'rp.post_id','rp.message','rp.created_at',
                DB::raw("CONCAT(author.first_name,' ',author.last_name) AS author_name"),
                DB::raw("CONCAT(recip.first_name,' ',recip.last_name) AS recipient_name"),
                'rb.badge_name','rb.badge_icon'
            )
            ->where('rp.moderation_status','approved')
            ->orderByDesc('rp.created_at')
            ->limit(5)->get();

        return view('dashboard', compact('stats','recent_reviews','risk_positions','recent_recognition'));
    }
}
