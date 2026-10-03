<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_learning_modules(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->get(route('learning.modules.index'));

        $response->assertOk();
        $response->assertSee('My courses');
        $response->assertSee('COURSE OVERVIEW');
        $response->assertSee('Capstone Project 1');
        $response->assertSee('MOD-INF-101');
        $response->assertSee('Hospital Infection Control &amp; Hand Hygiene Protocols', false);
        $response->assertSee('pdfModuleViewerOverlay');
        $response->assertSee('pdfViewerIframe');
    }

    public function test_modules_tab_appears_in_learning_tabs_partial(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->get(route('learning.index'));

        $response->assertOk();
        $response->assertSee(route('learning.modules.index'));
        $response->assertSee('Modules');
    }

    public function test_sample_pdf_module_file_exists(): void
    {
        $pdfPath = public_path('modules/infection-control-hand-hygiene.pdf');

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(1000, filesize($pdfPath));
    }
}
