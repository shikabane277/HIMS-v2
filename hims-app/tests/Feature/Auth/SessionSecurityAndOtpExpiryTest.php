<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SessionSecurityAndOtpExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_does_not_contain_keep_me_signed_in(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Keep me signed in');
        $response->assertDontSee('name="remember"', false);
    }

    public function test_two_factor_screen_reflects_five_minute_code_expiration(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->get(route('two-factor.show'));
        $response->assertStatus(200);
        $response->assertSee('Codes expire after 5 minutes');
    }

    public function test_otp_code_is_valid_within_five_minutes(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $sentCode = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });

        // Fast-forward 3 minutes (within the 5-minute window)
        $this->travel(3)->minutes();

        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertAuthenticatedAs($user);
        $verifyResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_otp_code_expires_after_five_minutes(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $sentCode = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });

        // Fast-forward 5 minutes and 10 seconds (exceeded 5-minute window)
        $this->travel(310)->seconds();

        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertGuest();
        $verifyResponse->assertSessionHasErrors('code');
    }

    public function test_resending_otp_also_sets_five_minute_expiry(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post('/two-factor-challenge/resend');

        $user->refresh();
        $this->assertNotNull($user->two_factor_expires_at);
        $diffInSeconds = now()->diffInSeconds($user->two_factor_expires_at, false);
        $this->assertGreaterThan(280, $diffInSeconds);
        $this->assertLessThanOrEqual(300, $diffInSeconds);
    }

    public function test_session_ping_endpoint_reports_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['hims_last_activity' => time() - 30])
            ->postJson('/session/ping');

        $response->assertOk();
        $response->assertJson(['status' => 'active']);
    }

    public function test_session_timeout_logs_out_inactive_user_and_redirects_to_login(): void
    {
        Config::set('session.idle_timeout', 900); // 15 minutes

        $user = User::factory()->create();

        // User was active 950 seconds ago (exceeded 900s timeout)
        $response = $this->actingAs($user)
            ->withSession(['hims_last_activity' => time() - 950])
            ->get(route('dashboard'));

        $this->assertGuest();
        $response->assertRedirect(route('login', ['timeout' => 1]));
    }

    public function test_login_page_renders_timeout_warning_notice(): void
    {
        $response = $this->get('/login?timeout=1');

        $response->assertStatus(200);
        $response->assertSee('Your session has expired due to inactivity');
    }

    public function test_deactivated_user_session_is_terminated_immediately_on_next_request(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertGuest();
        $response->assertRedirect(route('login', ['timeout' => 1]));
    }

    public function test_2fa_email_delivery_failure_does_not_reveal_code_on_screen(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP Server Down'));

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.show'));
        $challengePage = $this->get(route('two-factor.show'));

        $challengePage->assertDontSee('Your verification code is:');
        $challengePage->assertDontSee('dev_code_notice');
    }
}
