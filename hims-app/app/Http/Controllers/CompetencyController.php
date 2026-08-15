<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use App\Support\CredentialStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompetencyController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_competencies' => DB::table('competencies')->count(),
            'avg_gap' => round(DB::table('competency_assessments')->avg('gap') ?? 0, 1),
            'expiring_soon' => CredentialStatus::whereExpiring(DB::table('employee_credentials'))->count(),
            'expired' => CredentialStatus::whereExpired(DB::table('employee_credentials'))->count(),
        ];

        $departments = DB::table('departments')->orderBy('name')->get();

        $filterDepartmentId = $request->query('department_id') ?: null;

        $gap_matrix = DB::table('competencies as c')
            ->join('competency_assessments as ca', 'c.competency_id', '=', 'ca.competency_id')
            ->when($filterDepartmentId, fn ($q, $id) => $q
                ->join('employees as e', 'ca.employee_id', '=', 'e.employee_id')
                ->where('e.department_id', $id))
            ->select('c.competency_name', 'c.competency_code', 'c.required_proficiency',
                DB::raw('AVG(ca.current_proficiency) as avg_score'),
                DB::raw('AVG(ca.gap) as gap'))
            ->groupBy('c.competency_id', 'c.competency_name', 'c.competency_code', 'c.required_proficiency')
            ->orderBy('gap')->limit(20)->get();

        $credentialAlertsQuery = DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->where(function ($q) {
                CredentialStatus::whereExpiring($q, 'ec.expiry_date');
            })
            ->orWhere(function ($q) {
                CredentialStatus::whereExpired($q, 'ec.expiry_date');
            })
            ->select('ec.credential_id', 'ec.credential_type', 'ec.expiry_date',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"))
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status', CredentialStatus::caseBindings())
            ->orderBy('ec.expiry_date');

        $credential_alerts = $this->scopeToVisibleEmployees($credentialAlertsQuery, 'e.employee_id')->limit(10)->get();

        $domains = DB::table('competency_domains as d')
            ->leftJoin('competency_categories as cc', 'd.domain_id', '=', 'cc.domain_id')
            ->leftJoin('competencies as c', 'cc.category_id', '=', 'c.category_id')
            ->select('d.domain_id', 'd.domain_name',
                DB::raw('COUNT(DISTINCT cc.category_id) as categories_count'),
                DB::raw('COUNT(DISTINCT c.competency_id) as competencies_count'))
            ->groupBy('d.domain_id', 'd.domain_name')->get();

        $employees = $this->scopeToVisibleEmployees(DB::table('employees')->orderBy('first_name'), 'employee_id')->get();
        $competencies = DB::table('competencies')->orderBy('competency_name')->get();

        return view('competency.index', compact('stats', 'departments', 'gap_matrix', 'credential_alerts', 'domains', 'employees', 'competencies', 'filterDepartmentId'));
    }

    public function storeAssessment(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'competency_id' => 'required|string|exists:competencies,competency_id',
            'current_proficiency' => 'required|integer|min:1|max:5',
            'assessment_method' => 'nullable|in:observation,self_assessment,supervisor_rating,practical_test,written_exam',
            'assessed_date' => 'nullable|date',
            'next_assessment_due' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $this->authorizeEmployeeAccess($request->employee_id);

        $assessedBy = $this->currentEmployeeId() ?? $request->employee_id;
        $assessmentId = (string) Str::uuid();

        DB::table('competency_assessments')->insert([
            'assessment_id' => $assessmentId,
            'employee_id' => $request->employee_id,
            'competency_id' => $request->competency_id,
            'assessed_by' => $assessedBy,
            'assessment_method' => $request->assessment_method ?: 'self_assessment',
            'current_proficiency' => $request->current_proficiency,
            'notes' => $request->notes,
            'assessed_date' => $request->assessed_date ?: now()->toDateString(),
            'next_assessment_due' => $request->next_assessment_due ?: now()->addYear()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AuditTrail::record('store_assessment', 'competency_assessments', $assessmentId, afterState: [
            'employee_id' => $request->employee_id,
            'competency_id' => $request->competency_id,
            'current_proficiency' => $request->current_proficiency,
            'assessment_method' => $request->assessment_method ?: 'self_assessment',
        ]);

        return redirect()->route('competency.index')->with('success', 'Assessment recorded.');
    }

    public function credentialsIndex(Request $request)
    {
        $query = DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->select('ec.*', DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"))
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status', CredentialStatus::caseBindings())
            ->when($request->query('focus'), fn ($q, $id) => $q->where('ec.credential_id', $id))
            ->orderBy('ec.expiry_date');

        $credentials = $this->scopeToVisibleEmployees($query, 'e.employee_id')->paginate(20);

        foreach ($credentials as $cred) {
            if ($cred->credential_number) {
                try {
                    $cred->credential_number = Crypt::decryptString($cred->credential_number);
                } catch (\Throwable $e) {
                    // Fallback to plaintext
                }
            }
        }

        $stats = [
            'total' => DB::table('employee_credentials')->count(),
            'valid' => CredentialStatus::whereActive(DB::table('employee_credentials'))->count(),
            'expiring' => CredentialStatus::whereExpiring(DB::table('employee_credentials'))->count(),
            'expired' => CredentialStatus::whereExpired(DB::table('employee_credentials'))->count(),
        ];

        $employees = $this->scopeToVisibleEmployees(DB::table('employees')->orderBy('first_name'), 'employee_id')->get();

        return view('competency.credentials.index', compact('credentials', 'stats', 'employees'));
    }

    public function storeCredential(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'credential_type' => 'required|string|max:50',
            'credential_number' => 'nullable|string|max:100',
            'issuing_body' => 'nullable|string|max:150',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
        ]);

        $this->authorizeEmployeeAccess($request->employee_id);

        $credentialId = (string) Str::uuid();
        $encNumber = $request->credential_number ? Crypt::encryptString($request->credential_number) : null;

        DB::table('employee_credentials')->insert([
            'credential_id' => $credentialId,
            'employee_id' => $request->employee_id,
            'credential_type' => $request->credential_type,
            'credential_number' => $encNumber,
            'issuing_body' => $request->issuing_body,
            'issue_date' => $request->issue_date ?: null,
            'expiry_date' => $request->expiry_date ?: null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AuditTrail::record('store_credential', 'employee_credentials', $credentialId, afterState: [
            'employee_id' => $request->employee_id,
            'credential_type' => $request->credential_type,
            'issuing_body' => $request->issuing_body,
            'expiry_date' => $request->expiry_date,
        ]);

        return redirect()->route('competency.credentials.index')->with('success', 'Credential added.');
    }

    /**
     * Create a domain. There is no matching create() — the form is a modal on
     * the Competency index, so this is posted to directly.
     */
    public function storeDomain(Request $request)
    {
        $request->validate(['domain_name' => 'required|string|max:100|unique:competency_domains,domain_name']);
        DB::table('competency_domains')->insert([
            'domain_id' => Str::uuid(),
            'domain_name' => $request->domain_name,
            'description' => $request->description,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('competency.index')->with('success', 'Domain created.');
    }

    public function showDomain($id)
    {
        $domain = DB::table('competency_domains')->where('domain_id', $id)->first();
        abort_if(! $domain, 404);

        // Categories carry the competency count so the page shows the framework,
        // not just a list of empty headings.
        $categories = DB::table('competency_categories as cc')
            ->leftJoin('competencies as c', 'cc.category_id', '=', 'c.category_id')
            ->where('cc.domain_id', $id)
            ->select('cc.*', DB::raw('COUNT(c.competency_id) as competency_count'))
            ->groupBy('cc.category_id')
            ->orderBy('cc.category_name')
            ->get();

        $competencies = DB::table('competencies as c')
            ->join('competency_categories as cc', 'c.category_id', '=', 'cc.category_id')
            ->where('cc.domain_id', $id)
            ->select('c.competency_id', 'c.competency_name', 'c.competency_code', 'c.description',
                'c.required_proficiency', 'c.is_mandatory', 'c.category_id', 'cc.category_name')
            ->orderBy('cc.category_name')->orderBy('c.competency_name')
            ->get();

        return view('competency.domains.show', compact('domain', 'categories', 'competencies'));
    }
}
