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

    public function test_two_factor_screen_reflects_two_minute_code_expiration(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->get(route('two-factor.show'));
        $response->assertStatus(200);
        $response->assertSee('Codes expire after 2 minutes');
    }

    public function test_otp_code_is_valid_within_two_minutes(): void
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

        // Fast-forward 1 minute (within the 2-minute window)
        $this->travel(1)->minutes();

        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertAuthenticatedAs($user);
        $verifyResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_otp_code_expires_after_two_minutes(): void
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

        // Fast-forward 2 minutes and 10 seconds (exceeded 2-minute window)
        $this->travel(130)->seconds();

        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertGuest();
        $verifyResponse->assertSessionHasErrors('code');
    }

    public function test_resending_otp_also_sets_two_minute_expiry(): void
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
        $this->assertGreaterThan(100, $diffInSeconds);
        $this->assertLessThanOrEqual(120, $diffInSeconds);
    }

    public function test_session_ping_endpoint_refreshes_activity_timestamp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['hims_last_activity' => time() - 300])
            ->postJson('/session/ping');

        $response->assertOk();
        $response->assertJson(['status' => 'active']);
        $this->assertGreaterThanOrEqual(time() - 2, session('hims_last_activity'));
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
}
