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
            ->orderByDesc('ce.enrollment_id')->limit(20)->get();

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
            'created_by'       => auth()->id(),
            'created_at'       => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('learning.index')->with('success','Course created successfully.');
    }

    public function enroll($courseId)
    {
        $exists = DB::table('course_enrollments')
            ->where('employee_id', auth()->user()->employee_id ?? '')
            ->where('course_id', $courseId)->exists();

        if (!$exists) {
            DB::table('course_enrollments')->insert([
                'enrollment_id'   => Str::uuid(),
                'employee_id'     => auth()->user()->employee_id,
                'course_id'       => $courseId,
                'enrollment_date' => now()->toDateString(),
                'status'          => 'enrolled',
                'progress_pct'    => 0,
                'created_at'      => now(), 'updated_at' => now(),
            ]);
        }

        return redirect()->route('learning.index')->with('success','Enrolled successfully!');
    }
}
