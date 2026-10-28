<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HrIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HrIntegrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createIntegration(): string
    {
        $id = DB::table('system_integrations')->where('system_code', 'hr1')->value('integration_id');
        if (! $id) {
            $id = (string) Str::uuid();
            DB::table('system_integrations')->insert([
                'integration_id' => $id,
                'system_code' => 'hr1',
                'system_name' => 'HR1 Core',
                'base_url' => 'https://api.external-hr.com',
                'api_key' => Crypt::encryptString('secret-test-key'),
                'timeout' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    public function test_supervisor_cannot_update_integration_settings(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $id = $this->createIntegration();

        $response = $this->actingAs($supervisor)->put(route('competency.integrations.update', $id), [
            'base_url' => 'https://attacker.com',
            'api_key' => 'stolen-token',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_save_internal_or_insecure_ssrf_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = $this->createIntegration();

        $badUrls = [
            'http://localhost:8080',
            'http://127.0.0.1:8000',
            'http://169.254.169.254/latest/meta-data',
            'http://192.168.1.100',
            'http://10.0.0.1',
            'ftp://example.com',
            'gopher://example.com',
        ];

        foreach ($badUrls as $url) {
            $response = $this->actingAs($admin)->put(route('competency.integrations.update', $id), [
                'base_url' => $url,
                'api_key' => 'key',
            ]);

            $response->assertSessionHasErrors('base_url');
        }
    }

    public function test_admin_can_save_valid_https_url_and_api_key_is_encrypted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = $this->createIntegration();

        $response = $this->actingAs($admin)->put(route('competency.integrations.update', $id), [
            'base_url' => 'https://hr-provider.hospital.ph/api',
            'api_key' => 'super-secret-token-123',
            'timeout' => 45,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $row = DB::table('system_integrations')->where('integration_id', $id)->first();
        $this->assertSame('https://hr-provider.hospital.ph/api', $row->base_url);

        // Raw database column is encrypted, not plaintext
        $this->assertNotSame('super-secret-token-123', $row->api_key);
        $this->assertSame('super-secret-token-123', Crypt::decryptString($row->api_key));

        // HrIntegrationService getConfig returns decrypted key
        $service = app(HrIntegrationService::class);
        $config = $service->getConfig('hr1');
        $this->assertSame('super-secret-token-123', $config['api_key']);
    }

    public function test_sync_aborts_if_configured_url_fails_ssrf_safety_check(): void
    {
        $service = app(HrIntegrationService::class);

        // Directly seed unsafe URL in DB
        DB::table('system_integrations')->updateOrInsert(
            ['system_code' => 'hr1'],
            [
                'integration_id' => (string) Str::uuid(),
                'system_name' => 'HR1 Core',
                'base_url' => 'http://169.254.169.254',
                'api_key' => 'key',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $result = $service->syncHr1();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('SSRF', $result['message']);
    }
}
