<?php

namespace App\Services;

use App\Support\AuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mandatory training: the hospital requiring a course or session of someone.
 *
 * For courses this is now the *only* path onto an enrolment — course
 * self-enrolment was removed, so every `course_enrollments` row originates here
 * and carries an `assignment_id` (which act required this), a `due_date`, and an
 * `enrolled_by` pointing at the assigner rather than the employee. Sessions still
 * accept self-registration through `training.register`, so a
 * `training_registrations` row with a null `assignment_id` means somebody signed
 * themselves up.
 *
 * NOTHING HERE NEEDS A `users` ROW. Targets are resolved from `employees`, and
 * every row written is keyed on `employee_id`. An employee tracked by proxy —
 * no login, no email even — is assigned, counted, and chased identically.
 */
class TrainingAssignmentService
{
    /**
     * Require several courses and/or sessions of one target in a single act.
     *
     * Each subject arrives as a prefixed composite — `course:<uuid>` /
     * `session:<uuid>` — because one submission may mix both and a bare UUID
     * cannot say which table it belongs to.
     *
     * Every subject still gets its own `training_assignments` row: two courses
     * have separate completion states, separate rosters and separate compliance
     * rates, so folding them into one record would leave the drill-down unable to
     * say what a person actually still owes. What the wrapper adds is atomicity —
     * one transaction, so a mixed submission either lands whole or not at all
     * rather than leaving half the requirements in place with no way to tell which
     * half.
     *
     * @param  array<int, string>  $subjects  `course:<uuid>` / `session:<uuid>`
     * @return array{assignment_ids: array<int, string>, created: int, skipped: int, employees: int}
     */
    public function assignMany(
        array $subjects,
        string $targetType,
        ?string $targetId,
        ?string $requiredBy,
        ?string $assignedBy,
        ?string $reason = null,
    ): array {
        return DB::transaction(function () use ($subjects, $targetType, $targetId, $requiredBy, $assignedBy, $reason) {
            $ids = [];
            $created = 0;
            $skipped = 0;
            $employees = 0;

            foreach ($subjects as $subject) {
                [$type, $id] = explode(':', $subject, 2);

                $result = $this->assign($type, $id, $targetType, $targetId, $requiredBy, $assignedBy, $reason);

                $ids[] = $result['assignment_id'];
                $created += $result['created'];
                $skipped += $result['skipped'];
                // The target expands to the same people for every subject, so
                // this is a headcount, not a running total.
                $employees = $result['employees'];
            }

            return [
                'assignment_ids' => $ids,
                'created' => $created,
                'skipped' => $skipped,
                'employees' => $employees,
            ];
        });
    }

