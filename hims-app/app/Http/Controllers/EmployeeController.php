<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = DB::table('employees as e')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->join('roles as r','e.role_id','=','r.role_id')
            ->select('e.*','d.name as department_name','r.role_name');

        if ($search = trim((string) $request->query('q'))) {
            $employees->where(function ($q) use ($search) {
                $q->where('e.first_name','like',"%{$search}%")
                  ->orWhere('e.last_name','like',"%{$search}%")
                  ->orWhere('e.employee_code','like',"%{$search}%")
                  ->orWhere('e.email','like',"%{$search}%");
            });
        }

        if ($dept = $request->query('department')) {
            $employees->where('e.department_id', $dept);
        }

        $employees = $this->scopeToVisibleEmployees($employees)
            ->orderBy('e.last_name')->paginate(20)->withQueryString();

        return view('employees.index', [
            'employees'   => $employees,
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'filters'     => ['q' => $search, 'department' => $dept],
        ]);
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
        $this->authorizeEmployeeAccess($id);

        $reviews        = DB::table('performance_reviews')->where('employee_id',$id)->latest()->limit(5)->get();
        $credentials    = DB::table('employee_credentials')->where('employee_id',$id)->get();
        $enrollments    = DB::table('course_enrollments as ce')->join('courses as c','ce.course_id','=','c.course_id')->where('ce.employee_id',$id)->select('ce.*','c.title','c.cpd_hours')->get();
        $recognitions   = DB::table('recognition_posts')->where('recipient_id',$id)->orderByDesc('created_at')->limit(5)->get();

        return view('employees.show', compact('employee','reviews','credentials','enrollments','recognitions'));
    }

    public function edit($id)
    {
        $employee = DB::table('employees')->where('employee_id',$id)->first();
        abort_if(!$employee, 404);
        $this->authorizeEmployeeAccess($id);

        return view('employees.edit', [
            'employee'    => $employee,
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'roles'       => DB::table('roles')->orderBy('role_name')->get(),
            'supervisors' => DB::table('employees')
                                ->where('employee_id','!=',$id)
                                ->where('employment_status','active')
                                ->orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = DB::table('employees')->where('employee_id',$id)->first();
        abort_if(!$employee, 404);
        $this->authorizeEmployeeAccess($id);

        $request->validate([
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'email'             => 'required|email|max:255|unique:employees,email,'.$id.',employee_id',
            'department_id'     => 'required|string|exists:departments,department_id',
            'role_id'           => 'required|string|exists:roles,role_id',
            'position_title'    => 'nullable|string|max:200',
            'hire_date'         => 'required|date',
            'employment_status' => 'required|in:active,probationary,on_leave,suspended,terminated,resigned',
            'supervisor_id'     => 'nullable|string|exists:employees,employee_id',
            'phone'             => 'nullable|string|max:100',
        ]);

        // An employee cannot be their own supervisor.
        if ($request->supervisor_id === $id) {
            return back()->withInput()->with('error','An employee cannot be their own supervisor.');
        }

        DB::table('employees')->where('employee_id',$id)->update([
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'department_id'     => $request->department_id,
            'role_id'           => $request->role_id,
            'position_title'    => $request->position_title,
            'hire_date'         => $request->hire_date,
            'employment_status' => $request->employment_status,
            'supervisor_id'     => $request->supervisor_id ?: null,
            'updated_at'        => now(),
        ]);

        return redirect()->route('employees.show',$id)->with('success','Employee record updated.');
    }

    public function destroy($id)
    {
        $employee = DB::table('employees')->where('employee_id', $id)->first();
        abort_if(!$employee, 404);

        $name = $employee->first_name . ' ' . $employee->last_name;

        // Soft-delete approach: set employment_status to 'terminated' to keep data integrity
        DB::table('employees')->where('employee_id', $id)->update([
            'employment_status' => 'terminated',
            'updated_at'        => now(),
        ]);

        return redirect()->route('employees.index')
            ->with('success', "Employee \"{$name}\" has been deactivated.");
    }
}
