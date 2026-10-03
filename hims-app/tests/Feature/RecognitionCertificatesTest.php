<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecognitionCertificatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_learning_certificates_route_redirects_to_recognition_certificates(): void
    {
        $response = $this->actingAs($this->admin)->get('/learning/certificates');
        $response->assertRedirect(route('recognition.certificates.index'));
    }

    public function test_recognition_certificates_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('recognition.certificates.index'));
        $response->assertStatus(200);
        $response->assertSee('Issued Certificates');
        $response->assertSee('Certificates');
        $response->assertSee(route('recognition.certificates.index'));
    }

    public function test_learning_tabs_do_not_contain_certificates_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(route('learning.index'));
        $response->assertStatus(200);
        // The learning section dropdown/tab strip should not have certificates
        $response->assertDontSee('data-icon="bi bi-patch-check"');
    }
}
