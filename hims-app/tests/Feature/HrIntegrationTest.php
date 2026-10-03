<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HrIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $deptId;

    private string $roleId;

    private string $adminEmpId;

    private User $adminUser;

    private string $staffEmpId;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'concat',
                fn (...$parts) => implode('', array_map(fn ($part) => $part ?? '', $parts))
            );
        }

        $this->deptId = $this->createDepartment('Clinical Governance');
        $this->roleId = $this->createRole('Clinical Specialist');

        $this->adminEmpId = $this->createEmployee('Admin', 'Officer', 'admin@hospital.test');
        $this->adminUser = $this->createUser('admin', $this->adminEmpId);

        $this->staffEmpId = $this->createEmployee('Jane', 'Nurse', 'nurse.jane@hospital.test', [
            'employee_code' => 'EMP-1001',
            'role_id' => $this->roleId,
            'supervisor_id' => $this->adminEmpId,
        ]);
        $this->staffUser = $this->createUser('staff', $this->staffEmpId);
    }

    private function createDepartment(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createRole(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => $name,
            'role_slug' => 'role-'.Str::lower(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createEmployee(string $firstName, string $lastName, string $email, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'employee_code' => 'EMP-'.strtoupper(Str::random(4)),
            'department_id' => $this->deptId,
            'role_id' => $this->roleId,
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $id;
    }

    private function createUser(string $role, string $employeeId): User
    {
        $emp = DB::table('employees')->where('employee_id', $employeeId)->first();

        return User::create([
            'name' => $emp->first_name.' '.$emp->last_name,
            'email' => $emp->email,
            'password' => bcrypt('password'),
            'role' => $role,
            'employee_id' => $employeeId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_manual_assessment_and_credential_creation_are_disabled(): void
    {
        // Manual assessment store should reject with error
        $response = $this->actingAs($this->adminUser)
            ->post(route('competency.assessments.store'), [
                'employee_id' => $this->staffEmpId,
                'competency_id' => (string) Str::uuid(),
                'current_proficiency' => 4,
            ]);

        $response->assertRedirect(route('competency.index'));
        $response->assertSessionHas('error');

        // Manual credential store should reject with error
        $response2 = $this->actingAs($this->adminUser)
            ->post(route('competency.credentials.store'), [
                'employee_id' => $this->staffEmpId,
                'credential_type' => 'PRC License',
            ]);

        $response2->assertRedirect(route('competency.credentials.index'));
        $response2->assertSessionHas('error');

        // Manual CSV import should reject with error
        $response3 = $this->actingAs($this->adminUser)
            ->post(route('competency.credentials.import'), []);

        $response3->assertRedirect(route('competency.credentials.index'));
        $response3->assertSessionHas('error');
    }

    public function test_hr1_credentials_sync_via_mocked_api(): void
    {
        Http::fake([
            'https://hr1.hospital.internal/api/v1/credentials' => Http::response([
                'data' => [
                    [
                        'employee_code' => 'EMP-1001',
                        'credential_type' => 'PRC Registered Nurse License',
                        'credential_number' => 'RN-987654',
                        'issuing_body' => 'Professional Regulation Commission',
                        'issue_date' => '2024-05-01',
                        'expiry_date' => '2027-05-01',
                    ],
                    [
                        'email' => 'nurse.jane@hospital.test',
                        'credential_type' => 'BLS Healthcare Provider',
                        'credential_number' => 'BLS-2024-44',
                        'issuing_body' => 'American Heart Association',
                        'issue_date' => '2025-01-10',
                        'expiry_date' => '2027-01-10',
                    ],
                ],
            ], 200),
        ]);

        // Configure HR1 in database
        DB::table('system_integrations')->where('system_code', 'hr1')->update([
            'base_url' => 'https://hr1.hospital.internal/api/v1',
            'api_key' => 'secret-hr1-token',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('competency.sync'), ['system' => 'hr1']);

        $response->assertSessionHas('success');

        // Check credentials in database
        $credentials = DB::table('employee_credentials')
            ->where('employee_id', $this->staffEmpId)
            ->get();

        $this->assertCount(2, $credentials);

        $prc = $credentials->firstWhere('credential_type', 'PRC Registered Nurse License');
        $this->assertNotNull($prc);
        $this->assertEquals('RN-987654', Crypt::decryptString($prc->credential_number));
        $this->assertEquals('Professional Regulation Commission', $prc->issuing_body);

        $bls = $credentials->firstWhere('credential_type', 'BLS Healthcare Provider');
        $this->assertNotNull($bls);
        $this->assertEquals('BLS-2024-44', Crypt::decryptString($bls->credential_number));

        // Check system_integrations record updated
        $integration = DB::table('system_integrations')->where('system_code', 'hr1')->first();
        $this->assertEquals('success', $integration->last_sync_status);
        $this->assertEquals(2, $integration->synced_records_count);
    }

    public function test_hr2_framework_and_assessments_sync_via_mocked_api(): void
    {
        Http::fake([
            'https://hr2.hospital.internal/api/v1/competencies' => Http::response([
                'data' => [
                    [
                        'domain_name' => 'Clinical Safety',
                        'category_name' => 'Infection Control',
                        'competency_code' => 'COMP-IC-01',
                        'competency_name' => 'Aseptic Technique and Hand Hygiene',
                        'required_proficiency' => 4,
                        'is_mandatory' => true,
                        'description' => 'Maintaining sterile field and compliance with infection control.',
                    ],
                ],
            ], 200),
            'https://hr2.hospital.internal/api/v1/assessments' => Http::response([
                'data' => [
                    [
                        'employee_code' => 'EMP-1001',
                        'competency_code' => 'COMP-IC-01',
                        'current_proficiency' => 5,
                        'assessment_method' => 'observation',
                        'assessed_date' => '2026-03-01',
                        'next_assessment_due' => '2027-03-01',
                        'notes' => 'Flawless sterile demonstration during annual audit.',
                    ],
                ],
            ], 200),
        ]);

        // Configure HR2 in database
        DB::table('system_integrations')->where('system_code', 'hr2')->update([
            'base_url' => 'https://hr2.hospital.internal/api/v1',
            'api_key' => 'secret-hr2-token',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('competency.sync'), ['system' => 'hr2']);

        $response->assertSessionHas('success');

        // Check domain created
        $this->assertDatabaseHas('competency_domains', [
            'domain_name' => 'Clinical Safety',
        ]);

        // Check category created
        $this->assertDatabaseHas('competency_categories', [
            'category_name' => 'Infection Control',
        ]);

        // Check competency created
        $comp = DB::table('competencies')->where('competency_code', 'COMP-IC-01')->first();
        $this->assertNotNull($comp);
        $this->assertEquals(4, $comp->required_proficiency);

        // Check assessment created and gap computed (current 5 - req 4 = gap +1)
        $assessment = DB::table('competency_assessments')
            ->where('employee_id', $this->staffEmpId)
            ->where('competency_id', $comp->competency_id)
            ->first();

        $this->assertNotNull($assessment);
        $this->assertEquals(5, $assessment->current_proficiency);
        $this->assertEquals(1, $assessment->gap);
        $this->assertEquals('observation', $assessment->assessment_method);

        // Check system_integrations record updated
        $integration = DB::table('system_integrations')->where('system_code', 'hr2')->first();
        $this->assertEquals('success', $integration->last_sync_status);
        $this->assertEquals(1, $integration->synced_records_count);
    }

    public function test_admin_can_update_integration_configuration_in_database(): void
    {
        $integration = DB::table('system_integrations')->where('system_code', 'hr1')->first();
        $this->assertNotNull($integration);

        $response = $this->actingAs($this->adminUser)
            ->put(route('competency.integrations.update', $integration->integration_id), [
                'base_url' => 'https://new-hr1-api.hospital.ph/v2',
                'api_key' => 'new-secret-key-1234',
                'timeout' => 45,
                'is_active' => 1,
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('system_integrations', [
            'integration_id' => $integration->integration_id,
            'base_url' => 'https://new-hr1-api.hospital.ph/v2',
            'api_key' => 'new-secret-key-1234',
            'timeout' => 45,
            'is_active' => 1,
        ]);
    }

    public function test_artisan_command_syncs_hr_integrations(): void
    {
        $this->artisan('hims:sync-hr', ['--system' => 'all', '--sample' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('competency_domains', [
            'domain_name' => 'Clinical Excellence',
        ]);

        $this->assertDatabaseHas('competencies', [
            'competency_code' => 'COMP-JCI-01',
        ]);
    }

    public function test_competency_views_display_integration_status_and_no_manual_inputs(): void
    {
        // Competency index view
        $response = $this->actingAs($this->adminUser)->get(route('competency.index'));
        $response->assertStatus(200);
        $response->assertSee('Integration Notice:');
        $response->assertSee('Not Connected');
        $response->assertDontSee('Sync HR1');
        $response->assertDontSee('New Assessment');
        $response->assertDontSee('Add Credential');

        // Credentials index view
        $responseCreds = $this->actingAs($this->adminUser)->get(route('competency.credentials.index'));
        $responseCreds->assertStatus(200);
        $responseCreds->assertSee('Integration Notice:');
        $responseCreds->assertSee('Not Connected');
        $responseCreds->assertDontSee('Sync from HR1');
        $responseCreds->assertDontSee('Import CSV');
        $responseCreds->assertDontSee('data-modal-open="credentialCreateModal"', false);
    }
}
