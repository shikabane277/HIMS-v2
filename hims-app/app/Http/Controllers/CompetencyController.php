<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompetencyController extends Controller
{
    public function index()
    {
        $stats = [
            'total_competencies' => DB::table('competencies')->count(),
            'avg_gap'            => round(DB::table('competency_assessments')->avg('gap') ?? 0, 1),
            'expiring_soon'      => DB::table('employee_credentials')
                                    ->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'expired'            => DB::table('employee_credentials')
                                    ->whereDate('expiry_date', '<', now())->count(),
        ];

        $departments = DB::table('departments')->orderBy('name')->get();

        $gap_matrix = DB::table('competencies as c')
            ->leftJoin('competency_assessments as ca','c.competency_id','=','ca.competency_id')
            ->select('c.competency_name','c.competency_code','c.required_proficiency',
                     DB::raw('AVG(ca.current_proficiency) as avg_score'),
                     DB::raw('AVG(ca.gap) as gap'))
            ->groupBy('c.competency_id','c.competency_name','c.competency_code','c.required_proficiency')
            ->orderBy('gap')->limit(20)->get();

        $credential_alerts = DB::table('employee_credentials as ec')
            ->join('employees as e','ec.employee_id','=','e.employee_id')
            ->where(function($q){
                $q->whereBetween('ec.expiry_date',[now(), now()->addDays(30)])
                  ->orWhereDate('ec.expiry_date','<',now());
            })
            ->select('ec.credential_id','ec.credential_type','ec.expiry_date',
                     DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"),
                     DB::raw("CASE WHEN ec.expiry_date < NOW() THEN 'expired' ELSE 'expiring_soon' END as status"))
            ->orderBy('ec.expiry_date')->limit(10)->get();

        $domains = DB::table('competency_domains as d')
            ->leftJoin('competency_categories as cc','d.domain_id','=','cc.domain_id')
            ->leftJoin('competencies as c','cc.category_id','=','c.category_id')
            ->select('d.domain_id','d.domain_name',
                     DB::raw('COUNT(DISTINCT cc.category_id) as categories_count'),
                     DB::raw('COUNT(DISTINCT c.competency_id) as competencies_count'))
            ->groupBy('d.domain_id','d.domain_name')->get();

        return view('competency.index', compact('stats','departments','gap_matrix','credential_alerts','domains'));
    }
}
