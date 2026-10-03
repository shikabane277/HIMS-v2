<?php

namespace App\Http\Controllers;

use App\Services\HrIntegrationService;
use App\Support\CredentialStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompetencyController extends Controller
{
    public function index(Request $request)
    {
        $baseCreds = $this->scopeToVisibleEmployees(
            DB::table('employee_credentials as ec')
                ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id'),
            'e.employee_id'
        );

        $stats = [
            'total_competencies' => DB::table('competencies')->count(),
            'avg_gap' => round(DB::table('competency_assessments')->avg('gap') ?? 0, 1),
            'expiring_soon' => CredentialStatus::whereExpiring(clone $baseCreds, 'ec.expiry_date')->count('ec.credential_id'),
            'expired' => CredentialStatus::whereExpired(clone $baseCreds, 'ec.expiry_date')->count('ec.credential_id'),
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
            ->select('d.domain_id', 'd.domain_name', 'd.description', 'd.is_active',
                DB::raw('COUNT(DISTINCT cc.category_id) as categories_count'),
                DB::raw('COUNT(DISTINCT c.competency_id) as competencies_count'))
            ->groupBy('d.domain_id', 'd.domain_name', 'd.description', 'd.is_active')->get();

        $employees = $this->scopeToVisibleEmployees(DB::table('employees')->orderBy('first_name'), 'employee_id')->get();
        $competencies = DB::table('competencies')->orderBy('competency_name')->get();
        $integrations = DB::table('system_integrations')->get()->keyBy('system_code');

        return view('competency.index', compact('stats', 'departments', 'gap_matrix', 'credential_alerts', 'domains', 'employees', 'competencies', 'filterDepartmentId', 'integrations'));
    }

    public function storeAssessment(Request $request)
    {
        return redirect()->route('competency.index')->with('error', 'Manual assessment input is disabled. Assessments are automatically pulled from HR2.');
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
            $cred->decryption_failed = false;
            if ($cred->credential_number) {
                try {
                    $cred->credential_number = Crypt::decryptString($cred->credential_number);
                } catch (\Throwable $e) {
                    $cred->decryption_failed = true;
                    Log::warning('Credential decryption failed — possibly rotated APP_KEY or plaintext stored', [
                        'credential_id' => $cred->credential_id,
                        'employee_id' => $cred->employee_id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        $baseStatsQuery = $this->scopeToVisibleEmployees(
            DB::table('employee_credentials as ec')
                ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id'),
            'e.employee_id'
        );

        $stats = [
            'total' => (clone $baseStatsQuery)->count('ec.credential_id'),
            'valid' => CredentialStatus::whereActive(clone $baseStatsQuery, 'ec.expiry_date')->count('ec.credential_id'),
            'expiring' => CredentialStatus::whereExpiring(clone $baseStatsQuery, 'ec.expiry_date')->count('ec.credential_id'),
            'expired' => CredentialStatus::whereExpired(clone $baseStatsQuery, 'ec.expiry_date')->count('ec.credential_id'),
        ];

        $employees = $this->scopeToVisibleEmployees(DB::table('employees')->orderBy('first_name'), 'employee_id')->get();
        $hr1Integration = DB::table('system_integrations')->where('system_code', 'hr1')->first();

        return view('competency.credentials.index', compact('credentials', 'stats', 'employees', 'hr1Integration'));
    }

    public function storeCredential(Request $request)
    {
        return redirect()->route('competency.credentials.index')->with('error', 'Manual credential input is disabled. Credentials and licenses are automatically pulled from HR1.');
    }

    public function downloadCredentialsTemplate()
    {
        abort(404, 'CSV import has been removed from the system.');
    }

    public function importCredentialsCsv(Request $request)
    {
        return redirect()->route('competency.credentials.index')->with('error', 'CSV credential import is disabled. Credentials and licenses are automatically pulled from HR1.');
    }

    public function syncHr(Request $request, HrIntegrationService $hrService)
    {
        abort_unless(auth()->user()->can('manage-competency'), 403);

        $system = $request->input('system', 'all');
        $result = $hrService->sync($system);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function updateIntegration(Request $request, $id, HrIntegrationService $hrService)
    {
        abort_unless(auth()->user()->can('manage-competency'), 403);

        $request->validate([
            'base_url' => 'nullable|string|max:255',
            'api_key' => 'nullable|string',
            'timeout' => 'nullable|integer|min:5|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $integration = DB::table('system_integrations')->where('integration_id', $id)->first();
        abort_if(! $integration, 404);

        $hrService->updateConfig($integration->system_code, [
            'base_url' => $request->base_url,
            'api_key' => $request->api_key,
            'timeout' => $request->timeout ?: 30,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', "{$integration->system_name} API settings updated successfully.");
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

    public function updateDomain(Request $request, $id)
    {
        $domain = DB::table('competency_domains')->where('domain_id', $id)->first();
        abort_if(! $domain, 404);

        $request->validate([
            'domain_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $updates = [
            'domain_name' => $request->domain_name,
            'description' => $request->description,
            'updated_at' => now(),
        ];

        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }

        DB::table('competency_domains')->where('domain_id', $id)->update($updates);

        return redirect()->back()->with('success', 'Competency domain updated.');
    }

    public function toggleDomainStatus($id)
    {
        $domain = DB::table('competency_domains')->where('domain_id', $id)->first();
        abort_if(! $domain, 404);

        $newStatus = ! ($domain->is_active ?? true);
        DB::table('competency_domains')->where('domain_id', $id)->update([
            'is_active' => $newStatus,
            'updated_at' => now(),
        ]);

        $statusText = $newStatus ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Competency domain {$statusText} successfully.");
    }

    public function destroyDomain($id)
    {
        $domain = DB::table('competency_domains')->where('domain_id', $id)->first();
        abort_if(! $domain, 404);

        $hasCategories = DB::table('competency_categories')->where('domain_id', $id)->exists();
        if ($hasCategories) {
            return redirect()->back()->with('error', 'Cannot delete domain that contains competency categories. Please remove or reassign categories first.');
        }

        DB::table('competency_domains')->where('domain_id', $id)->delete();

        return redirect()->route('competency.index')->with('success', 'Competency domain deleted.');
    }

    public function roleRequirementsIndex(Request $request)
    {
        $roles = DB::table('roles')->orderBy('role_name')->get();
        $competencies = DB::table('competencies')->orderBy('competency_name')->get();

        $selectedRole = $request->query('role_id') ?: ($roles->first()->role_id ?? null);

        $requirements = DB::table('role_competency_requirements as rcr')
            ->join('competencies as c', 'rcr.competency_id', '=', 'c.competency_id')
            ->join('roles as r', 'rcr.role_id', '=', 'r.role_id')
            ->when($selectedRole, fn ($q, $id) => $q->where('rcr.role_id', $id))
            ->select('rcr.*', 'c.competency_name', 'c.competency_code', 'c.required_proficiency as global_required', 'r.role_name')
            ->orderBy('c.competency_name')
            ->get();

        return view('competency.role-requirements.index', compact('roles', 'competencies', 'selectedRole', 'requirements'));
    }

    public function storeRoleRequirement(Request $request)
    {
        $request->validate([
            'role_id' => 'required|string|exists:roles,role_id',
            'competency_id' => 'required|string|exists:competencies,competency_id',
            'minimum_proficiency' => 'required|integer|min:1|max:5',
            'is_critical' => 'nullable|boolean',
        ]);

        $exists = DB::table('role_competency_requirements')
            ->where('role_id', $request->role_id)
            ->where('competency_id', $request->competency_id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'competency_id' => 'This competency is already mapped to the selected role.',
            ]);
        }

        DB::table('role_competency_requirements')->insert([
            'id' => (string) Str::uuid(),
            'role_id' => $request->role_id,
            'competency_id' => $request->competency_id,
            'minimum_proficiency' => $request->minimum_proficiency,
            'is_critical' => $request->boolean('is_critical'),
        ]);

        return back()->with('success', 'Role competency requirement saved.');
    }

    public function updateRoleRequirement(Request $request, $id)
    {
        $req = DB::table('role_competency_requirements')->where('id', $id)->first();
        abort_if(! $req, 404);

        $request->validate([
            'minimum_proficiency' => 'required|integer|min:1|max:5',
            'is_critical' => 'nullable|boolean',
        ]);

        DB::table('role_competency_requirements')->where('id', $id)->update([
            'minimum_proficiency' => $request->minimum_proficiency,
            'is_critical' => $request->boolean('is_critical'),
        ]);

        return back()->with('success', 'Role competency requirement updated.');
    }

    public function destroyRoleRequirement($id)
    {
        $req = DB::table('role_competency_requirements')->where('id', $id)->first();
        abort_if(! $req, 404);

        DB::table('role_competency_requirements')->where('id', $id)->delete();

        return back()->with('success', 'Role competency requirement removed.');
    }
}
