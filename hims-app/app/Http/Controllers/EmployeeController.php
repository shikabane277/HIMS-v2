<?php

namespace App\Http\Controllers;

use App\Services\ReportingLineService;
use App\Support\AuditTrail;
use App\Support\CredentialStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->orderByRaw("CASE ce.status WHEN 'in_progress' THEN 1 WHEN 'enrolled' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")
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
        $assessments = DB::table('competency_assessments')
            ->where('employee_id', $id)
            ->orderByDesc('assessed_date')
            ->get()
            ->unique('competency_id');

        $upcomingReassessments = collect();
        if ($assessments->isNotEmpty()) {
            $competencyIds = $assessments->pluck('competency_id')->toArray();
            $competencies = DB::table('competencies as c')
                ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
                ->whereIn('c.competency_id', $competencyIds)
                ->select('c.competency_id', 'c.competency_name', 'c.competency_code', 'c.reassessment_months', 'cc.category_name')
                ->get()
                ->keyBy('competency_id');

            $windowEnd = now()->addDays(90)->toDateString();

            foreach ($assessments as $ca) {
                $comp = $competencies->get($ca->competency_id);
                if (! $comp) {
                    continue;
                }

                $dueDate = null;
                $overrideDue = $ca->next_assessment_due;
                if ($overrideDue) {
                    $dueDate = Carbon::parse($overrideDue)->toDateString();
                } elseif ($comp->reassessment_months && $ca->assessed_date) {
                    $dueDate = Carbon::parse($ca->assessed_date)->addMonths((int) $comp->reassessment_months)->toDateString();
                }

                if ($dueDate && $dueDate <= $windowEnd) {
                    $upcomingReassessments->push((object) [
                        'competency_id' => $comp->competency_id,
                        'competency_name' => $comp->competency_name,
                        'competency_code' => $comp->competency_code,
                        'category_name' => $comp->category_name,
                        'last_assessed' => $ca->assessed_date,
                        'reassessment_months' => $comp->reassessment_months,
                        'override_due' => $overrideDue,
                        'due_date' => $dueDate,
                    ]);
                }
            }

            $upcomingReassessments = $upcomingReassessments->sortBy('due_date')->values();
        }

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
            $employee,
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

            // Automatic user account deactivation when employee is terminated/resigned/suspended
            if (in_array($request->employment_status, ['terminated', 'resigned', 'suspended'], true)) {
                $affected = DB::table('users')->where('employee_id', $id)->where('is_active', true)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
                if ($affected > 0) {
                    AuditTrail::record('user_account_auto_deactivated', 'users', $id, afterState: [
                        'reason' => 'employee_status_'.$request->employment_status,
                        'employee_id' => $id,
                    ]);
                }
            } elseif ($request->employment_status === 'active' && $employee->employment_status !== 'active') {
                DB::table('users')->where('employee_id', $id)->where('is_active', false)->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
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

    public function downloadTemplate()
    {
        abort_unless(auth()->user()->can('manage-employees'), 403);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="employee_import_template.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['first_name', 'last_name', 'email', 'department_code', 'role_slug', 'position_title', 'employment_status', 'hire_date']);
            fputcsv($handle, ['Maria', 'Santos', 'maria.santos@hospital.ph', 'NUR', 'staff_nurse', 'Staff Nurse I', 'active', '2026-01-15']);
            fputcsv($handle, ['Juan', 'Dela Cruz', 'juan.delacruz@hospital.ph', 'MED', 'doctor', 'Resident Physician', 'active', '2026-02-01']);
            fclose($handle);
        }, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        abort_unless(auth()->user()->can('manage-employees'), 403);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Unable to open CSV file.');
        }

        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map(fn ($h) => trim(strtolower($h)), $header);

        $imported = 0;
        $skipped = 0;

        $departments = DB::table('departments')->get()->keyBy(fn ($d) => strtoupper($d->department_code));
        $roles = DB::table('roles')->get()->keyBy(fn ($r) => strtolower($r->role_slug));

        $lastCode = DB::table('employees')
            ->where('employee_code', 'LIKE', 'EMP-%')
            ->orderByDesc('employee_code')
            ->value('employee_code');
        $nextNum = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $data = array_combine($header, array_pad($row, count($header), ''));
                $email = trim($data['email'] ?? '');
                $firstName = trim($data['first_name'] ?? '');
                $lastName = trim($data['last_name'] ?? '');

                if (empty($email) || empty($firstName) || empty($lastName)) {
                    $skipped++;

                    continue;
                }

                if (DB::table('employees')->where('email', $email)->exists()) {
                    $skipped++;

                    continue;
                }

                $deptCode = strtoupper(trim($data['department_code'] ?? ''));
                $dept = $departments->get($deptCode) ?? $departments->first();

                $roleSlug = strtolower(trim($data['role_slug'] ?? ''));
                $role = $roles->get($roleSlug) ?? $roles->first();

                $empId = (string) Str::uuid();
                $empCode = 'EMP-'.str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $hireDate = ! empty($data['hire_date']) ? date('Y-m-d', strtotime($data['hire_date'])) : now()->toDateString();
                $status = in_array(trim($data['employment_status'] ?? ''), ['active', 'on_leave', 'suspended', 'resigned', 'terminated'])
                    ? trim($data['employment_status'])
                    : 'active';

                DB::table('employees')->insert([
                    'employee_id' => $empId,
                    'employee_code' => $empCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'department_id' => $dept ? $dept->department_id : DB::table('departments')->value('department_id'),
                    'role_id' => $role ? $role->role_id : DB::table('roles')->value('role_id'),
                    'position_title' => trim($data['position_title'] ?? 'Staff Member'),
                    'hire_date' => $hireDate,
                    'employment_status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! DB::table('users')->where('email', $email)->exists()) {
                    $userRole = 'staff';
                    if ($roleSlug === 'system_admin' || $roleSlug === 'admin') {
                        $userRole = 'admin';
                    } elseif (str_contains($roleSlug, 'hr')) {
                        $userRole = 'hr_manager';
                    } elseif (str_contains($roleSlug, 'supervisor') || str_contains($roleSlug, 'head')) {
                        $userRole = 'supervisor';
                    }

                    DB::table('users')->insert([
                        'name' => "{$firstName} {$lastName}",
                        'email' => $email,
                        'password' => Hash::make(Str::random(16)),
                        'role' => $userRole,
                        'employee_id' => $empId,
                        'is_active' => $status === 'active',
                        'must_change_password' => true,
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $imported++;
            }

            DB::commit();
            fclose($handle);
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);

            return back()->with('error', 'CSV Import failed: '.$e->getMessage());
        }

        $msg = "Imported {$imported} employees successfully.";
        if ($skipped > 0) {
            $msg .= " ({$skipped} duplicate or invalid rows skipped)";
        }

        return redirect()->route('employees.index')->with('success', $msg);
    }
}
