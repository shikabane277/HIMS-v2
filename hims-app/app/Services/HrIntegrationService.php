<?php

namespace App\Services;

use App\Support\AuditTrail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service to synchronize clinical credentials and competency assessments from
 * external HR1 (Core HR / Credentials) and HR2 (Competencies & Talent) APIs.
 */
class HrIntegrationService
{
    /**
     * Retrieve configuration for a specific external HR system.
     * Checks database table `system_integrations` first, then falls back to config/services.php (.env).
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(string $systemCode): ?array
    {
        $systemCode = strtolower(trim($systemCode));
        $record = DB::table('system_integrations')->where('system_code', $systemCode)->first();

        $envBaseUrl = (string) config("services.{$systemCode}.base_url", '');
        $envApiKey = (string) config("services.{$systemCode}.api_key", '');
        $envTimeout = (int) config("services.{$systemCode}.timeout", 30);

        if (! $record) {
            return [
                'integration_id' => null,
                'system_code' => $systemCode,
                'system_name' => strtoupper($systemCode),
                'description' => null,
                'base_url' => $envBaseUrl,
                'api_key' => $envApiKey,
                'timeout' => $envTimeout ?: 30,
                'is_active' => true,
                'last_synced_at' => null,
                'last_sync_status' => 'idle',
                'last_sync_message' => null,
                'synced_records_count' => 0,
            ];
        }

        return [
            'integration_id' => $record->integration_id,
            'system_code' => $record->system_code,
            'system_name' => $record->system_name,
            'description' => $record->description,
            'base_url' => ! empty($record->base_url) ? $record->base_url : $envBaseUrl,
            'api_key' => ! empty($record->api_key) ? $record->api_key : $envApiKey,
            'timeout' => (int) ($record->timeout ?? $envTimeout ?: 30),
            'is_active' => (bool) ($record->is_active ?? true),
            'last_synced_at' => $record->last_synced_at,
            'last_sync_status' => $record->last_sync_status ?? 'idle',
            'last_sync_message' => $record->last_sync_message,
            'synced_records_count' => (int) ($record->synced_records_count ?? 0),
        ];
    }

    /**
     * Update integration configuration in the database.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConfig(string $systemCode, array $data): bool
    {
        $systemCode = strtolower(trim($systemCode));
        $updates = [
            'updated_at' => now(),
        ];

        if (array_key_exists('base_url', $data)) {
            $updates['base_url'] = ! empty($data['base_url']) ? trim($data['base_url']) : null;
        }

        if (array_key_exists('api_key', $data)) {
            $updates['api_key'] = ! empty($data['api_key']) ? trim($data['api_key']) : null;
        }

        if (array_key_exists('timeout', $data)) {
            $updates['timeout'] = max(5, (int) $data['timeout']);
        }

        if (array_key_exists('is_active', $data)) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        $exists = DB::table('system_integrations')->where('system_code', $systemCode)->exists();
        if ($exists) {
            DB::table('system_integrations')->where('system_code', $systemCode)->update($updates);
        } else {
            DB::table('system_integrations')->insert(array_merge([
                'integration_id' => (string) Str::uuid(),
                'system_code' => $systemCode,
                'system_name' => strtoupper($systemCode),
                'created_at' => now(),
            ], $updates));
        }

        return true;
    }

    /**
     * Dispatch synchronization based on system selector.
     *
     * @return array{success: bool, message: string, hr1?: array, hr2?: array}
     */
    public function sync(string $system = 'all', array $options = []): array
    {
        $system = strtolower(trim($system));

        if ($system === 'hr1') {
            $hr1 = $this->syncHr1($options);

            return [
                'success' => $hr1['success'],
                'message' => $hr1['message'],
                'hr1' => $hr1,
            ];
        }

        if ($system === 'hr2') {
            $hr2 = $this->syncHr2($options);

            return [
                'success' => $hr2['success'],
                'message' => $hr2['message'],
                'hr2' => $hr2,
            ];
        }

        // Default: sync both HR1 and HR2
        $hr1 = $this->syncHr1($options);
        $hr2 = $this->syncHr2($options);

        $overallSuccess = $hr1['success'] || $hr2['success'];
        $message = "HR1: {$hr1['message']} | HR2: {$hr2['message']}";

        return [
            'success' => $overallSuccess,
            'message' => $message,
            'hr1' => $hr1,
            'hr2' => $hr2,
        ];
    }

