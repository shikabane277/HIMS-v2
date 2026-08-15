<?php

namespace App\Http\Controllers;

use App\Services\RenewalCycleService;
use App\Services\TrainingAssignmentService;
use App\Support\AuditTrail;
use App\Support\CredentialStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The hospital-facing half of the Learning module: what people are *required* to
 * hold, who is short of it, and the evidence an accreditation survey asks for.
 *
 * LearningController serves the other half — the catalogue, an employee's own CPD
 * log, their pathways. Everything here answers an institutional question instead
 * — "is this department compliant", "who lapses next month", "show me every ICU
 * nurse's status" — which is why it stays in its own controller rather than
 * growing LearningController further. The class keeps the name Compliance because
 * that is still what it does; only the routes, the views and the navigation moved
 * under `learning`, where these pages are now tabs rather than a second sidebar
 * entry.
 *
 * NO PAGE HERE REQUIRES THE SUBJECT TO HAVE A LOGIN. Every query keys on
 * `employee_id`; `accountCoverage()` is the single place a `users` row is even
 * mentioned, and there it is the thing being reported on, not a precondition.
 */
class ComplianceController extends Controller
{
    public function __construct(
        private TrainingAssignmentService $assignments,
        private RenewalCycleService $cycles,
    ) {}

    /**
     * Required Training: assignment completion rates and renewal risk, scoped to
     * what the signed-in role may see.
     *
     * Also loads the five dropdown/checklist sets the Assign Training modal needs.
     * The page body does not use them — they belong to the modal that used to be a
     * separate GET page, and this is where its data has to come from now.
     */
    public function index()
    {
        $assignments = $this->assignments->listWithCompliance(25);
        $assignments->each(function ($assignment) {
            $assignment->visible_roster = $this->assignments->roster($assignment)
                ->filter(fn ($row) => $this->canAccessEmployee($row->employee_id))
                ->values();
        });
        $atRisk = $this->cycles->atRisk(fn ($query) => $this->scopeToVisibleEmployees($query));

        return view('learning.assignments.index', [
            'assignments' => $assignments,
            'atRisk' => $atRisk,
            'stats' => [
                // Assignments with somebody still to finish. A raw row count was
                // the wrong number under a label that promised outstanding work:
                // it counted history, so it never fell as people completed. Read
                // off the collection above rather than re-queried — the
                // completion figures are already computed there.
                'open_assignments' => $assignments->filter(
                    fn ($assignment) => $assignment->compliance['outstanding'] > 0
                )->count(),
                'at_risk' => $atRisk->where('risk', 'at_risk')->count(),
                'shortfall' => $atRisk->where('risk', 'shortfall')->count(),
                'rules' => DB::table('renewal_rules')->where('is_active', true)->count(),
            ],
            'courses' => DB::table('courses')->where('is_active', true)
                ->orderBy('title')->get(['course_id', 'title', 'category', 'cpd_hours']),
            'sessions' => DB::table('training_sessions')
                ->whereDate('session_date', '>=', now()->toDateString())
                ->orderBy('session_date')->get(['session_id', 'title', 'session_date']),
            'departments' => DB::table('departments')->orderBy('name')
                ->get(['department_id', 'name as department_name']),
            'roles' => DB::table('roles')->orderBy('role_name')->get(['role_id', 'role_name']),
            'employees' => $this->scopeToVisibleEmployees(
                DB::table('employees as e')->where('e.employment_status', 'active')
            )->orderBy('e.last_name')->get(['e.employee_id', 'e.first_name', 'e.last_name', 'e.employee_code']),
        ]);
    }

    // ── Mandatory training ───────────────────────────────────

