<?php

namespace App\Http\Controllers;

use App\Services\CompetencyGapAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Objective 6 — AI-Assisted Competency Gap Analysis.
 *
 * Reads performance results, competency assessments against job requirements,
 * and training received, then reports the missing skills and how to close them.
 */
class GapAnalysisController extends Controller
{
    public function __construct(private CompetencyGapAnalysisService $analysis) {}

    /**
     * Picker + department-level rollup.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Supervisors are pinned to their own department; HR/admin may choose.
        $departmentId = $user->seesWholeOrganisation()
            ? $request->query('department')
            : $user->departmentId();

        $employees = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employment_status', 'active')
            ->select('e.employee_id', 'e.first_name', 'e.last_name', 'e.position_title', 'd.name as department_name');

        $employees = $this->scopeToVisibleEmployees($employees)
            ->orderBy('e.first_name')->get();

        // The department rollup is deterministic-only here; the AI narrative is
        // fetched on demand so the landing page stays fast.
        $department = $this->analysis->analyseDepartment($departmentId, withAi: false);

        return view('competency.gap-analysis.index', [
            'employees'    => $employees,
            'departments'  => $user->seesWholeOrganisation()
                                ? DB::table('departments')->orderBy('name')->get()
                                : collect(),
            'departmentId' => $departmentId,
            'department'   => $department,
        ]);
    }

    /**
     * Full per-employee analysis, including the AI narrative.
     */
    public function employee(Request $request, string $employeeId)
    {
        $this->authorizeEmployeeAccess($employeeId);

        $withAi = ! $request->boolean('no_ai');
        $result = $this->analysis->analyseEmployee($employeeId, $withAi);

        if (isset($result['error'])) {
            abort(404);
        }

        return view('competency.gap-analysis.employee', ['analysis' => $result]);
    }

    /**
     * Department rollup with the AI narrative attached.
     */
    public function department(Request $request)
    {
        $user = auth()->user();

        $departmentId = $user->seesWholeOrganisation()
            ? $request->query('department')
            : $user->departmentId();

        abort_if(! $user->seesWholeOrganisation() && ! $departmentId, 403,
            'Your account is not linked to a department.');

        return view('competency.gap-analysis.department', [
            'analysis' => $this->analysis->analyseDepartment($departmentId, withAi: true),
        ]);
    }

    /**
     * JSON endpoint so the page can load the AI section asynchronously.
     */
    public function employeeJson(string $employeeId)
    {
        $this->authorizeEmployeeAccess($employeeId);

        $result = $this->analysis->analyseEmployee($employeeId, withAi: true);

        if (isset($result['error'])) {
            return response()->json(['error' => 'Employee not found.'], 404);
        }

        return response()->json([
            'summary' => $result['summary'],
            'ai'      => $result['ai'],
        ]);
    }
}
