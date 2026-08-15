<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The root path is not a landing page. HIMS is an internal system with no
 * public face, so `/` redirects straight to the login screen.
 */
class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login', absolute: false));
    }
}
