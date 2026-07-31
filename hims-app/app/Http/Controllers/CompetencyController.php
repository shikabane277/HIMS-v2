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

    public function createAssessment()
    {
        return view('competency.assessments.create', [
            'employees'    => DB::table('employees')->orderBy('first_name')->get(),
            'competencies' => DB::table('competencies')->orderBy('competency_name')->get(),
        ]);
    }

    public function storeAssessment(\Illuminate\Http\Request $request)
    {
        $request->validate(['employee_id'=>'required','competency_id'=>'required','current_proficiency'=>'required|numeric|min:1|max:5']);
        $required = DB::table('competencies')->where('competency_id',$request->competency_id)->value('required_proficiency') ?? 3;
        DB::table('competency_assessments')->insert([
            'assessment_id'      => \Illuminate\Support\Str::uuid(),
            'employee_id'        => $request->employee_id,
            'competency_id'      => $request->competency_id,
            'current_proficiency'=> $request->current_proficiency,
            'required_proficiency'=> $required,
            'gap'                => round($request->current_proficiency - $required, 2),
            'assessment_method'  => $request->assessment_method ?? 'self_assessment',
            'assessment_date'    => $request->assessment_date ?? now()->toDateString(),
            'assessor_notes'     => $request->assessor_notes,
            'assessed_by'        => auth()->user()->employee_id ?? null,
            'created_at'         => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('competency.index')->with('success','Assessment recorded.');
    }

    public function credentialsIndex()
    {
        $credentials = DB::table('employee_credentials as ec')
            ->join('employees as e','ec.employee_id','=','e.employee_id')
            ->select('ec.*', DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
            ->orderBy('ec.expiry_date')->paginate(20);
        $stats = [
            'total'   => DB::table('employee_credentials')->count(),
            'valid'   => DB::table('employee_credentials')->whereDate('expiry_date','>=',now())->count(),
            'expiring'=> DB::table('employee_credentials')->whereBetween('expiry_date',[now(),now()->addDays(30)])->count(),
            'expired' => DB::table('employee_credentials')->whereDate('expiry_date','<',now())->count(),
        ];
        return view('competency.credentials.index', compact('credentials','stats'));
    }

    public function createCredential()
    {
        return view('competency.credentials.create', [
            'employees' => DB::table('employees')->orderBy('first_name')->get(),
        ]);
    }

    public function storeCredential(\Illuminate\Http\Request $request)
    {
        $request->validate(['employee_id'=>'required','credential_type'=>'required|string']);
        DB::table('employee_credentials')->insert([
            'credential_id'     => \Illuminate\Support\Str::uuid(),
            'employee_id'       => $request->employee_id,
            'credential_type'   => $request->credential_type,
            'credential_number' => $request->credential_number,
            'issuing_body'      => $request->issuing_body,
            'issue_date'        => $request->issue_date ?: null,
            'expiry_date'       => $request->expiry_date ?: null,
            'verification_status'=> 'pending',
            'created_at'        => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('competency.credentials.index')->with('success','Credential added.');
    }

    public function createDomain()
    {
        return view('competency.domains.create');
    }

    public function storeDomain(\Illuminate\Http\Request $request)
    {
        $request->validate(['domain_name'=>'required|string|max:150']);
        DB::table('competency_domains')->insert([
            'domain_id'   => \Illuminate\Support\Str::uuid(),
            'domain_name' => $request->domain_name,
            'description' => $request->description,
            'created_at'  => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('competency.index')->with('success','Domain created.');
    }

    public function showDomain($id)
    {
        $domain = DB::table('competency_domains')->where('domain_id',$id)->first();
        abort_if(!$domain, 404);
        $categories = DB::table('competency_categories')->where('domain_id',$id)->get();
        return view('competency.domains.show', compact('domain','categories'));
    }
}
