<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningController extends Controller
{
    public function index()
    {
        $stats = [
            'total_courses'         => DB::table('courses')->where('is_active',true)->count(),
            'completions_this_month'=> DB::table('course_enrollments')->where('status','completed')->whereMonth('completed_at',now()->month)->count(),
            'avg_completion_rate'   => round(DB::table('course_enrollments')->avg(DB::raw('progress_pct')) ?? 0),
            'certificates_issued'   => DB::table('certificates')->count(),
        ];

        $courses = DB::table('courses as c')
            ->leftJoin('course_enrollments as ce','c.course_id','=','ce.course_id')
            ->select('c.*', DB::raw('COUNT(ce.enrollment_id) as enrollments_count'))
            ->where('c.is_active', true)
            ->groupBy('c.course_id')
            ->orderByDesc('enrollments_count')->limit(20)->get();

        $pathways = DB::table('learning_pathways as lp')
            ->leftJoin('pathway_courses as pc','lp.pathway_id','=','pc.pathway_id')
            ->select('lp.*', DB::raw('COUNT(pc.id) as courses_count'))
            ->groupBy('lp.pathway_id')->get();

        $cpd_records = DB::table('cpd_records as cr')
            ->join('employees as e','cr.employee_id','=','e.employee_id')
            ->leftJoin('employees as v','cr.verified_by','=','v.employee_id')
            ->select('cr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"))
            ->orderByDesc('cr.date_earned')->limit(10)->get();

        return view('learning.index', compact('stats','courses','pathways','cpd_records'));
    }

    public function createCourse() { return view('learning.courses.create'); }

    public function storeCourse(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:300',
            'category'          => 'required|string',
            'cpd_hours'         => 'required|numeric|min:0',
            'difficulty_level'  => 'required|in:beginner,intermediate,advanced',
        ]);

        DB::table('courses')->insert([
            'course_id'        => Str::uuid(),
            'title'            => $request->title,
            'category'         => $request->category,
            'cpd_hours'        => $request->cpd_hours,
            'difficulty_level' => $request->difficulty_level,
            'description'      => $request->description,
            'passing_score'    => $request->passing_score ?? 70,
            'is_mandatory'     => $request->boolean('is_mandatory'),
            'is_active'        => true,
            'created_by'       => $this->currentEmployeeId(),
            'created_at'       => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('learning.index')->with('success','Course created successfully.');
    }

    public function enroll($courseId)
    {
        $empId = $this->currentEmployeeId();

        if (! $empId) {
            return back()->with('error','Your account is not linked to an employee profile, so it cannot be enrolled.');
        }

        abort_if(! DB::table('courses')->where('course_id', $courseId)->exists(), 404);

        $exists = DB::table('course_enrollments')
            ->where('employee_id', $empId)
            ->where('course_id', $courseId)->exists();

        // course_enrollments has no timestamps(); it tracks enrollment_date/completed_at.
        if (!$exists) {
            DB::table('course_enrollments')->insert([
                'enrollment_id'   => Str::uuid(),
                'employee_id'     => $empId,
                'course_id'       => $courseId,
                'enrolled_by'     => $empId,
                'enrollment_date' => now()->toDateString(),
                'status'          => 'enrolled',
                'progress_pct'    => 0,
            ]);
        }

        return redirect()->route('learning.index')->with('success','Enrolled successfully!');
    }

    public function showCourse($id)
    {
        $course = DB::table('courses')->where('course_id',$id)->first();
        abort_if(!$course, 404);
        $enrollments = DB::table('course_enrollments as ce')
            ->join('employees as e','ce.employee_id','=','e.employee_id')
            ->where('ce.course_id',$id)
            ->select('ce.*',DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
            ->get();
        return view('learning.courses.show', compact('course','enrollments'));
    }

    public function pathwaysIndex()
    {
        // Group by the PK only — learning_pathways has no is_active column, and
        // MySQL resolves the other selected columns as functionally dependent.
        $pathways = DB::table('learning_pathways as lp')
            ->leftJoin('pathway_courses as pc','lp.pathway_id','=','pc.pathway_id')
            ->select('lp.*', DB::raw('COUNT(pc.id) as courses_count'))
            ->groupBy('lp.pathway_id')
            ->orderBy('lp.pathway_name')
            ->get();
        return view('learning.pathways.index', compact('pathways'));
    }

    public function createPathway()
    {
        return view('learning.pathways.create', [
            'roles' => DB::table('roles')->orderBy('role_name')->get(),
        ]);
    }

    public function storePathway(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'pathway_name'    => 'required|string|max:200',
            'description'     => 'nullable|string',
            'total_cpd_hours' => 'nullable|numeric|min:0|max:9999.9',
            'target_roles'    => 'nullable|array',
            'target_roles.*'  => 'string|exists:roles,role_id',
        ]);

        DB::table('learning_pathways')->insert([
            'pathway_id'      => Str::uuid(),
            'pathway_name'    => $request->pathway_name,
            'description'     => $request->description,
            'target_roles'    => $request->target_roles ? json_encode(array_values($request->target_roles)) : null,
            'total_cpd_hours' => $request->total_cpd_hours ?: null,
            'is_mandatory'    => $request->boolean('is_mandatory'),
            'created_by'      => $this->currentEmployeeId(),
            'created_at'      => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('learning.pathways.index')->with('success','Pathway created.');
    }

    public function cpdIndex()
    {
        $records = DB::table('cpd_records as cr')
            ->join('employees as e','cr.employee_id','=','e.employee_id')
            ->leftJoin('employees as vb','cr.verified_by','=','vb.employee_id')
            ->select('cr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                DB::raw("CONCAT(COALESCE(vb.first_name,''),' ',COALESCE(vb.last_name,'')) as verified_by_name"))
            ->orderByDesc('cr.date_earned')->paginate(30);
        return view('learning.cpd.index', compact('records'));
    }
}