    /**
     * Require one or more courses/sessions of a target.
     *
     * `subjects[]` carries prefixed composites — `course:<uuid>` / `session:<uuid>`
     * — because a flat list of bare UUIDs cannot say which table a row came from,
     * and one submission may mix both. Each subject becomes its own
     * `training_assignments` row: they have separate completion states and are
     * chased separately, so collapsing them into one record would leave the
     * roster unable to say what a person still owes.
     */
    public function storeAssignment(Request $request)
    {
        // Accept the former single-subject payload as well as the new checklist
        // payload. This keeps saved forms and integrations working after the
        // Compliance-to-Learning merge while all new UI submits subjects[].
        if (! $request->has('subjects') && $request->filled(['subject_type', 'subject_id'])) {
            $request->merge([
                'subjects' => [$request->string('subject_type').':'.$request->string('subject_id')],
            ]);
        }

        $validated = $request->validate([
            'subjects' => 'required|array|min:1|max:25',
            'subjects.*' => ['required', 'string', 'regex:/^(course|session):[0-9a-fA-F-]{36}$/'],
            'target_type' => 'required|in:employee,department,role,all',
            'target_id' => 'nullable|string|size:36',
            'required_by' => 'nullable|date|after_or_equal:today',
            'reason' => 'nullable|string|max:1000',
        ], [
            'subjects.required' => 'Tick at least one course or training session.',
            'subjects.*.regex' => 'That course or session selection was not recognised.',
        ]);

        if ($validated['target_type'] !== 'all' && ! $validated['target_id']) {
            return back()->withInput()->with('error', 'Choose who this training is for.');
        }

        // A supervisor may only assign within their own reach, so the target has
        // to survive the same row-level check the rest of the app uses.
        if ($validated['target_type'] === 'employee' && ! $this->canAccessEmployee($validated['target_id'])) {
            return back()->withInput()->with('error', 'You cannot assign training to that employee.');
        }

        $result = $this->assignments->assignMany(
            $validated['subjects'],
            $validated['target_type'],
            $validated['target_type'] === 'all' ? null : $validated['target_id'],
            $validated['required_by'] ?? null,
            $this->currentEmployeeId(),
            $validated['reason'] ?? null,
        );

        if ($result['employees'] === 0) {
            return back()->withInput()->with('error', 'That target has no active employees, so nothing was assigned.');
        }

        $subjects = count($validated['subjects']);
        $message = $subjects === 1
            ? "Assigned to {$result['created']} of {$result['employees']} employee(s)."
            : "{$subjects} requirements assigned — {$result['created']} enrolment(s) created across {$result['employees']} employee(s).";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} already enrolled or beyond session capacity.";
        }

        // One subject has a roster worth landing on; several do not — there is no
        // single roster to show, so the list of what was just required is the
        // more useful destination.
        return $subjects === 1
            ? redirect()->route('learning.assignments.show', $result['assignment_ids'][0])->with('success', $message)
            : redirect()->route('learning.assignments.index')->with('success', $message);
    }

    /**
     * The drill-down: who has not finished.
     *
     * `?status=` answers "who's short" with a filter rather than a page title.
     * The tiles come from the unscoped compliance figures while the roster is
     * scoped by canAccessEmployee(), so the two can legitimately disagree — the
     * footnote on the page explains it.
     */
    public function showAssignment(Request $request, string $id)
    {
        $assignment = DB::table('training_assignments')->where('assignment_id', $id)->first();
        abort_if(! $assignment, 404);

        $assignment->subject_name = $this->assignments->subjectName($assignment);
        $assignment->target_name = $this->assignments->targetName($assignment);

        $status = in_array($request->query('status'), ['outstanding', 'complete'], true)
            ? $request->query('status')
            : 'all';

        $roster = $this->assignments->roster($assignment)
            ->filter(fn ($row) => $this->canAccessEmployee($row->employee_id));

        if ($status === 'outstanding') {
            $roster = $roster->filter(fn ($row) => ! $row->is_complete);
        } elseif ($status === 'complete') {
            $roster = $roster->filter(fn ($row) => (bool) $row->is_complete);
        }

        return view('learning.assignments.show', [
            'assignment' => $assignment,
            'compliance' => $this->assignments->complianceFor($assignment),
            'roster' => $roster->values(),
            'status' => $status,
            'assigner' => $assignment->assigned_by
                ? DB::table('employees')->where('employee_id', $assignment->assigned_by)
                    ->first(['first_name', 'last_name'])
                : null,
        ]);
    }

    // ── Renewal rules and cycles ─────────────────────────────

    public function rulesIndex()
    {
        return view('learning.renewals.rules', [
            'rules' => DB::table('renewal_rules as rr')
                ->leftJoin('employee_renewal_cycles as erc', 'erc.rule_id', '=', 'rr.rule_id')
                ->select('rr.*', DB::raw('COUNT(erc.cycle_id) as cycles_count'))
                ->groupBy('rr.rule_id')
                ->orderBy('rr.label')
                ->get(),
            'credentialTypes' => DB::table('employee_credentials')
                ->distinct()->orderBy('credential_type')->pluck('credential_type'),
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'subject_type' => 'required|in:credential,cpd',
            'subject_key' => 'required|string|max:100',
            'label' => 'required|string|max:150',
            'required_hours' => 'required|numeric|min:0|max:9999',
            'cycle_months' => 'required|integer|min:1|max:120',
            'grace_days' => 'nullable|integer|min:0|max:365',
        ]);

        $clash = DB::table('renewal_rules')
            ->where('subject_type', $validated['subject_type'])
            ->where('subject_key', $validated['subject_key'])
            ->exists();

        if ($clash) {
            return back()->withInput()->with('error', 'A rule already covers that subject. Edit the existing one instead.');
        }

        $ruleId = (string) Str::uuid();

        DB::table('renewal_rules')->insert([
            'rule_id' => $ruleId,
            'subject_type' => $validated['subject_type'],
            'subject_key' => $validated['subject_key'],
            'label' => $validated['label'],
            'required_hours' => $validated['required_hours'],
            'cycle_months' => $validated['cycle_months'],
            'grace_days' => $validated['grace_days'] ?? 0,
            'is_active' => true,
            'created_by' => $this->currentEmployeeId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::record('create_renewal_rule', 'renewal_rules', $ruleId, afterState: $validated);

        return redirect()->route('learning.renewals.rules')
            ->with('success', 'Rule saved. Run "Open cycles" to apply it to current employees.');
    }

    /**
     * Open the cycles a new or changed rule implies.
     *
     * Explicit rather than automatic: opening cycles across every active
     * employee is a bulk write, and doing it silently on rule save would make a
     * typo expensive to unpick.
     */
    public function syncCycles()
    {
        $employees = DB::table('employees')->where('employment_status', 'active')->pluck('employee_id');
        $opened = 0;

        foreach ($employees as $employeeId) {
            $opened += $this->cycles->syncCycles($employeeId);
        }

        AuditTrail::record('sync_renewal_cycles', 'employee_renewal_cycles', null, afterState: [
            'employees' => $employees->count(),
            'cycles_opened' => $opened,
        ]);

        return back()->with('success', "Opened {$opened} cycle(s) across {$employees->count()} active employee(s).");
    }

    /**
     * Everyone at risk of falling short before renewal.
     */
    public function atRisk(Request $request)
    {
        $departmentId = $request->query('department');

        return view('learning.renewals.index', [
            'cycles' => $this->cycles->atRisk(
                fn ($query) => $this->scopeToVisibleEmployees($query),
                $departmentId,
            ),
            'departments' => DB::table('departments')->orderBy('name')
                ->get(['department_id', 'name as department_name']),
            'departmentId' => $departmentId,
        ]);
    }

    /**
     * The employee's own cycles: how many hours they owe and by when.
     *
     * Open to every role. The oversight pages answer the hospital's question;
     * this one answers the individual's, and hiding it from staff would mean
     * the only people who can see a deficit are the ones who cannot fix it.
     */
    public function myCycles(Request $request)
    {
        $employeeId = $request->query('employee') ?: $this->currentEmployeeId();

        if ($employeeId !== $this->currentEmployeeId()) {
            $this->authorizeEmployeeAccess($employeeId);
        }

        if (! $employeeId) {
            return view('learning.my-cycles', [
                'cycles' => collect(),
                'employee' => null,
                'unlinked' => true,
            ]);
        }

        // Open any cycle a rule implies but this employee has not had created
        // yet — someone hired after the last sync should not see a blank page.
        $this->cycles->syncCycles($employeeId);

        return view('learning.my-cycles', [
            'cycles' => $this->cycles->cyclesFor($employeeId),
            'employee' => DB::table('employees')->where('employee_id', $employeeId)
                ->first(['employee_id', 'first_name', 'last_name', 'employee_code']),
            'unlinked' => false,
        ]);
    }

    // ── Accreditation reporting ──────────────────────────────

    /**
     * One row per employee: competency proficiency, credential standing, and
     * training completion, filterable by department and role.
     *
     * This is the compilation a JCI survey asks for. The standard code is tagged
     * on `competency_categories`, one join above the competency itself; it has
     * been captured since the competency module shipped and never reported on.
     */
    public function accreditationReport(Request $request)
    {
        $departmentId = $request->query('department');
        $roleId = $request->query('role');
        $standard = $request->query('standard');

        $rows = $this->scopeToVisibleEmployees(
            DB::table('employees as e')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->leftJoin('roles as r', 'r.role_id', '=', 'e.role_id')
                ->where('e.employment_status', 'active')
                ->select([
                    'e.employee_id', 'e.employee_code', 'e.first_name', 'e.last_name',
                    'e.position_title', 'd.name as department_name', 'r.role_name',
                ])
        );

        if ($departmentId) {
            $rows->where('e.department_id', $departmentId);
        }

        if ($roleId) {
            $rows->where('e.role_id', $roleId);
        }

        $employees = $rows->orderBy('d.name')->orderBy('e.last_name')->get();
        $ids = $employees->pluck('employee_id')->all();

        $competency = $this->competencyRollup($ids, $standard);
        $credentials = $this->credentialRollup($ids);
        $training = $this->trainingRollup($ids);

        foreach ($employees as $employee) {
            $employee->competency = $competency[$employee->employee_id] ?? null;
            $employee->credentials = $credentials[$employee->employee_id] ?? null;
            $employee->training = $training[$employee->employee_id] ?? null;
            $employee->compliant = ($employee->credentials->expired ?? 0) === 0
                && ($employee->training->outstanding ?? 0) === 0;
        }

        return view('learning.accreditation', [
            'employees' => $employees,
            'departments' => DB::table('departments')->orderBy('name')
                ->get(['department_id', 'name as department_name']),
            'roles' => DB::table('roles')->orderBy('role_name')->get(['role_id', 'role_name']),
            'standards' => DB::table('competency_categories')->whereNotNull('jci_standard_code')
                ->distinct()->orderBy('jci_standard_code')->pluck('jci_standard_code'),
            'filters' => ['department' => $departmentId, 'role' => $roleId, 'standard' => $standard],
        ]);
    }

    /**
     * Assessed competencies and average proficiency, optionally narrowed to one
     * JCI standard.
     *
     * Reassessments mean an employee can hold several rows for one competency.
     * Only the newest counts, so the rows are reduced in PHP the same way
     * CompetencyGapAnalysisService does it — a SQL AVG over every row would
     * quietly weight anyone reassessed more often.
     */
    private function competencyRollup(array $employeeIds, ?string $standard)
    {
        if (! $employeeIds) {
            return collect();
        }

        $query = DB::table('competency_assessments as ca')
            ->join('competencies as c', 'c.competency_id', '=', 'ca.competency_id')
            ->join('competency_categories as cc', 'cc.category_id', '=', 'c.category_id')
            ->whereIn('ca.employee_id', $employeeIds)
            ->select([
                'ca.employee_id', 'ca.competency_id', 'ca.current_proficiency',
                'ca.assessed_date', 'c.required_proficiency',
            ])
            ->orderByDesc('ca.assessed_date');

        if ($standard) {
            $query->where('cc.jci_standard_code', $standard);
        }

        return $query->get()
            ->unique(fn ($row) => $row->employee_id.'|'.$row->competency_id)
            ->groupBy('employee_id')
            ->map(fn ($rows) => (object) [
                'assessed' => $rows->count(),
                'avg_proficiency' => round($rows->avg('current_proficiency'), 1),
                'at_target' => $rows->filter(
                    fn ($r) => $r->current_proficiency >= $r->required_proficiency
                )->count(),
            ]);
    }

    private function credentialRollup(array $employeeIds)
    {
        if (! $employeeIds) {
            return collect();
        }

        // Window boundaries come from CredentialStatus so this report and the
        // dashboard cannot disagree about what "expiring" means. Bound as
        // parameters, which keeps the query portable to the sqlite test suite.
        return DB::table('employee_credentials')
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->select('employee_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < ? THEN 1 ELSE 0 END) as expired',
                [CredentialStatus::today()],
            )
            ->selectRaw(
                'SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END) as expiring',
                [CredentialStatus::today(), CredentialStatus::windowEnd()],
            )
            ->get()
            ->keyBy('employee_id');
    }

    private function trainingRollup(array $employeeIds)
    {
        if (! $employeeIds) {
            return collect();
        }

        return DB::table('course_enrollments')
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->select([
                'employee_id',
                DB::raw('COUNT(*) as enrolled'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status <> 'completed' AND assignment_id IS NOT NULL THEN 1 ELSE 0 END) as outstanding"),
                DB::raw('SUM(COALESCE(cpd_hours_earned, 0)) as cpd_hours'),
            ])
            ->get()
            ->keyBy('employee_id');
    }

    // ── Account coverage ─────────────────────────────────────

    /**
     * Which employees have a login and which are tracked by proxy.
     *
     * Read-only reporting. Being proxy-tracked is a supported state, not a
     * defect: everything in the compliance layer works for someone with no
     * account. What this page exists for is to make the split visible, so
     * "nobody told them" can be answered with a list rather than a guess.
     */
    public function accountCoverage(Request $request)
    {
        $filter = $request->query('filter', 'all');   // all | linked | unlinked

        $query = DB::table('employees as e')
            ->leftJoin('users as u', 'u.employee_id', '=', 'e.employee_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->select([
                'e.employee_id', 'e.employee_code', 'e.first_name', 'e.last_name',
                'e.email', 'e.employment_status', 'e.position_title',
                'd.name as department_name', 'u.id as user_id', 'u.role', 'u.email as account_email',
                'u.email_verified_at',
            ]);

        match ($filter) {
            'linked' => $query->whereNotNull('u.id'),
            'unlinked' => $query->whereNull('u.id'),
            default => null,
        };

        $employees = $query->orderBy('e.last_name')->get();

        $totals = DB::table('employees as e')
            ->leftJoin('users as u', 'u.employee_id', '=', 'e.employee_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN u.id IS NULL THEN 1 ELSE 0 END) as unlinked')
            ->selectRaw('SUM(CASE WHEN u.id IS NULL AND e.email IS NULL THEN 1 ELSE 0 END) as unreachable')
            ->first();

        return view('learning.accounts', [
            'employees' => $employees,
            'filter' => $filter,
            'totals' => $totals,
            'orphanAccounts' => DB::table('users')->whereNull('employee_id')
                ->get(['id', 'name', 'email', 'role']),
        ]);
    }
}
