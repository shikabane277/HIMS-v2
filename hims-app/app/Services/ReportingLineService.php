<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingLineService
{
    public const REVIEW_ACCESS_ROLES = ['supervisor', 'hr_manager', 'admin'];

    /**
     * Managers available for a new Reports To assignment.
     */
    public function eligibleManagers(?string $excludeEmployeeId = null): Collection
    {
        $query = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('users as u', function ($join) {
                $join->on('u.employee_id', '=', 'e.employee_id')
                    ->whereIn('u.role', self::REVIEW_ACCESS_ROLES);
            })
            ->where('e.is_people_manager', true)
            ->where('e.employment_status', 'active')
            ->select(
                'e.employee_id',
                'e.first_name',
                'e.last_name',
                'e.position_title',
                'e.employment_status',
                'e.is_people_manager',
                'd.name as department_name',
                'u.role as hims_role'
            );

        if ($excludeEmployeeId) {
            $query->where('e.employee_id', '!=', $excludeEmployeeId);
        }

        return $query->orderBy('e.first_name')
            ->orderBy('e.last_name')
            ->get()
            ->unique('employee_id')
            ->values()
            ->map(fn ($manager) => $this->decorateManager($manager));
    }

    /**
     * Load an employee with the account state needed to explain manager setup.
     */
    public function managerDetails(?string $employeeId): ?object
    {
        if (! $employeeId) {
            return null;
        }

        $manager = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employee_id', $employeeId)
            ->select(
                'e.employee_id',
                'e.first_name',
                'e.last_name',
                'e.position_title',
                'e.employment_status',
                'e.is_people_manager',
                'd.name as department_name'
            )
            ->first();

        if (! $manager) {
            return null;
        }

        $accountRoles = DB::table('users')
            ->where('employee_id', $employeeId)
            ->pluck('role');

        $manager->has_hims_account = $accountRoles->isNotEmpty();
        $manager->hims_role = $accountRoles->first(
            fn ($role) => in_array($role, self::REVIEW_ACCESS_ROLES, true)
        ) ?? $accountRoles->first();
        $manager->has_review_access = $accountRoles->contains(
            fn ($role) => in_array($role, self::REVIEW_ACCESS_ROLES, true)
        );

        return $this->decorateManager($manager);
    }

    /**
     * Return a user-facing assignment error, or null when the choice is valid.
     */
    public function assignmentError(
        string $employeeId,
        ?string $supervisorId,
        bool $requireEligibleManager,
        ?array $reportingGraph = null
    ): ?string {
        if (! $supervisorId) {
            return null;
        }

        if ($employeeId === $supervisorId) {
            return 'An employee cannot report to themselves.';
        }

        $manager = $this->managerDetails($supervisorId);

        if (! $manager) {
            return 'The selected manager no longer exists.';
        }

        if ($requireEligibleManager) {
            if (! $manager->is_people_manager) {
                return 'The selected employee is not marked as a People Manager.';
            }

            if ($manager->employment_status !== 'active') {
                return 'Only active People Managers can be selected for a new reporting assignment.';
            }

            if (! $manager->has_review_access) {
                return 'The selected People Manager needs Supervisor, HR Manager, or Admin access before they can be assigned.';
            }
        }

        if ($this->wouldCreateLoop($employeeId, $supervisorId, $reportingGraph)) {
            return 'That reporting relationship would create a reporting loop.';
        }

        return null;
    }

    /**
     * Lock and return the whole reporting graph for an atomic assignment check.
     */
    public function reportingGraph(bool $forUpdate = false): array
    {
        $query = DB::table('employees')
            ->select('employee_id', 'supervisor_id')
            ->orderBy('employee_id');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->mapWithKeys(fn ($employee) => [$employee->employee_id => $employee->supervisor_id])
            ->all();
    }

    public function wouldCreateLoop(
        string $employeeId,
        string $supervisorId,
        ?array $reportingGraph = null
    ): bool {
        $reportingGraph ??= $this->reportingGraph();
        $seen = [];
        $cursor = $supervisorId;

        while ($cursor) {
            if ($cursor === $employeeId || isset($seen[$cursor])) {
                return true;
            }

            $seen[$cursor] = true;
            $cursor = $reportingGraph[$cursor] ?? null;
        }

        return false;
    }

    public function directReports(string $managerId, bool $activeOnly = false): Collection
    {
        $query = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.supervisor_id', $managerId)
            ->select(
                'e.employee_id',
                'e.first_name',
                'e.last_name',
                'e.position_title',
                'e.employment_status',
                'd.name as department_name'
            );

        if ($activeOnly) {
            $query->where('e.employment_status', 'active');
        }

        return $query->orderBy('e.first_name')->orderBy('e.last_name')->get();
    }

    /**
     * The mismatch lists HR needs to repair manager setup.
     */
    public function setupReport(): array
    {
        $peopleManagersWithoutAccounts = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.is_people_manager', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users as u')
                    ->whereColumn('u.employee_id', 'e.employee_id');
            })
            ->select('e.*', 'd.name as department_name')
            ->orderBy('e.first_name')
            ->get();

        $peopleManagersWithStaffAccess = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('users as u', 'u.employee_id', '=', 'e.employee_id')
            ->where('e.is_people_manager', true)
            ->where('u.role', 'staff')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users as review_account')
                    ->whereColumn('review_account.employee_id', 'e.employee_id')
                    ->whereIn('review_account.role', self::REVIEW_ACCESS_ROLES);
            })
            ->select('e.*', 'd.name as department_name', 'u.role as hims_role')
            ->orderBy('e.first_name')
            ->get()
            ->unique('employee_id')
            ->values();

        $supervisorAccountsNotManagers = DB::table('users as u')
            ->join('employees as e', 'u.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('u.role', 'supervisor')
            ->where('e.is_people_manager', false)
            ->select('e.*', 'd.name as department_name', 'u.role as hims_role')
            ->orderBy('e.first_name')
            ->get();

        $inactiveManagersWithActiveReports = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.is_people_manager', true)
            ->where('e.employment_status', '!=', 'active')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('employees as report')
                    ->whereColumn('report.supervisor_id', 'e.employee_id')
                    ->where('report.employment_status', 'active');
            })
            ->select('e.*', 'd.name as department_name')
            ->orderBy('e.first_name')
            ->get()
            ->map(function ($manager) {
                $manager->active_direct_reports = $this->directReports($manager->employee_id, true);

                return $manager;
            });

        $employeesWithoutManagers = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employment_status', 'active')
            ->whereNull('e.supervisor_id')
            ->select('e.*', 'd.name as department_name')
            ->orderBy('e.first_name')
            ->get();

        return compact(
            'peopleManagersWithoutAccounts',
            'peopleManagersWithStaffAccess',
            'supervisorAccountsNotManagers',
            'inactiveManagersWithActiveReports',
            'employeesWithoutManagers'
        );
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'hr_manager' => 'HR Manager',
            'supervisor' => 'Supervisor',
            'staff' => 'Staff',
            default => 'No HIMS account',
        };
    }

    private function decorateManager(object $manager): object
    {
        $manager->has_hims_account ??= isset($manager->hims_role);
        $manager->has_review_access ??= isset($manager->hims_role)
            && in_array($manager->hims_role, self::REVIEW_ACCESS_ROLES, true);
        $manager->setup_complete = (bool) $manager->is_people_manager
            && $manager->employment_status === 'active'
            && $manager->has_review_access;
        $manager->access_label = self::roleLabel($manager->hims_role ?? null);

        return $manager;
    }
}