    /**
     * Synchronize employee credentials from external HR1 API into `employee_credentials`.
     *
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, updated?: int, skipped?: int}
     */
    public function syncHr1(array $options = []): array
    {
        $config = $this->getConfig('hr1');

        if (! $config || ! $config['is_active']) {
            $this->logSyncStatus('hr1', 'idle', 'HR1 integration is disabled.', 0);

            return ['success' => false, 'message' => 'HR1 integration is currently disabled.'];
        }

        // Direct payload provided (for testing or webhook push)
        if (isset($options['payload']) && is_array($options['payload'])) {
            return $this->processHr1CredentialsPayload($options['payload']);
        }

        if (empty($config['base_url'])) {
            $msg = 'HR1 API Base URL is not configured in .env or the database. Set HR1_API_BASE_URL in .env or save API settings.';
            $this->logSyncStatus('hr1', 'failed', $msg, 0);

            return ['success' => false, 'message' => $msg];
        }

        $url = rtrim($config['base_url'], '/').'/credentials';

        try {
            $request = Http::timeout($config['timeout'] ?? 30)->acceptJson();

            if (! empty($config['api_key'])) {
                $request = $request->withHeaders([
                    'Authorization' => 'Bearer '.$config['api_key'],
                    'X-Api-Key' => $config['api_key'],
                ]);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                $status = $response->status();
                $msg = "HR1 API returned error HTTP {$status}: ".Str::limit($response->body(), 120);
                $this->logSyncStatus('hr1', 'failed', $msg, 0);

                return ['success' => false, 'message' => $msg];
            }

            $payload = $response->json();
            $items = is_array($payload) ? (isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload) : [];

            return $this->processHr1CredentialsPayload($items);
        } catch (\Throwable $e) {
            $msg = 'Failed connecting to HR1 API: '.$e->getMessage();
            Log::error('HR1 Sync exception: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->logSyncStatus('hr1', 'failed', $msg, 0);

            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Process list of credential items from HR1 and persist to `employee_credentials`.
     *
     * @param  array<int, mixed>  $items
     * @return array{success: bool, message: string, imported: int, updated: int, skipped: int}
     */
    public function processHr1CredentialsPayload(array $items): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $employeesByCode = DB::table('employees')->get()->keyBy(fn ($e) => strtoupper((string) $e->employee_code));
        $employeesByEmail = DB::table('employees')->get()->keyBy(fn ($e) => strtolower((string) $e->email));
        $employeesById = DB::table('employees')->get()->keyBy('employee_id');

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skipped++;

                    continue;
                }

                $code = trim((string) ($item['employee_code'] ?? ''));
                $email = trim((string) ($item['email'] ?? ''));
                $empId = trim((string) ($item['employee_id'] ?? ''));

                $emp = null;
                if ($empId && $employeesById->has($empId)) {
                    $emp = $employeesById->get($empId);
                } elseif ($code && $employeesByCode->has(strtoupper($code))) {
                    $emp = $employeesByCode->get(strtoupper($code));
                } elseif ($email && $employeesByEmail->has(strtolower($email))) {
                    $emp = $employeesByEmail->get(strtolower($email));
                }

                if (! $emp) {
                    $skipped++;

                    continue;
                }

                $type = trim((string) ($item['credential_type'] ?? ''));
                if (empty($type)) {
                    $skipped++;

                    continue;
                }

                $rawNumber = trim((string) ($item['credential_number'] ?? ($item['license_number'] ?? '')));
                $encNumber = $rawNumber !== '' ? Crypt::encryptString($rawNumber) : null;
                $issuingBody = trim((string) ($item['issuing_body'] ?? ''));
                $issueDate = ! empty($item['issue_date']) ? date('Y-m-d', strtotime((string) $item['issue_date'])) : null;
                $expiryDate = ! empty($item['expiry_date']) ? date('Y-m-d', strtotime((string) $item['expiry_date'])) : null;

                // Check if existing credential for this employee and type exists
                $existing = DB::table('employee_credentials')
                    ->where('employee_id', $emp->employee_id)
                    ->where('credential_type', $type)
                    ->first();

                if ($existing) {
                    DB::table('employee_credentials')->where('credential_id', $existing->credential_id)->update([
                        'credential_number' => $encNumber ?: $existing->credential_number,
                        'issuing_body' => $issuingBody ?: $existing->issuing_body,
                        'issue_date' => $issueDate ?: $existing->issue_date,
                        'expiry_date' => $expiryDate ?: $existing->expiry_date,
                        'updated_at' => now(),
                    ]);
                    $updated++;
                } else {
                    $newCredId = (string) Str::uuid();
                    DB::table('employee_credentials')->insert([
                        'credential_id' => $newCredId,
                        'employee_id' => $emp->employee_id,
                        'credential_type' => $type,
                        'credential_number' => $encNumber,
                        'issuing_body' => $issuingBody,
                        'issue_date' => $issueDate,
                        'expiry_date' => $expiryDate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    AuditTrail::record('sync_credential_hr1', 'employee_credentials', $newCredId, afterState: [
                        'employee_id' => $emp->employee_id,
                        'credential_type' => $type,
                        'source' => 'HR1_API',
                    ]);
                    $imported++;
                }
            }

            DB::commit();

            $total = $imported + $updated;
            $msg = "HR1 sync completed: {$imported} new credentials added, {$updated} updated, {$skipped} skipped.";
            $this->logSyncStatus('hr1', 'success', $msg, $total);

            return [
                'success' => true,
                'message' => $msg,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $msg = 'Failed processing HR1 credentials: '.$e->getMessage();
            Log::error($msg);
            $this->logSyncStatus('hr1', 'failed', $msg, 0);

            return ['success' => false, 'message' => $msg, 'imported' => 0, 'updated' => 0, 'skipped' => $skipped];
        }
    }

    /**
     * Synchronize competency framework and staff assessments from external HR2 API.
     *
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, competencies_count?: int, assessments_count?: int}
     */
    public function syncHr2(array $options = []): array
    {
        $config = $this->getConfig('hr2');

        if (! $config || ! $config['is_active']) {
            $this->logSyncStatus('hr2', 'idle', 'HR2 integration is disabled.', 0);

            return ['success' => false, 'message' => 'HR2 integration is currently disabled.'];
        }

        // Direct payload provided (for testing or webhook push)
        if (isset($options['payload']) && is_array($options['payload'])) {
            return $this->processHr2Payload($options['payload']);
        }

        if (empty($config['base_url'])) {
            $msg = 'HR2 API Base URL is not configured in .env or the database. Set HR2_API_BASE_URL in .env or save API settings.';
            $this->logSyncStatus('hr2', 'failed', $msg, 0);

            return ['success' => false, 'message' => $msg];
        }

        $baseUrl = rtrim($config['base_url'], '/');

        try {
            $request = Http::timeout($config['timeout'] ?? 30)->acceptJson();

            if (! empty($config['api_key'])) {
                $request = $request->withHeaders([
                    'Authorization' => 'Bearer '.$config['api_key'],
                    'X-Api-Key' => $config['api_key'],
                ]);
            }

            // 1. Fetch competencies framework
            $compResponse = $request->get($baseUrl.'/competencies');
            $competenciesPayload = [];
            if ($compResponse->successful()) {
                $compData = $compResponse->json();
                $competenciesPayload = is_array($compData) ? (isset($compData['data']) && is_array($compData['data']) ? $compData['data'] : $compData) : [];
            }

            // 2. Fetch assessments
            $assessResponse = $request->get($baseUrl.'/assessments');
            $assessmentsPayload = [];
            if ($assessResponse->successful()) {
                $assessData = $assessResponse->json();
                $assessmentsPayload = is_array($assessData) ? (isset($assessData['data']) && is_array($assessData['data']) ? $assessData['data'] : $assessData) : [];
            }

            return $this->processHr2Payload([
                'competencies' => $competenciesPayload,
                'assessments' => $assessmentsPayload,
            ]);
        } catch (\Throwable $e) {
            $msg = 'Failed connecting to HR2 API: '.$e->getMessage();
            Log::error('HR2 Sync exception: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->logSyncStatus('hr2', 'failed', $msg, 0);

            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Process HR2 framework and assessment data and persist to database.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, competencies_count: int, assessments_count: int}
     */
    public function processHr2Payload(array $payload): array
    {
        $competenciesList = $payload['competencies'] ?? ($payload['framework'] ?? []);
        $assessmentsList = $payload['assessments'] ?? [];

        $competencyCount = 0;
        $assessmentCount = 0;

        DB::beginTransaction();
        try {
            // Process Competency Framework (Domains -> Categories -> Competencies)
            if (is_array($competenciesList) && ! empty($competenciesList)) {
                foreach ($competenciesList as $comp) {
                    if (! is_array($comp)) {
                        continue;
                    }

                    $domainName = trim((string) ($comp['domain_name'] ?? 'Clinical Governance'));
                    $categoryName = trim((string) ($comp['category_name'] ?? 'Core Competencies'));
                    $competencyName = trim((string) ($comp['competency_name'] ?? ''));
                    $competencyCode = trim((string) ($comp['competency_code'] ?? ''));

                    if (empty($competencyName)) {
                        continue;
                    }

                    // Find or create domain
                    $domain = DB::table('competency_domains')->where('domain_name', $domainName)->first();
                    if (! $domain) {
                        $domainId = (string) Str::uuid();
                        DB::table('competency_domains')->insert([
                            'domain_id' => $domainId,
                            'domain_name' => $domainName,
                            'description' => $comp['domain_description'] ?? 'Imported from HR2',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $domainId = $domain->domain_id;
                    }

                    // Find or create category
                    $category = DB::table('competency_categories')
                        ->where('domain_id', $domainId)
                        ->where('category_name', $categoryName)
                        ->first();

                    if (! $category) {
                        $categoryId = (string) Str::uuid();
                        DB::table('competency_categories')->insert([
                            'category_id' => $categoryId,
                            'domain_id' => $domainId,
                            'category_name' => $categoryName,
                            'jci_standard_code' => $comp['jci_standard_code'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $categoryId = $category->category_id;
                    }

                    // Find or create/update competency
                    $existingComp = null;
                    if ($competencyCode) {
                        $existingComp = DB::table('competencies')->where('competency_code', $competencyCode)->first();
                    }
                    if (! $existingComp) {
                        $existingComp = DB::table('competencies')->where('competency_name', $competencyName)->first();
                    }

                    $reqProf = isset($comp['required_proficiency']) ? max(1, min(5, (int) $comp['required_proficiency'])) : 3;

                    if ($existingComp) {
                        DB::table('competencies')->where('competency_id', $existingComp->competency_id)->update([
                            'category_id' => $categoryId,
                            'competency_name' => $competencyName,
                            'required_proficiency' => $reqProf,
                            'description' => $comp['description'] ?? $existingComp->description,
                            'is_mandatory' => (bool) ($comp['is_mandatory'] ?? $existingComp->is_mandatory),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('competencies')->insert([
                            'competency_id' => (string) Str::uuid(),
                            'category_id' => $categoryId,
                            'competency_name' => $competencyName,
                            'competency_code' => $competencyCode ?: strtoupper(Str::slug($competencyName)),
                            'description' => $comp['description'] ?? null,
                            'required_proficiency' => $reqProf,
                            'is_mandatory' => (bool) ($comp['is_mandatory'] ?? false),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $competencyCount++;
                }
            }

            // Process Assessments
            if (is_array($assessmentsList) && ! empty($assessmentsList)) {
                $employeesByCode = DB::table('employees')->get()->keyBy(fn ($e) => strtoupper((string) $e->employee_code));
                $employeesByEmail = DB::table('employees')->get()->keyBy(fn ($e) => strtolower((string) $e->email));
                $employeesById = DB::table('employees')->get()->keyBy('employee_id');

                $competenciesByCode = DB::table('competencies')->get()->keyBy(fn ($c) => strtoupper((string) $c->competency_code));
                $competenciesByName = DB::table('competencies')->get()->keyBy(fn ($c) => strtolower((string) $c->competency_name));
                $competenciesById = DB::table('competencies')->get()->keyBy('competency_id');

                // Default assessor: an active admin or supervisor employee
                $defaultAssessorId = DB::table('employees')
                    ->join('users', 'employees.employee_id', '=', 'users.employee_id')
                    ->whereIn('users.role', ['admin', 'hr_manager'])
                    ->value('employees.employee_id') ?: DB::table('employees')->value('employee_id');

                foreach ($assessmentsList as $ass) {
                    if (! is_array($ass)) {
                        continue;
                    }

                    // Match employee
                    $code = trim((string) ($ass['employee_code'] ?? ''));
                    $email = trim((string) ($ass['email'] ?? ''));
                    $empId = trim((string) ($ass['employee_id'] ?? ''));

                    $emp = null;
                    if ($empId && $employeesById->has($empId)) {
                        $emp = $employeesById->get($empId);
                    } elseif ($code && $employeesByCode->has(strtoupper($code))) {
                        $emp = $employeesByCode->get(strtoupper($code));
                    } elseif ($email && $employeesByEmail->has(strtolower($email))) {
                        $emp = $employeesByEmail->get(strtolower($email));
                    }

                    if (! $emp) {
                        continue;
                    }

                    // Match competency
                    $cCode = trim((string) ($ass['competency_code'] ?? ''));
                    $cName = trim((string) ($ass['competency_name'] ?? ''));
                    $cId = trim((string) ($ass['competency_id'] ?? ''));

                    $comp = null;
                    if ($cId && $competenciesById->has($cId)) {
                        $comp = $competenciesById->get($cId);
                    } elseif ($cCode && $competenciesByCode->has(strtoupper($cCode))) {
                        $comp = $competenciesByCode->get(strtoupper($cCode));
                    } elseif ($cName && $competenciesByName->has(strtolower($cName))) {
                        $comp = $competenciesByName->get(strtolower($cName));
                    }

                    if (! $comp) {
                        continue;
                    }

                    $currentProf = max(1, min(5, (int) ($ass['current_proficiency'] ?? 3)));

                    // Determine required proficiency from role requirement or competency default
                    $roleReq = $emp->role_id ? DB::table('role_competency_requirements')
                        ->where('role_id', $emp->role_id)
                        ->where('competency_id', $comp->competency_id)
                        ->value('minimum_proficiency') : null;

                    $reqProf = $roleReq ?? $comp->required_proficiency ?? 3;
                    $gap = $currentProf - (int) $reqProf;

                    $method = trim((string) ($ass['assessment_method'] ?? 'supervisor_rating'));
                    $validMethods = ['observation', 'self_assessment', 'supervisor_rating', 'practical_test', 'written_exam'];
                    if (! in_array($method, $validMethods, true)) {
                        $method = 'supervisor_rating';
                    }

                    $assessedDate = ! empty($ass['assessed_date']) ? date('Y-m-d', strtotime((string) $ass['assessed_date'])) : now()->toDateString();
                    $nextDue = ! empty($ass['next_assessment_due']) ? date('Y-m-d', strtotime((string) $ass['next_assessment_due'])) : date('Y-m-d', strtotime('+1 year', strtotime($assessedDate)));

                    $assessedBy = $emp->supervisor_id ?: $defaultAssessorId;

                    // Upsert assessment row
                    $existingAssessment = DB::table('competency_assessments')
                        ->where('employee_id', $emp->employee_id)
                        ->where('competency_id', $comp->competency_id)
                        ->orderByDesc('assessed_date')
                        ->first();

                    if ($existingAssessment) {
                        DB::table('competency_assessments')
                            ->where('assessment_id', $existingAssessment->assessment_id)
                            ->update([
                                'current_proficiency' => $currentProf,
                                'gap' => $gap,
                                'assessment_method' => $method,
                                'assessed_date' => $assessedDate,
                                'next_assessment_due' => $nextDue,
                                'notes' => $ass['notes'] ?? $existingAssessment->notes,
                                'updated_at' => now(),
                            ]);
                    } else {
                        $assessmentId = (string) Str::uuid();
                        DB::table('competency_assessments')->insert([
                            'assessment_id' => $assessmentId,
                            'employee_id' => $emp->employee_id,
                            'competency_id' => $comp->competency_id,
                            'assessed_by' => $assessedBy ?: $emp->employee_id,
                            'assessment_method' => $method,
                            'current_proficiency' => $currentProf,
                            'gap' => $gap,
                            'notes' => $ass['notes'] ?? 'Synchronized via HR2 API',
                            'assessed_date' => $assessedDate,
                            'next_assessment_due' => $nextDue,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        AuditTrail::record('sync_assessment_hr2', 'competency_assessments', $assessmentId, afterState: [
                            'employee_id' => $emp->employee_id,
                            'competency_id' => $comp->competency_id,
                            'current_proficiency' => $currentProf,
                            'gap' => $gap,
                            'source' => 'HR2_API',
                        ]);
                    }

                    $assessmentCount++;
                }
            }

            DB::commit();

            $msg = "HR2 sync completed: {$competencyCount} competencies verified/added, {$assessmentCount} assessments synchronized.";
            $this->logSyncStatus('hr2', 'success', $msg, $assessmentCount);

            return [
                'success' => true,
                'message' => $msg,
                'competencies_count' => $competencyCount,
                'assessments_count' => $assessmentCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $msg = 'Failed processing HR2 payload: '.$e->getMessage();
            Log::error($msg);
            $this->logSyncStatus('hr2', 'failed', $msg, 0);

            return [
                'success' => false,
                'message' => $msg,
                'competencies_count' => 0,
                'assessments_count' => 0,
            ];
        }
    }

    /**
     * Record sync status and count in the `system_integrations` table.
     */
    protected function logSyncStatus(string $systemCode, string $status, string $message, int $recordsCount): void
    {
        $systemCode = strtolower(trim($systemCode));
        $now = now();

        $exists = DB::table('system_integrations')->where('system_code', $systemCode)->exists();
        if ($exists) {
            DB::table('system_integrations')->where('system_code', $systemCode)->update([
                'last_synced_at' => $now,
                'last_sync_status' => $status,
                'last_sync_message' => $message,
                'synced_records_count' => $recordsCount,
                'updated_at' => $now,
            ]);
        }
    }
}
