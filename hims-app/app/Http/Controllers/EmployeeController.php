<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = DB::table('employees as e')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->join('roles as r','e.role_id','=','r.role_id')
            ->select('e.*','d.name as department_name','r.role_name')
            ->orderBy('e.last_name')->paginate(20);

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.create', [
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'roles'       => DB::table('roles')->orderBy('role_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|unique:employees',
            'department_id' => 'required|string',
            'role_id'       => 'required|string',
            'hire_date'     => 'required|date',
        ]);

        $empCode = 'EMP-' . strtoupper(Str::random(6));

        DB::table('employees')->insert([
            'employee_id'       => Str::uuid(),
            'employee_code'     => $empCode,
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'email'             => $request->email,
            'department_id'     => $request->department_id,
            'role_id'           => $request->role_id,
            'position_title'    => $request->position_title,
            'hire_date'         => $request->hire_date,
            'employment_status' => 'active',
            'supervisor_id'     => $request->supervisor_id ?: null,
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('employees.index')->with('success','Employee record created.');
    }

    public function show($id)
    {
        $employee = DB::table('employees as e')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->join('roles as r','e.role_id','=','r.role_id')
            ->where('e.employee_id',$id)
            ->select('e.*','d.name as department_name','r.role_name')
            ->first();

        abort_if(!$employee, 404);

        $reviews        = DB::table('performance_reviews')->where('employee_id',$id)->latest()->limit(5)->get();
        $credentials    = DB::table('employee_credentials')->where('employee_id',$id)->get();
        $enrollments    = DB::table('course_enrollments as ce')->join('courses as c','ce.course_id','=','c.course_id')->where('ce.employee_id',$id)->select('ce.*','c.title','c.cpd_hours')->get();
        $recognitions   = DB::table('recognition_posts')->where('recipient_id',$id)->orderByDesc('created_at')->limit(5)->get();

        return view('employees.show', compact('employee','reviews','credentials','enrollments','recognitions'));
    }
}
