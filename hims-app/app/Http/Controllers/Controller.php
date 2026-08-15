<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Return the current user's linked employee_id, or null if none.
     * Use this everywhere instead of auth()->user()->employee_id to avoid
     * crashing NOT NULL FK inserts when the field is nullable on users.
     */
    protected function currentEmployeeId(): ?string
    {
        return auth()->check() ? auth()->user()->employee_id : null;
    }

    /**
     * Abort unless the current user may act on this employee's records.
     */
    protected function authorizeEmployeeAccess(?string $employeeId): void
    {
        abort_unless(
            $this->canAccessEmployee($employeeId),
            403,
            'You do not have access to that employee record.'
        );
    }

    /**
     * Admin and HR see every employee; supervisors see the people who actually
     * report to them; everyone else may only reach their own record.
     *
     * A supervisor's reach is the reporting line, not the department. Sharing a
     * department with somebody is not authority over them — two head nurses on
     * the same ward each supervise their own team, and neither supervises the
     * other. `employees.supervisor_id` is the only record of who answers to
     * whom, so it is what this reads.
     */
    protected function canAccessEmployee(?string $employeeId): bool
    {
        $user = auth()->user();

        if (! $user || ! $employeeId) {
            return false;
        }

        if ($user->seesWholeOrganisation()) {
            return true;
        }

        if ($user->employee_id === $employeeId) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee_id) {
            return DB::table('employees')->where('employee_id', $employeeId)->value('supervisor_id') === $user->employee_id;
        }

        return false;
    }

    /**
     * Constrain a query to the rows the current user is allowed to see.
     * Admin/HR are unrestricted, supervisors get their direct reports plus
     * themselves, and staff get only themselves.
     *
     * The supervisor branch matches on `employees.supervisor_id`, so a
     * supervisor with nobody reporting to them sees only their own row — which
     * is the honest answer, where the previous department match quietly granted
     * reach over colleagues they have no authority over.
     *
     * `$departmentColumn` is retained for call-site compatibility and is no
     * longer read; the supervisor column is derived from the employee column so
     * the helper still works against any alias (`e.`, `emp.`, unqualified).
     *
     * @param  Builder  $query
     * @param  string  $employeeColumn  qualified employees.employee_id column
     * @param  string  $departmentColumn  unused, kept so existing calls stay valid
     */
    protected function scopeToVisibleEmployees($query, string $employeeColumn = 'e.employee_id', string $departmentColumn = 'e.department_id')
    {
        $user = auth()->user();

        if (! $user || $user->seesWholeOrganisation()) {
            return $query;
        }

        $employeeId = $user->employee_id ?? '';

        if ($user->isSupervisor() && $employeeId) {
            $supervisorColumn = str_replace('employee_id', 'supervisor_id', $employeeColumn);

            // Their reports, and themselves: a supervisor still has their own
            // CPD log, credentials and reviews to look at.
            return $query->where(function ($q) use ($supervisorColumn, $employeeColumn, $employeeId) {
                $q->where($supervisorColumn, $employeeId)
                    ->orWhere($employeeColumn, $employeeId);
            });
        }

        // Staff, and anyone with no linked profile, see only their own row.
        return $query->where($employeeColumn, $employeeId);
    }

    /**
     * May the current account write a review about this employee?
     *
     * The rule is identity, not role: the acting account's linked employee must
     * be the subject's recorded supervisor. Admin and HR get no blanket pass —
     * they reach outside the chain only through the exception path below, and
     * only when it is genuinely open.
     *
     * Self-review is refused first and unconditionally. No role is exempt from
     * that, including an admin reviewing themselves.
     */
    protected function isLegitimateReviewerFor(?string $employeeId): bool
    {
        $actor = $this->currentEmployeeId();
        $user = auth()->user();

        if (! $actor || ! $employeeId || $actor === $employeeId || ! $user?->canCompleteReviews()) {
            return false;
        }

        return DB::table('employees')->where('employee_id', $employeeId)->value('supervisor_id') === $actor;
    }

    /**
     * Why an admin or HR account may review outside the reporting line, or null
     * when no exception applies and the review must be refused.
     *
     * Four situations, all of them cases where the chain cannot answer:
     *  - no_supervisor            the employee has no supervisor recorded
     *  - supervisor_unavailable   the supervisor is on leave, suspended or gone
     *  - supervisor_account_unavailable the supervisor lacks a usable HIMS role
     *  - supervisor_is_subject    the supervisor is the subject of this cycle
     *
     * Returning the basis rather than a boolean is what lets the caller stamp it
     * on the review, so an out-of-chain review is never silently ordinary.
     */
    protected function reviewExceptionBasis(?string $employeeId, ?string $cycleId = null): ?string
    {
        $user = auth()->user();

        if (! $user || ! $user->seesWholeOrganisation() || ! $employeeId) {
            return null;
        }

        $supervisorId = DB::table('employees')->where('employee_id', $employeeId)->value('supervisor_id');

        if (! $supervisorId) {
            return 'no_supervisor';
        }

        $status = DB::table('employees')->where('employee_id', $supervisorId)->value('employment_status');

        if (in_array($status, ['on_leave', 'suspended', 'resigned'], true)) {
            return 'supervisor_unavailable';
        }

        // The supervisor being reviewed in this same cycle cannot also be the
        // one writing their subordinate's review without a conflict.
        if ($cycleId && DB::table('performance_reviews')
            ->where('employee_id', $supervisorId)
            ->where('cycle_id', $cycleId)
            ->exists()) {
            return 'supervisor_is_subject';
        }

        if (! DB::table('users')
            ->where('employee_id', $supervisorId)
            ->whereIn('role', ['supervisor', 'hr_manager', 'admin'])
            ->exists()) {
            return 'supervisor_account_unavailable';
        }

        return null;
    }
}
