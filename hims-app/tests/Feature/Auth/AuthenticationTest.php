<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Password');
        $response->assertSee('Forgot password?');
    }

    public function test_submitting_valid_credentials_initiates_2fa_and_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.show'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_code);
        $this->assertNotNull($user->two_factor_expires_at);

        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email)
                && strlen($mail->code) === 6
                && preg_match('/[A-Z]/', $mail->code)
                && preg_match('/[a-z]/', $mail->code);
        });
    }

    public function test_users_can_authenticate_using_the_login_screen_with_2fa(): void
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

        $this->assertNotNull($sentCode);
        $this->assertSame(6, strlen($sentCode));

        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertAuthenticatedAs($user);
        $verifyResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_2fa_verification_is_strictly_case_sensitive(): void
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

        // Invert the case of the first alphabetic character
        $wrongCaseCode = $sentCode;
        for ($i = 0; $i < strlen($wrongCaseCode); $i++) {
            if (ctype_upper($wrongCaseCode[$i])) {
                $wrongCaseCode[$i] = strtolower($wrongCaseCode[$i]);
                break;
            } elseif (ctype_lower($wrongCaseCode[$i])) {
                $wrongCaseCode[$i] = strtoupper($wrongCaseCode[$i]);
                break;
            }
        }

        $this->assertNotSame($sentCode, $wrongCaseCode);

        // Attempt verification with incorrect case
        $response = $this->post('/two-factor-challenge', [
            'code' => $wrongCaseCode,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_users_can_not_authenticate_with_invalid_2fa_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post('/two-factor-challenge', [
            'code' => 'Xy99Zz',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_2fa_code_cannot_be_used_after_expiration(): void
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

        // Travel 15 minutes into the future
        $this->travel(15)->minutes();

        $response = $this->post('/two-factor-challenge', [
            'code' => $sentCode,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_users_can_resend_2fa_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Mail::assertSentCount(1);

        $resendResponse = $this->post('/two-factor-challenge/resend');
        $resendResponse->assertSessionHas('status');

        Mail::assertSentCount(2);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
