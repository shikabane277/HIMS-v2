<?php

namespace App\Http\Controllers;

use App\Support\CredentialStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Reports Overview Dashboard
     */
    public function index()
    {
        $cyclesCount = DB::table('review_cycles')->count();
        $reviewsCount = DB::table('performance_reviews')->where('status', 'finished')->count();
        $credentialsCount = DB::table('employee_credentials')->count();
        $activeEmployees = DB::table('employees')->where('employment_status', 'active')->count();

        return view('reports.index', compact('cyclesCount', 'reviewsCount', 'credentialsCount', 'activeEmployees'));
    }

    /**
     * Performance Evaluation Report (PB-18, PB-19)
     */
    public function performanceReport(Request $request)
    {
        $cycleId = $request->query('cycle_id');
        $cycles = DB::table('review_cycles')->orderByDesc('start_date')->get();

        $selectedCycle = $cycleId
            ? $cycles->firstWhere('cycle_id', $cycleId)
            : $cycles->first();

        $query = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as rev', 'pr.reviewer_id', '=', 'rev.employee_id')
            ->select(
                'pr.review_id', 'pr.status', 'pr.overall_score', 'pr.submitted_at',
                'e.first_name', 'e.last_name', 'e.employee_code',
                'd.name as department_name', 'rc.cycle_name',
                DB::raw("CONCAT(COALESCE(rev.first_name,''),' ',COALESCE(rev.last_name,'')) as reviewer_name")
            );

        if ($selectedCycle) {
            $query->where('pr.cycle_id', $selectedCycle->cycle_id);
        }

        $reviews = $query->orderByDesc('pr.overall_score')->get();

        // Rating distribution: 1.0-1.99, 2.0-2.99, 3.0-3.99, 4.0-4.99, 5.0
        $distribution = [
            '1.0 - 1.99' => $reviews->where('overall_score', '>=', 1.0)->where('overall_score', '<', 2.0)->count(),
            '2.0 - 2.99' => $reviews->where('overall_score', '>=', 2.0)->where('overall_score', '<', 3.0)->count(),
            '3.0 - 3.99' => $reviews->where('overall_score', '>=', 3.0)->where('overall_score', '<', 4.0)->count(),
            '4.0 - 4.99' => $reviews->where('overall_score', '>=', 4.0)->where('overall_score', '<', 5.0)->count(),
            '5.0' => $reviews->where('overall_score', '>=', 5.0)->count(),
        ];

        $avgScore = $reviews->whereNotNull('overall_score')->avg('overall_score') ?? 0;

        return view('reports.performance', compact('cycles', 'selectedCycle', 'reviews', 'distribution', 'avgScore'));
    }

    /**
     * Export Performance Report to CSV
     */
    public function exportPerformanceCsv(Request $request)
    {
        $cycleId = $request->query('cycle_id');
        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as rev', 'pr.reviewer_id', '=', 'rev.employee_id')
            ->when($cycleId, fn ($q) => $q->where('pr.cycle_id', $cycleId))
            ->select(
                'e.employee_code', 'e.first_name', 'e.last_name',
                'd.name as department_name', 'rc.cycle_name',
                'pr.status', 'pr.overall_score',
                DB::raw("CONCAT(COALESCE(rev.first_name,''),' ',COALESCE(rev.last_name,'')) as reviewer_name"),
                'pr.submitted_at'
            )
            ->orderBy('d.name')
            ->orderBy('e.last_name')
            ->get();

        $filename = 'performance-report-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($reviews) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Employee Code', 'Employee Name', 'Department', 'Review Cycle', 'Reviewer', 'Status', 'Overall Rating (1-5)', 'Submitted Date']);

            foreach ($reviews as $row) {
                fputcsv($handle, [
                    $row->employee_code,
                    "{$row->first_name} {$row->last_name}",
                    $row->department_name,
                    $row->cycle_name,
                    $row->reviewer_name ?: 'None',
                    ucfirst($row->status),
                    $row->overall_score ? number_format($row->overall_score, 2) : 'Unscored',
                    $row->submitted_at ? Carbon::parse($row->submitted_at)->format('Y-m-d') : 'Pending',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Compliance & Credential Expiry Report
     */
    public function complianceReport()
    {
        $credentials = DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->select('ec.*', 'e.first_name', 'e.last_name', 'e.employee_code', 'd.name as department_name')
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status_label', CredentialStatus::caseBindings())
            ->orderBy('ec.expiry_date')
            ->get();

        $statusCounts = [
            'valid' => $credentials->where('status_label', 'valid')->count(),
            'expiring_soon' => $credentials->where('status_label', 'expiring_soon')->count(),
            'critical' => $credentials->where('status_label', 'critical')->count(),
            'expired' => $credentials->where('status_label', 'expired')->count(),
        ];

        return view('reports.compliance', compact('credentials', 'statusCounts'));
    }

    /**
     * Export Compliance Report to CSV
     */
    public function exportComplianceCsv()
    {
        $credentials = DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->select('ec.*', 'e.first_name', 'e.last_name', 'e.employee_code', 'd.name as department_name')
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status_label', CredentialStatus::caseBindings())
            ->orderBy('ec.expiry_date')
            ->get();

        $filename = 'credential-compliance-report-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($credentials) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Employee Code', 'Employee Name', 'Department', 'Credential Type', 'Issuing Body', 'Expiry Date', 'Status', 'Verified']);

            foreach ($credentials as $row) {
                fputcsv($handle, [
                    $row->employee_code,
                    "{$row->first_name} {$row->last_name}",
                    $row->department_name,
                    $row->credential_type,
                    $row->issuing_body,
                    $row->expiry_date,
                    strtoupper(str_replace('_', ' ', $row->status_label)),
                    $row->verified_at ? 'Yes' : 'Pending',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