    /**
     * Create the assignment and expand it into per-employee rows.
     *
     * @param  string  $subjectType  course | session
     * @param  string  $targetType  employee | department | role | all
     * @param  string|null  $targetId  null when targetType = all
     * @return array{assignment_id: string, created: int, skipped: int, employees: int}
     */
    public function assign(
        string $subjectType,
        string $subjectId,
        string $targetType,
        ?string $targetId,
        ?string $requiredBy,
        ?string $assignedBy,
        ?string $reason = null,
    ): array {
        $employeeIds = $this->resolveTargets($targetType, $targetId);
        $assignmentId = (string) Str::uuid();

        DB::table('training_assignments')->insert([
            'assignment_id' => $assignmentId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'required_by' => $requiredBy,
            'reason' => $reason,
            'assigned_by' => $assignedBy,
            'expanded_count' => count($employeeIds),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $subjectType === 'course'
            ? $this->expandCourse($assignmentId, $subjectId, $employeeIds, $requiredBy, $assignedBy)
            : $this->expandSession($assignmentId, $subjectId, $employeeIds, $requiredBy, $assignedBy);

        AuditTrail::record(
            'assign_training',
            'training_assignments',
            $assignmentId,
            afterState: [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'required_by' => $requiredBy,
                'employees' => count($employeeIds),
                'created' => $result['created'],
            ],
        );

        return $result + ['assignment_id' => $assignmentId, 'employees' => count($employeeIds)];
    }

    /**
     * Who a target expands to. Only active employees — assigning mandatory
     * training to someone who has resigned would show up forever as a
     * compliance gap nobody can close.
     *
     * @return array<int, string>
     */
    public function resolveTargets(string $targetType, ?string $targetId): array
    {
        $query = DB::table('employees')->where('employment_status', 'active');

        match ($targetType) {
            'employee' => $query->where('employee_id', $targetId),
            'department' => $query->where('department_id', $targetId),
            'role' => $query->where('role_id', $targetId),
            'all' => null,
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('employee_id')->all();
    }

    /**
     * `course_enrollments` has a unique key on (employee_id, course_id) and no
     * `timestamps()`. Someone already enrolled keeps their row — re-inserting
     * would throw, and overwriting would wipe progress and completion. They are
     * counted as skipped, not failed: they already have what the assignment
     * asked for.
     */
    private function expandCourse(
        string $assignmentId,
        string $courseId,
        array $employeeIds,
        ?string $requiredBy,
        ?string $assignedBy,
    ): array {
        $existing = DB::table('course_enrollments')
            ->where('course_id', $courseId)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->flip();

        $rows = [];

        foreach ($employeeIds as $employeeId) {
            if ($existing->has($employeeId)) {
                continue;
            }

            $rows[] = [
                'enrollment_id' => (string) Str::uuid(),
                'employee_id' => $employeeId,
                'course_id' => $courseId,
                'enrolled_by' => $assignedBy,
                'assignment_id' => $assignmentId,
                'enrollment_date' => now()->toDateString(),
                'due_date' => $requiredBy,
                'status' => 'enrolled',
                'progress_pct' => 0,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('course_enrollments')->insert($chunk);
        }

        return ['created' => count($rows), 'skipped' => count($employeeIds) - count($rows)];
    }

    /**
     * Sessions have a capacity. Assigning past it would silently produce a
     * roster the venue cannot hold, so the expansion stops at the limit and
     * reports the shortfall through `skipped` — the caller surfaces it.
     */
    private function expandSession(
        string $assignmentId,
        string $sessionId,
        array $employeeIds,
        ?string $requiredBy,
        ?string $assignedBy,
    ): array {
        $session = DB::table('training_sessions')->where('session_id', $sessionId)->first();
        $taken = DB::table('training_registrations')->where('session_id', $sessionId)->count();
        $room = max(0, (int) ($session->capacity ?? 0) - $taken);

        $existing = DB::table('training_registrations')
            ->where('session_id', $sessionId)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->flip();

        $rows = [];

        foreach ($employeeIds as $employeeId) {
            if ($existing->has($employeeId) || count($rows) >= $room) {
                continue;
            }

            $rows[] = [
                'registration_id' => (string) Str::uuid(),
                'session_id' => $sessionId,
                'employee_id' => $employeeId,
                'registered_by' => $assignedBy,
                'assignment_id' => $assignmentId,
                'required_by' => $requiredBy,
                'registration_date' => now(),
                'status' => 'registered',
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('training_registrations')->insert($chunk);
        }

        return ['created' => count($rows), 'skipped' => count($employeeIds) - count($rows)];
    }

    /**
     * Compliance rate for one assignment.
     *
     * "Percent complete" is people, not content: `count(completed) / count(assigned)`.
     * Per-person progress would need `progress_pct` to mean something, and
     * nothing writes it — `course_modules` is dead schema, so there is no
     * content delivery to measure against.
     *
     * @return array{total: int, complete: int, outstanding: int, overdue: int, rate: float}
     */
    public function complianceFor(object $assignment): array
    {
        $rows = $this->rosterQuery($assignment)->get();

        $total = $rows->count();
        $complete = $rows->where('is_complete', 1)->count();
        $outstanding = $total - $complete;

        // required_by is one date for the whole assignment, so once it passes
        // every unfinished person is overdue.
        $pastDue = $assignment->required_by && now()->toDateString() > $assignment->required_by;

        return [
            'total' => $total,
            'complete' => $complete,
            'outstanding' => $outstanding,
            'overdue' => $pastDue ? $outstanding : 0,
            'rate' => $total > 0 ? round($complete / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Everyone on an assignment, with their completion state — the drill-down
     * behind the compliance percentage.
     */
    public function roster(object $assignment): Collection
    {
        return $this->rosterQuery($assignment)
            ->orderBy('is_complete')
            ->orderBy('e.last_name')
            ->get();
    }

    private function rosterQuery(object $assignment)
    {
        if ($assignment->subject_type === 'course') {
            return DB::table('course_enrollments as ce')
                ->join('employees as e', 'e.employee_id', '=', 'ce.employee_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->where('ce.assignment_id', $assignment->assignment_id)
                ->select([
                    'e.employee_id',
                    'e.employee_code',
                    'e.email',
                    'e.first_name',
                    'e.last_name',
                    'd.name as department_name',
                    'ce.status',
                    'ce.completed_at',
                    'ce.due_date',
                    // Aliased so the drill-down can act on a row without caring
                    // whether it is an enrolment or a registration.
                    'ce.enrollment_id as record_id',
                    DB::raw("(CASE WHEN ce.status = 'completed' THEN 1 ELSE 0 END) as is_complete"),
                ]);
        }

        return DB::table('training_registrations as tr')
            ->join('employees as e', 'e.employee_id', '=', 'tr.employee_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('tr.assignment_id', $assignment->assignment_id)
            ->select([
                'e.employee_id',
                'e.employee_code',
                'e.email',
                'e.first_name',
                'e.last_name',
                'd.name as department_name',
                'tr.status',
                'tr.check_in_time as completed_at',
                'tr.required_by as due_date',
                'tr.registration_id as record_id',
                DB::raw("(CASE WHEN tr.status = 'attended' OR tr.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as is_complete"),
            ]);
    }

    /**
     * How many people are past their assignment's due date and still not done,
     * across every assignment — the dashboard's whole-organisation figure.
     *
     * This exists so the dashboard cannot disagree with the Required Training
     * tab. `complianceFor()` decides "overdue" one assignment at a time, and a
     * count written independently in `DashboardController` would be a second
     * definition of the same word, free to drift the first time either changed.
     * The rule is the same one, expressed as two aggregates instead of a loop:
     * `required_by` has passed and the row is not complete. Rows on assignments
     * with no `required_by` are never overdue — nothing was ever asked of them
     * by a date.
     *
     * Two queries rather than a union because "complete" means a different
     * column per subject type, exactly as in `rosterQuery()`: a course enrolment
     * is `completed`, a session registration is `attended` or checked in.
     * Comparing `Y-m-d` strings keeps this portable to sqlite.
     */
    public function overdueCount(): int
    {
        $today = now()->toDateString();

        $courses = DB::table('course_enrollments as ce')
            ->join('training_assignments as ta', 'ta.assignment_id', '=', 'ce.assignment_id')
            ->whereNotNull('ta.required_by')
            ->where('ta.required_by', '<', $today)
            ->where('ce.status', '!=', 'completed')
            ->count();

        $sessions = DB::table('training_registrations as tr')
            ->join('training_assignments as ta', 'ta.assignment_id', '=', 'tr.assignment_id')
            ->whereNotNull('ta.required_by')
            ->where('ta.required_by', '<', $today)
            ->where('tr.status', '!=', 'attended')
            ->whereNull('tr.check_in_time')
            ->count();

        return $courses + $sessions;
    }

    /**
     * Assignments with their compliance figures, newest first.
     */
    public function listWithCompliance(int $limit = 100): Collection
    {
        return DB::table('training_assignments')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($assignment) {
                $assignment->subject_name = $this->subjectName($assignment);
                $assignment->target_name = $this->targetName($assignment);
                $assignment->compliance = $this->complianceFor($assignment);

                return $assignment;
            });
    }

    public function subjectName(object $assignment): string
    {
        $name = $assignment->subject_type === 'course'
            ? DB::table('courses')->where('course_id', $assignment->subject_id)->value('title')
            : DB::table('training_sessions')->where('session_id', $assignment->subject_id)->value('title');

        return $name ?: '(removed)';
    }

    public function targetName(object $assignment): string
    {
        return match ($assignment->target_type) {
            'all' => 'All active employees',
            'department' => (string) (DB::table('departments')
                ->where('department_id', $assignment->target_id)->value('name') ?: 'Department'),
            'role' => (string) (DB::table('roles')
                ->where('role_id', $assignment->target_id)->value('role_name') ?: 'Role'),
            // Concatenated in PHP, not SQL: `CONCAT()` and `||` disagree between
            // MySQL and the sqlite the suite runs on.
            'employee' => $this->employeeName($assignment->target_id),
            default => $assignment->target_type,
        };
    }

    private function employeeName(?string $employeeId): string
    {
        $row = DB::table('employees')
            ->where('employee_id', $employeeId)
            ->first(['first_name', 'last_name']);

        return $row ? trim($row->first_name.' '.$row->last_name) : 'Employee';
    }
}
