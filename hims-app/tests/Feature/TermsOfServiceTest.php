<?php

namespace Tests\Feature;

use Tests\TestCase;

class TermsOfServiceTest extends TestCase
{
    public function test_guest_can_access_terms_of_service_page(): void
    {
        $response = $this->get('/terms-of-service');

        $response->assertStatus(200);
        $response->assertSee('Terms of Service');
        $response->assertSee('Republic Act No. 10173');
        $response->assertSee('GDPR');
        $response->assertSee('three (3) minutes of inactivity');
        $response->assertSee('five (5) minutes');
        $response->assertSee('Schedule of Personal Data Collected');
    }

    public function test_login_page_links_to_terms_of_service(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(route('terms-of-service'));
        $response->assertSee('Terms of Service & Data Protection Notice');
        $response->assertSee('Sessions end after 3 minutes idle');
    }
}

