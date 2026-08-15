<?php

namespace App\Http\Controllers;

use App\Services\ReportingLineService;
use App\Support\AuditTrail;
use App\Support\CredentialStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(private readonly ReportingLineService $reportingLines) {}

    public function index(Request $request)
    {
        $employees = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->select('e.*', 'd.name as department_name', 'r.role_name');

        if ($search = trim((string) $request->query('q'))) {
            $employees->where(function ($q) use ($search) {
                $q->where('e.first_name', 'like', "%{$search}%")
                    ->orWhere('e.last_name', 'like', "%{$search}%")
                    ->orWhere('e.employee_code', 'like', "%{$search}%")
                    ->orWhere('e.email', 'like', "%{$search}%");
            });
        }

        if ($dept = $request->query('department')) {
            $employees->where('e.department_id', $dept);
        }

        $employees = $this->scopeToVisibleEmployees($employees)
            ->orderBy('e.last_name')->paginate(20)->withQueryString();

        foreach ($employees as $emp) {
            if ($emp->phone) {
                try {
                    $emp->phone = Crypt::decryptString($emp->phone);
                } catch (\Throwable $e) {
                }
            }
        }

        return view('employees.index', [
            'employees' => $employees,
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'filters' => ['q' => $search, 'department' => $dept],
        ]);
    }

    public function create()
    {
        return view('employees.create', [
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'roles' => DB::table('roles')->orderBy('role_name')->get(),
            'supervisors' => $this->reportingLines->eligibleManagers(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:employees',
            'department_id' => 'required|string|exists:departments,department_id',
            'role_id' => 'required|string|exists:roles,role_id',
            'position_title' => 'nullable|string|max:200',
            'hire_date' => 'required|date',
            'employment_status' => 'required|in:active,probationary,on_leave,suspended,terminated,resigned',
            'supervisor_id' => 'nullable|string|exists:employees,employee_id',
            'is_people_manager' => 'nullable|boolean',
            'phone' => 'nullable|string|max:100',
        ]);

        $empCode = 'EMP-'.strtoupper(Str::random(6));
        $encPhone = $request->phone ? Crypt::encryptString($request->phone) : null;
        $empId = (string) Str::uuid();
        $supervisorId = $request->supervisor_id ?: null;
        $isPeopleManager = $request->boolean('is_people_manager');

        DB::transaction(function () use ($empId, $empCode, $request, $encPhone, $supervisorId, $isPeopleManager) {
            $graph = $this->reportingLines->reportingGraph(true);
            $assignmentError = $this->reportingLines->assignmentError($empId, $supervisorId, true, $graph);

            if ($assignmentError) {
                throw ValidationException::withMessages(['supervisor_id' => $assignmentError]);
            }

            DB::table('employees')->insert([
                'employee_id' => $empId,
                'employee_code' => $empCode,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $encPhone,
                'department_id' => $request->department_id,
                'role_id' => $request->role_id,
                'position_title' => $request->position_title,
                'hire_date' => $request->hire_date,
                'employment_status' => $request->employment_status,
                'is_people_manager' => $isPeopleManager,
                'supervisor_id' => $supervisorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        AuditTrail::record('create_employee', 'employees', $empId, afterState: [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'is_people_manager' => $isPeopleManager,
            'supervisor_id' => $supervisorId,
        ]);

        return redirect()->route('employees.index')->with('success', 'Employee record created.');
    }

    public function show($id)
    {
        $employee = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->where('e.employee_id', $id)
            ->select('e.*', 'd.name as department_name', 'r.role_name')
            ->first();

        abort_if(! $employee, 404);
        $this->authorizeEmployeeAccess($id);

        if ($employee->phone) {
            try {
                $employee->phone = Crypt::decryptString($employee->phone);
            } catch (\Throwable $e) {
            }
        }

        $reviews = DB::table('performance_reviews')->where('employee_id', $id)->latest()->limit(5)->get();
        $credentials = DB::table('employee_credentials')->where('employee_id', $id)->get();
        $enrollments = DB::table('course_enrollments as ce')->join('courses as c', 'ce.course_id', '=', 'c.course_id')->where('ce.employee_id', $id)->select('ce.*', 'c.title', 'c.cpd_hours')->get();

        return view('employees.show', compact('employee', 'reviews', 'credentials', 'enrollments'));
    }

    /**
     * "My Development" — the progression screen for the signed-in user, so the
     * sidebar can link it without knowing an employee id.
     */
    public function myProgression()
    {
        $employeeId = $this->currentEmployeeId();

        if (! $employeeId) {
            return redirect()->route('dashboard')
                ->with('error', 'Your account is not linked to an employee profile, so there is no development record to show.');
        }

        return $this->progression($employeeId);
    }

    /**
     * One screen for an employee's development position: open competency gaps,
     * credential validity, course and training activity, verified CPD, and what
     * falls due next.
     *
     * Reads the trigger-computed competency_assessments.gap and the canonical
     * CredentialStatus helper rather than recomputing either, so this page
     * cannot disagree with the gap analysis or the expiry alerts.
     */
    public function progression($id)
    {
        $employee = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->where('e.employee_id', $id)
            ->select('e.*', 'd.name as department_name', 'r.role_name')
            ->first();

        abort_if(! $employee, 404);
        $this->authorizeEmployeeAccess($id);

        // ── Open competency gaps ──
        // gap is the trigger-computed column; nothing here recomputes it. Only
        // the most recent sitting per competency counts, matching how
        // CompetencyGapAnalysisService::assessments() resolves "current" — an
        // old failing assessment must not keep showing as an open gap after a
        // later passing one.
        $gaps = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->where('ca.employee_id', $id)
            ->select('c.competency_name', 'c.competency_code', 'c.required_proficiency',
                'c.is_mandatory', 'cc.category_name', 'ca.competency_id',
                'ca.current_proficiency', 'ca.gap', 'ca.assessed_date')
            ->orderByDesc('ca.assessed_date')
            ->get()
            ->unique('competency_id')
            ->filter(fn ($row) => $row->gap !== null && $row->gap < 0)
            ->sortBy('gap')
            ->values();

        // ── Credentials, worst status first, via the canonical helper ──
        $credentials = DB::table('employee_credentials')
            ->where('employee_id', $id)
            ->selectRaw('*, '.CredentialStatus::caseSql('expiry_date').' as status',
                CredentialStatus::caseBindings())
            ->orderByRaw('CASE
                WHEN expiry_date IS NULL THEN 3
                WHEN expiry_date < ? THEN 0
                WHEN expiry_date <= ? THEN 1
                ELSE 2 END', CredentialStatus::caseBindings())
            ->orderBy('expiry_date')
            ->get();

        // ── Course enrollments (in progress + recently completed) ──
        $enrollments = DB::table('course_enrollments as ce')
            ->join('courses as c', 'ce.course_id', '=', 'c.course_id')
            ->where('ce.employee_id', $id)
            ->whereIn('ce.status', ['enrolled', 'in_progress', 'completed'])
            ->select('ce.*', 'c.title', 'c.category', 'c.cpd_hours')
            ->orderByRaw("FIELD(ce.status,'in_progress','enrolled','completed')")
            ->orderByDesc('ce.enrollment_date')
            ->limit(10)->get();

        // ── Training sessions (upcoming + recently attended) ──
        $trainings = DB::table('training_registrations as tr')
            ->join('training_sessions as ts', 'tr.session_id', '=', 'ts.session_id')
            ->where('tr.employee_id', $id)
            ->select('tr.*', 'ts.title', 'ts.session_date', 'ts.start_time', 'ts.cpd_hours')
            ->orderByDesc('ts.session_date')
            ->limit(10)->get();

        // ── CPD records (verified hours, last 12 months) ──
        $cpd = DB::table('cpd_records')
            ->where('employee_id', $id)
            ->where('verified', true)
            ->where('date_earned', '>=', now()->subMonths(12)->toDateString())
            ->orderByDesc('date_earned')
            ->get();

        $cpdTotal = $cpd->sum('cpd_hours');

        // ── Upcoming reassessments ──
        // Two-tier cadence: per-assessment next_assessment_due overrides the
        // type-level competencies.reassessment_months. Only the latest sitting
        // per competency counts, so a competency assessed three times shows once
        // off its most recent date. 90-day warning window, and overdue rows stay
        // in the list rather than disappearing once the date passes.
        $latestAssessments = DB::table('competency_assessments')
            ->where('employee_id', $id)
            ->select('competency_id',
                DB::raw('MAX(assessed_date) as last_assessed'),
                DB::raw('SUBSTRING_INDEX(GROUP_CONCAT(next_assessment_due ORDER BY assessed_date DESC), ",", 1) as override_due'))
            ->groupBy('competency_id');

        $upcomingReassessments = DB::table('competencies as c')
            ->joinSub($latestAssessments, 'la', 'la.competency_id', '=', 'c.competency_id')
            ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->selectRaw('c.competency_id, c.competency_name, c.competency_code, cc.category_name,
                la.last_assessed, c.reassessment_months, la.override_due,
                COALESCE(la.override_due, DATE_ADD(la.last_assessed, INTERVAL c.reassessment_months MONTH)) as due_date')
            ->whereRaw('(la.override_due IS NOT NULL OR c.reassessment_months IS NOT NULL)')
            ->havingRaw('due_date IS NOT NULL AND due_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)')
            ->orderBy('due_date')
            ->get();

        return view('employees.progression', compact(
            'employee', 'gaps', 'credentials', 'enrollments', 'trainings', 'cpd', 'cpdTotal', 'upcomingReassessments'
        ));
    }

    public function edit($id)
    {
        $employee = DB::table('employees')->where('employee_id', $id)->first();
        abort_if(! $employee, 404);
        $this->authorizeEmployeeAccess($id);

        if ($employee->phone) {
            try {
                $employee->phone = Crypt::decryptString($employee->phone);
            } catch (\Throwable $e) {
            }
        }

        $supervisors = $this->reportingLines->eligibleManagers($id);
        $currentSupervisor = $this->reportingLines->managerDetails($employee->supervisor_id);

        // Preserve an unchanged legacy assignment even if the manager is now
        // inactive or their HIMS account is incomplete.
        if ($currentSupervisor && ! $supervisors->contains('employee_id', $currentSupervisor->employee_id)) {
            $currentSupervisor->is_current_assignment = true;
            $supervisors->prepend($currentSupervisor);
        }

        $directReports = $this->reportingLines->directReports($id);

        return view('employees.edit', [
            'employee' => $employee,
            'departments' => DB::table('departments')->orderBy('name')->get(),
            'roles' => DB::table('roles')->orderBy('role_name')->get(),
            'supervisors' => $supervisors,
            'currentSupervisor' => $currentSupervisor,
            'directReports' => $directReports,
            'activeDirectReports' => $directReports->where('employment_status', 'active')->values(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = DB::table('employees')->where('employee_id', $id)->first();
        abort_if(! $employee, 404);
        $this->authorizeEmployeeAccess($id);

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:employees,email,'.$id.',employee_id',
            'department_id' => 'required|string|exists:departments,department_id',
            'role_id' => 'required|string|exists:roles,role_id',
            'position_title' => 'nullable|string|max:200',
            'hire_date' => 'required|date',
            'employment_status' => 'required|in:active,probationary,on_leave,suspended,terminated,resigned',
            'supervisor_id' => 'nullable|string|exists:employees,employee_id',
            'is_people_manager' => 'nullable|boolean',
            'phone' => 'nullable|string|max:100',
        ]);

        $encPhone = $request->phone ? Crypt::encryptString($request->phone) : null;
        $supervisorId = $request->supervisor_id ?: null;
        $isPeopleManager = $request->boolean('is_people_manager');
        $supervisorChanged = $supervisorId !== $employee->supervisor_id;
        $activeDirectReports = $this->reportingLines->directReports($id, true);

        $beforeState = (array) $employee;

        DB::transaction(function () use (
            $id,
            $request,
            $encPhone,
            $supervisorId,
            $isPeopleManager,
            $supervisorChanged
        ) {
            $graph = $this->reportingLines->reportingGraph(true);
            $directReportCount = collect($graph)->filter(fn ($managerId) => $managerId === $id)->count();

            if (! $isPeopleManager && $directReportCount > 0) {
                throw ValidationException::withMessages([
                    'is_people_manager' => "Reassign this manager's {$directReportCount} direct report(s) before removing People Manager status.",
                ]);
            }

            $assignmentError = $this->reportingLines->assignmentError(
                $id,
                $supervisorId,
                $supervisorChanged,
                $graph
            );

            if ($assignmentError) {
                throw ValidationException::withMessages(['supervisor_id' => $assignmentError]);
            }

            DB::table('employees')->where('employee_id', $id)->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $encPhone,
                'department_id' => $request->department_id,
                'role_id' => $request->role_id,
                'position_title' => $request->position_title,
                'hire_date' => $request->hire_date,
                'employment_status' => $request->employment_status,
                'is_people_manager' => $isPeopleManager,
                'supervisor_id' => $supervisorId,
                'updated_at' => now(),
            ]);
        });

        AuditTrail::record('update_employee', 'employees', $id, beforeState: $beforeState, afterState: [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'employment_status' => $request->employment_status,
            'is_people_manager' => $isPeopleManager,
            'supervisor_id' => $supervisorId,
        ]);

        $redirect = redirect()->route('employees.show', $id)->with('success', 'Employee record updated.');

        if ($request->employment_status !== 'active'
            && $employee->employment_status !== $request->employment_status
            && $activeDirectReports->isNotEmpty()) {
            $names = $activeDirectReports->map(fn ($report) => $report->first_name.' '.$report->last_name)->implode(', ');
            $redirect->with('warning', "This employee still has active direct reports who need reassignment: {$names}.");
        }

        return $redirect;
    }

    public function managerSetup()
    {
        return view('employees.manager-setup', $this->reportingLines->setupReport());
    }

    public function destroy($id)
    {
        $employee = DB::table('employees')->where('employee_id', $id)->first();
        abort_if(! $employee, 404);

        $name = $employee->first_name.' '.$employee->last_name;
        $activeDirectReports = $this->reportingLines->directReports($id, true);

        // Soft-delete approach: set employment_status to 'terminated' to keep data integrity
        DB::table('employees')->where('employee_id', $id)->update([
            'employment_status' => 'terminated',
            'updated_at' => now(),
        ]);

        $redirect = redirect()->route('employees.index')
            ->with('success', "Employee \"{$name}\" has been deactivated.");

        if ($activeDirectReports->isNotEmpty()) {
            $names = $activeDirectReports->map(fn ($report) => $report->first_name.' '.$report->last_name)->implode(', ');
            $redirect->with('warning', "{$name} still has active direct reports who need reassignment: {$names}.");
        }

        return $redirect;
    }
}
