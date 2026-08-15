<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Public self-registration is deliberately disabled — see the block comment in
 * routes/auth.php. A self-registered account would land with no employee_id and
 * the users.role default of 'staff', which is a stranger holding a login to
 * workforce data. Accounts are provisioned by an admin through UserController.
 *
 * These tests assert the routes are ABSENT. Breeze's RegisteredUserController
 * and auth/register.blade.php are intentionally left on disk but unrouted, so a
 * test that only checked for the controller would not catch a re-route.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_cannot_be_posted(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_no_registration_route_is_registered(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

        $this->assertFalse($names->contains('register'),
            'A route named "register" exists — self-registration must stay disabled.');
    }
}
