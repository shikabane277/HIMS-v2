<?php

namespace App\Http\Controllers;

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
     * Admin and HR see every employee; supervisors are limited to their own
     * department; everyone else may only reach their own record.
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

        if ($user->isSupervisor() && ($dept = $user->departmentId())) {
            return DB::table('employees')->where('employee_id', $employeeId)->value('department_id') === $dept;
        }

        return false;
    }

    /**
     * Constrain a query to the rows the current user is allowed to see.
     * Admin/HR are unrestricted, supervisors get their department, and staff
     * get only themselves.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $employeeColumn   qualified employees.employee_id column
     * @param  string  $departmentColumn qualified employees.department_id column
     */
    protected function scopeToVisibleEmployees($query, string $employeeColumn = 'e.employee_id', string $departmentColumn = 'e.department_id')
    {
        $user = auth()->user();

        if (! $user || $user->seesWholeOrganisation()) {
            return $query;
        }

        if ($user->isSupervisor() && ($dept = $user->departmentId())) {
            return $query->where($departmentColumn, $dept);
        }

        // Staff, and anyone with no linked profile, see only their own row.
        return $query->where($employeeColumn, $user->employee_id ?? '');
    }
}
