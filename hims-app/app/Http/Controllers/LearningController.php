<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Services\RenewalCycleService;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_courses' => DB::table('courses')->where('is_active', true)->count(),
            'completions_this_month' => DB::table('course_enrollments')->where('status', 'completed')->whereMonth('completed_at', now()->month)->count(),
            'avg_completion_rate' => round(DB::table('course_enrollments')->avg(DB::raw('progress_pct')) ?? 0),
            'certificates_issued' => DB::table('certificates')->count(),
        ];

        // The catalogue's category control used to be a hardcoded <select> with no
        // name and no form — it filtered nothing. Options now come from the
        // categories actually in use, so the list cannot offer an empty result.
        $categories = DB::table('courses')->where('is_active', true)
            ->whereNotNull('category')->distinct()
            ->orderBy('category')->pluck('category');

        $category = $request->query('category');
        if (! $categories->contains($category)) {
            $category = null;
        }

        $courses = DB::table('courses as c')
            ->leftJoin('course_enrollments as ce', 'c.course_id', '=', 'ce.course_id')
            ->select('c.*', DB::raw('COUNT(ce.enrollment_id) as enrollments_count'))
            ->where('c.is_active', true)
            ->when($category, fn ($q) => $q->where('c.category', $category))
            ->groupBy('c.course_id')
            ->orderByDesc('enrollments_count')->limit(20)->get();

        $pathways = DB::table('learning_pathways as lp')
            ->leftJoin('pathway_courses as pc', 'lp.pathway_id', '=', 'pc.pathway_id')
            ->select('lp.*', DB::raw('COUNT(pc.id) as courses_count'))
            ->groupBy('lp.pathway_id')->get();

        $cpd_records = $this->scopeToVisibleEmployees(
            DB::table('cpd_records as cr')
                ->join('employees as e', 'cr.employee_id', '=', 'e.employee_id')
                ->leftJoin('employees as v', 'cr.verified_by', '=', 'v.employee_id')
                ->select('cr.*',
                    DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"))
        )
            ->orderByDesc('cr.date_earned')->limit(10)->get();

        // The New Course and New Pathway modals are hosted on this page, so the
        // competency checklist and the role checklist load with the index. The
        // Edit Course modal is the same one form re-pointed per row, so it needs
        // each course's existing tags to tick on open — one query for the page
        // rather than one per row.
        $competencies = $this->taggableCompetencies();
        $roles = DB::table('roles')->orderBy('role_name')->get();

        $courseTags = DB::table('course_competencies')
            ->whereIn('course_id', $courses->pluck('course_id'))
            ->get()
            ->groupBy('course_id')
            ->map(fn ($rows) => $rows->pluck('competency_id')->implode(','));

        return view('learning.index', compact(
            'stats', 'courses', 'pathways', 'cpd_records',
            'competencies', 'roles', 'categories', 'category', 'courseTags',
        ) + ['institutional' => $this->institutionalHeadline()]);
    }

    /**
     * The two numbers that say whether the hospital-facing half of Learning needs
     * attention, for the strip on the Overview tab. Null for staff, who have no
     * oversight tabs to send them to.
     *
     * Deliberately two counts rather than TrainingAssignmentService::
     * listWithCompliance(), which runs a roster query per assignment — the
     * Overview should not pay for the Required Training page. Both are scoped the
     * same way as the pages they link to, so the strip cannot promise a figure the
     * destination then contradicts.
     *
     * @return array{outstanding: int, overdue: int, behind: int}|null
     */
    private function institutionalHeadline(): ?array
    {
        if (! auth()->user()?->can('view-compliance')) {
            return null;
        }

        $today = now()->toDateString();

        // Required course work still open. assignment_id is non-null on every
        // enrolment now that self-enrolment is gone, but the filter stays: legacy
        // rows predate the change and nobody required those by a date.
        $courses = $this->scopeToVisibleEmployees(
            DB::table('course_enrollments as ce')
                ->join('employees as e', 'e.employee_id', '=', 'ce.employee_id')
                ->whereNotNull('ce.assignment_id')
                ->where('ce.status', '<>', 'completed')
        );

        $sessions = $this->scopeToVisibleEmployees(
            DB::table('training_registrations as tr')
                ->join('employees as e', 'e.employee_id', '=', 'tr.employee_id')
                ->whereNotNull('tr.assignment_id')
                ->where('tr.status', '<>', 'attended')
                ->whereNull('tr.check_in_time')
        );

        $outstanding = (clone $courses)->count() + (clone $sessions)->count();

        $overdue = (clone $courses)->whereNotNull('ce.due_date')->where('ce.due_date', '<', $today)->count()
            + (clone $sessions)->whereNotNull('tr.required_by')->where('tr.required_by', '<', $today)->count();

        $behind = app(RenewalCycleService::class)
            ->atRisk(fn ($query) => $this->scopeToVisibleEmployees($query))
            ->whereIn('risk', ['at_risk', 'shortfall'])
            ->count();

        return ['outstanding' => $outstanding, 'overdue' => $overdue, 'behind' => $behind];
    }

    /**
     * Competencies for the course-tagging checklists, carrying the category name
     * so a list of a hundred entries is still readable. Shared by the New Course
     * modal and the re-tagging panel on a course page.
     */
    private function taggableCompetencies()
    {
        return DB::table('competencies as c')
            ->leftJoin('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->select('c.competency_id', 'c.competency_name', 'c.competency_code', 'cc.category_name')
            ->orderBy('c.competency_name')->get();
    }

    public function storeCourse(Request $request)
    {
        $request->validate($this->courseRules());

        $courseId = (string) Str::uuid();

        DB::table('courses')->insert([
            'course_id' => $courseId,
            'course_code' => $request->course_code ?: null,
            'title' => $request->title,
            'category' => $request->category,
            'cpd_hours' => $request->cpd_hours,
            'difficulty_level' => $request->difficulty_level,
            'estimated_duration' => $request->estimated_duration ?: null,
            'description' => $request->description,
            'passing_score' => $request->passing_score ?? 70,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'is_active' => true,
            'created_by' => $this->currentEmployeeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tagging the course to the competencies it remediates is what makes it
        // show up as a recommendation on the gap analysis screens.
        $this->syncCourseCompetencies($courseId, $request->input('competencies', []));

        return redirect()->route('learning.courses.show', $courseId)->with('success', 'Course created successfully.');
    }

    /**
     * Edit a course from the catalogue's Edit modal. Gated on manage-learning by
     * the route; the catalogue hides the button from everyone else.
     *
     * `is_active` is deliberately not editable here. The catalogue lists active
     * courses only, so retiring one from this modal would remove the row the modal
     * was opened from and leave no way back to it.
     */
    public function updateCourse(Request $request, string $courseId)
    {
        abort_if(! DB::table('courses')->where('course_id', $courseId)->exists(), 404);

        $request->validate($this->courseRules($courseId));

        DB::table('courses')->where('course_id', $courseId)->update([
            'course_code' => $request->course_code ?: null,
            'title' => $request->title,
            'category' => $request->category,
            'cpd_hours' => $request->cpd_hours,
            'difficulty_level' => $request->difficulty_level,
            'estimated_duration' => $request->estimated_duration ?: null,
            'description' => $request->description,
            'passing_score' => $request->passing_score ?? 70,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'updated_at' => now(),
        ]);

        $this->syncCourseCompetencies($courseId, $request->input('competencies', []));

        return redirect()->route('learning.index')->with('success', 'Course updated.');
    }

    /**
     * One validation shape for both course writes.
     *
     * `course_code` carries a unique index and was previously read off the request
     * unvalidated, so a repeated code reached the database and came back as a 500;
     * `estimated_duration` was likewise unchecked against an integer column. On
     * update the uniqueness rule has to ignore the row being edited, or saving a
     * course without touching its code refuses itself.
     *
     * @return array<string, mixed>
     */
    private function courseRules(?string $ignoreCourseId = null): array
    {
        $code = 'nullable|string|max:30|unique:courses,course_code';

        if ($ignoreCourseId !== null) {
            $code .= ','.$ignoreCourseId.',course_id';
        }

        return [
            'title' => 'required|string|max:300',
            'course_code' => $code,
            'category' => 'required|string|max:50',
            'cpd_hours' => 'required|numeric|min:0|max:999.9',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1|max:100000',
            'passing_score' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'competencies' => 'nullable|array',
            'competencies.*' => 'string|exists:competencies,competency_id',
        ];
    }

    /**
     * Replace a course's competency tags. course_competencies carries no
     * timestamps, so this is a plain delete-then-insert.
     *
     * @param  array<int, string>  $competencyIds
     */
    private function syncCourseCompetencies(string $courseId, array $competencyIds): void
    {
        DB::table('course_competencies')->where('course_id', $courseId)->delete();

        $rows = collect($competencyIds)->filter()->unique()
            ->map(fn ($competencyId) => [
                'id' => (string) Str::uuid(),
                'course_id' => $courseId,
                'competency_id' => $competencyId,
            ])->all();

        if ($rows !== []) {
            DB::table('course_competencies')->insert($rows);
        }
    }

    /**
     * Re-tag an existing course from its detail page. Gated on manage-learning.
     */
    public function updateCourseCompetencies(Request $request, string $courseId)
    {
        abort_unless(auth()->user()->can('manage-learning'), 403);
        abort_if(! DB::table('courses')->where('course_id', $courseId)->exists(), 404);

        $request->validate([
            'competencies' => 'nullable|array',
            'competencies.*' => 'string|exists:competencies,competency_id',
        ]);

        $this->syncCourseCompetencies($courseId, $request->input('competencies', []));

        return back()->with('success', 'Competency tags updated.');
    }

    public function showCourse($id)
    {
        $course = DB::table('courses')->where('course_id', $id)->first();
        abort_if(! $course, 404);

        // Staff see only their own enrollment on a course page; supervisors see
        // their department. Previously this listed every enrollee in the
        // hospital to anyone who opened a course.
        $enrollments = $this->scopeToVisibleEmployees(
            DB::table('course_enrollments as ce')
                ->join('employees as e', 'ce.employee_id', '=', 'e.employee_id')
                ->where('ce.course_id', $id)
                ->select('ce.*', DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
        )
            ->get();

        // Competencies this course is tagged to remediate, so the catalogue page
        // says what the course is actually for.
        $competencies = DB::table('course_competencies as cc')
            ->join('competencies as c', 'cc.competency_id', '=', 'c.competency_id')
            ->where('cc.course_id', $id)
            ->select('c.competency_id', 'c.competency_name', 'c.competency_code')
            ->orderBy('c.competency_name')->get();

        // The full list backs the re-tag picker, which only admin/HR can submit.
        $allCompetencies = auth()->user()->can('manage-learning')
            ? $this->taggableCompetencies()
            : collect();

        return view('learning.courses.show', compact('course', 'enrollments', 'competencies', 'allCompetencies'));
    }

    public function pathwaysIndex()
    {
        // Group by the PK only — learning_pathways has no is_active column, and
        // MySQL resolves the other selected columns as functionally dependent.
        $pathways = DB::table('learning_pathways as lp')
            ->leftJoin('pathway_courses as pc', 'lp.pathway_id', '=', 'pc.pathway_id')
            ->select('lp.*', DB::raw('COUNT(pc.id) as courses_count'))
            ->groupBy('lp.pathway_id')
            ->orderBy('lp.pathway_name')
            ->get();

        // Feeds the New Pathway modal's target-role checklist.
        $roles = DB::table('roles')->orderBy('role_name')->get();

        return view('learning.pathways.index', compact('pathways', 'roles'));
    }

    public function storePathway(Request $request)
    {
        $request->validate([
            'pathway_name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'total_cpd_hours' => 'nullable|numeric|min:0|max:9999.9',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'string|exists:roles,role_id',
        ]);

        DB::table('learning_pathways')->insert([
            'pathway_id' => Str::uuid(),
            'pathway_name' => $request->pathway_name,
            'description' => $request->description,
            'target_roles' => $request->target_roles ? json_encode(array_values($request->target_roles)) : null,
            'total_cpd_hours' => $request->total_cpd_hours ?: null,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'created_by' => $this->currentEmployeeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('learning.pathways.index')->with('success', 'Pathway created.');
    }

    public function cpdIndex(Request $request)
    {
        $records = $this->scopeToVisibleEmployees(
            DB::table('cpd_records as cr')
                ->join('employees as e', 'cr.employee_id', '=', 'e.employee_id')
                ->leftJoin('employees as vb', 'cr.verified_by', '=', 'vb.employee_id')
                ->select('cr.*',
                    DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                    DB::raw("CONCAT(COALESCE(vb.first_name,''),' ',COALESCE(vb.last_name,'')) as verified_by_name"))
        )
            ->when($request->query('focus'), fn ($q, $id) => $q->where('cr.cpd_id', $id))
            ->when($request->query('filter') === 'pending', fn ($q) => $q->where('cr.verified', false))
            ->orderByDesc('cr.date_earned')->paginate(30)->withQueryString();

        // Totals reflect the same scope as the list, so a member of staff sees
        // their own hours rather than the hospital's.
        $totals = $this->scopeToVisibleEmployees(
            DB::table('cpd_records as cr')
                ->join('employees as e', 'cr.employee_id', '=', 'e.employee_id')
        )
            ->selectRaw('COALESCE(SUM(cr.cpd_hours),0) as total_hours')
            ->selectRaw('COALESCE(SUM(CASE WHEN cr.verified = 1 THEN cr.cpd_hours ELSE 0 END),0) as verified_hours')
            ->selectRaw('COUNT(CASE WHEN cr.verified = 0 THEN 1 END) as pending_count')
            ->first();

        $user = auth()->user();

        return view('learning.cpd.index', [
            'records' => $records,
            'totals' => $totals,
            'filter' => $request->query('filter') === 'pending' ? 'pending' : 'all',
            'canVerify' => $user?->can('manage-learning') ?? false,
            // The Record CPD modal is hosted on this page, so its pickers load
            // here — createCpd() and its GET page are gone. Staff log their own
            // hours; admin/HR may log on behalf of anyone, which is why the
            // employee picker only loads for them.
            'canLogForOthers' => $user?->can('manage-learning') ?? false,
            'employees' => $user?->can('manage-learning')
                ? DB::table('employees')->where('employment_status', 'active')
                    ->orderBy('first_name')->get()
                : collect(),
            'courses' => DB::table('courses')->where('is_active', true)->orderBy('title')->get(),
            'sessions' => DB::table('training_sessions')->orderByDesc('session_date')->limit(100)->get(),
        ]);
    }

    public function storeCpd(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|string|exists:employees,employee_id',
            'source_type' => 'required|in:course,training,external',
            'source_id' => 'nullable|string',
            'activity_name' => 'required|string|max:300',
            'cpd_hours' => 'required|numeric|min:0.5|max:999.9',
            'date_earned' => 'required|date|before_or_equal:today',
            'renewal_period' => 'nullable|string|max:20',
        ]);

        $user = auth()->user();

        // Only admin/HR may file CPD against someone else's record; everyone
        // else is pinned to their own profile regardless of what was posted.
        $employeeId = $user->can('manage-learning') && $request->employee_id
            ? $request->employee_id
            : $this->currentEmployeeId();

        if (! $employeeId) {
            return back()->withInput()
                ->with('error', 'Your account is not linked to an employee profile, so CPD cannot be recorded against it.');
        }

        $this->authorizeEmployeeAccess($employeeId);

        // Hours that came from a HIMS course or training session are already
        // evidenced by the completion record, so they post verified. External
        // activity is a claim until HR checks the certificate.
        $isSystemSourced = in_array($request->source_type, ['course', 'training'], true);

        DB::table('cpd_records')->insert([
            'cpd_id' => Str::uuid(),
            'employee_id' => $employeeId,
            'source_type' => $request->source_type,
            'source_id' => $isSystemSourced ? ($request->source_id ?: null) : null,
            'activity_name' => $request->activity_name,
            'cpd_hours' => $request->cpd_hours,
            'date_earned' => $request->date_earned,
            'renewal_period' => $request->renewal_period ?: now()->year,
            'verified' => $isSystemSourced,
            'verified_by' => $isSystemSourced ? $this->currentEmployeeId() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('learning.cpd.index')->with('success', $isSystemSourced
            ? 'CPD recorded and auto-verified from its HIMS source.'
            : 'CPD submitted. External activity stays pending until HR verifies it.');
    }

    /**
     * Record that somebody finished a course.
     *
     * This is the write that was missing: nothing else in the app ever set
     * `course_enrollments.status = 'completed'`, so every assignment compliance
     * rate was pinned at 0% and no course could ever credit CPD. Sessions had
     * their equivalent from the start in TrainingController::checkIn().
     *
     * Completion is recorded *about* somebody, never *by* them: the route is
     * gated to admin/HR/supervisor and supervisors are held to their own
     * department, matching the check-in rule. An admin can still complete their
     * own enrolment — the audit row is what makes that reviewable.
     */
    public function completeEnrollment(Request $request, string $enrollmentId)
    {
        $enrollment = DB::table('course_enrollments as ce')
            ->join('courses as c', 'c.course_id', '=', 'ce.course_id')
            ->where('ce.enrollment_id', $enrollmentId)
            ->select('ce.*', 'c.title', 'c.cpd_hours')
            ->first();

        abort_if(! $enrollment, 404);
        $this->authorizeEmployeeAccess($enrollment->employee_id);

        if ($enrollment->status === 'completed') {
            return back()->with('error', 'That enrolment is already recorded as complete.');
        }

        $hours = (float) $enrollment->cpd_hours;

        DB::transaction(function () use ($enrollment, $enrollmentId, $hours) {
            DB::table('course_enrollments')->where('enrollment_id', $enrollmentId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress_pct' => 100,
                'cpd_hours_earned' => $hours,
            ]);

            // The CPD row is the point of the exercise: RenewalCycleService sums
            // verified cpd_records inside the window, so finishing a course is
            // what actually moves a renewal cycle. A course worth no hours gets
            // no row rather than a 0.0-hour one.
            if ($hours > 0 && ! $this->courseCpdExists($enrollment->employee_id, $enrollment->course_id)) {
                DB::table('cpd_records')->insert([
                    'cpd_id' => Str::uuid(),
                    'employee_id' => $enrollment->employee_id,
                    'source_type' => 'course',
                    'source_id' => $enrollment->course_id,
                    'activity_name' => Str::limit($enrollment->title, 300, ''),
                    'cpd_hours' => $hours,
                    'date_earned' => now()->toDateString(),
                    'renewal_period' => now()->year,
                    // Evidenced by the completion record itself, so it posts
                    // verified — the same rule storeCpd() applies to HIMS-sourced
                    // hours. Only external claims wait on HR.
                    'verified' => true,
                    'verified_by' => $this->currentEmployeeId(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        // Logged because this is the write that moves a compliance figure, and
        // "who says this person completed it" is a question an accreditation
        // survey asks. It is also the only record of who recorded it — there is
        // no completed_by column.
        AuditTrail::record('complete_enrollment', 'course_enrollments', $enrollmentId, afterState: [
            'employee_id' => $enrollment->employee_id,
            'course_id' => $enrollment->course_id,
            'course' => $enrollment->title,
            'cpd_hours_credited' => $hours,
            'assignment_id' => $enrollment->assignment_id,
        ]);

        app(NotificationService::class)->notify(
            $enrollment->employee_id,
            'course_completed',
            'Course completed: '.Str::limit($enrollment->title, 60),
            $hours > 0
                ? "Your completion has been recorded and {$hours} CPD hour(s) credited."
                : 'Your completion has been recorded.',
            'course_enrollments',
            $enrollmentId,
        );

        return back()->with('success', $hours > 0
            ? "Completion recorded. {$hours} CPD hour(s) credited."
            : 'Completion recorded.');
    }

    /**
     * Undo a completion. Admin/HR only — narrower than recording one, because
     * this withdraws evidence rather than adding it, and a compliance figure
     * that can be quietly walked back is worth less than one that cannot.
     */
    public function reopenEnrollment(Request $request, string $enrollmentId)
    {
        abort_unless(auth()->user()->can('manage-learning'), 403);

        $enrollment = DB::table('course_enrollments')->where('enrollment_id', $enrollmentId)->first();
        abort_if(! $enrollment, 404);

        if ($enrollment->status !== 'completed') {
            return back()->with('error', 'That enrolment is not marked complete.');
        }

        DB::transaction(function () use ($enrollment, $enrollmentId) {
            DB::table('course_enrollments')->where('enrollment_id', $enrollmentId)->update([
                'status' => 'in_progress',
                'completed_at' => null,
                'progress_pct' => 0,
                'cpd_hours_earned' => 0,
            ]);

            // Withdraw the credit too, or the renewal cycle keeps counting hours
            // for a course no longer recorded as finished. Keyed on
            // (employee, course) — the natural identity of "CPD from this
            // course" — which will also take a manually logged duplicate of the
            // same fact.
            DB::table('cpd_records')
                ->where('employee_id', $enrollment->employee_id)
                ->where('source_type', 'course')
                ->where('source_id', $enrollment->course_id)
                ->delete();
        });

        AuditTrail::record('reopen_enrollment', 'course_enrollments', $enrollmentId, beforeState: [
            'status' => 'completed',
            'completed_at' => $enrollment->completed_at,
            'cpd_hours_earned' => (float) $enrollment->cpd_hours_earned,
        ], afterState: ['status' => 'in_progress']);

        return back()->with('success', 'Completion withdrawn and its CPD credit removed.');
    }

    /**
     * Has this employee already banked CPD for this course? Guards the
     * complete → reopen → complete path against stacking duplicate credit.
     */
    private function courseCpdExists(string $employeeId, string $courseId): bool
    {
        return DB::table('cpd_records')
            ->where('employee_id', $employeeId)
            ->where('source_type', 'course')
            ->where('source_id', $courseId)
            ->exists();
    }

    /**
     * Approve a pending external CPD claim. Gated on manage-learning (admin/HR).
     */
    public function verifyCpd(Request $request, string $cpdId)
    {
        abort_unless(auth()->user()->can('manage-learning'), 403);

        $record = DB::table('cpd_records')->where('cpd_id', $cpdId)->first();
        abort_if(! $record, 404);

        DB::table('cpd_records')->where('cpd_id', $cpdId)->update([
            'verified' => true,
            'verified_by' => $this->currentEmployeeId(),
            'updated_at' => now(),
        ]);

        app(NotificationService::class)->notify(
            $record->employee_id,
            'cpd_verified',
            'CPD verified: '.Str::limit($record->activity_name, 60),
            "{$record->cpd_hours} CPD hour(s) recorded on {$record->date_earned} have been verified.",
            'cpd_records',
            $cpdId,
        );

        return back()->with('success', 'CPD entry verified.');
    }
}
