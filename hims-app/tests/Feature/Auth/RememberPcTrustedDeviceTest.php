<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RememberPcTrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_has_remember_this_pc_checkbox(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Remember this PC for 30 days');
        $response->assertSee('name="remember_device"', false);
    }

    public function test_two_factor_challenge_screen_has_remember_this_pc_checkbox(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember_device' => '1',
        ]);

        $response = $this->get(route('two-factor.show'));

        $response->assertStatus(200);
        $response->assertSee('Remember this PC for 30 days');
        $response->assertSee('name="remember_device"', false);
        $response->assertSee('checked', false);
    }

    public function test_verifying_2fa_with_remember_device_issues_cookie_and_records_trusted_device(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember_device' => '1',
        ]);

        $sentCode = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });

        $this->assertNotNull($sentCode);

        $verifyResponse = $this->post(route('two-factor.verify'), [
            'code' => $sentCode,
            'remember_device' => '1',
        ]);

        $verifyResponse->assertRedirect(route('dashboard'));
        $verifyResponse->assertCookie(TwoFactorService::TRUSTED_DEVICE_COOKIE);

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('user_trusted_devices', [
            'user_id' => $user->id,
        ]);

        $device = DB::table('user_trusted_devices')->where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        $this->assertTrue(now()->diffInDays(\Carbon\Carbon::parse($device->expires_at)) >= 29);
    }

    public function test_subsequent_login_from_remembered_pc_bypasses_2fa(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // First login and verify with remember device
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember_device' => '1',
        ]);

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $verifyResponse = $this->post(route('two-factor.verify'), [
            'code' => $code,
            'remember_device' => '1',
        ]);

        $cookie = $verifyResponse->getCookie(TwoFactorService::TRUSTED_DEVICE_COOKIE);
        $this->assertNotNull($cookie);
        $cookieValue = $cookie->getValue();

        // Sign out
        $this->post('/logout');
        $this->assertGuest();

        // Mail reset count
        Mail::fake();

        // Subsequent login from same PC presenting the trusted device cookie
        $subsequentResponse = $this->withCookie(TwoFactorService::TRUSTED_DEVICE_COOKIE, $cookieValue)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        // Should bypass 2FA and redirect directly to dashboard!
        $subsequentResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // No 2FA email should have been sent!
        Mail::assertNothingSent();
    }

    public function test_login_from_untrusted_device_or_expired_cookie_still_requires_2fa(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // Login with expired token in DB
        DB::table('user_trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'fake-expired-token'),
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $response = $this->withCookie(TwoFactorService::TRUSTED_DEVICE_COOKIE, 'fake-expired-token')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        // Must redirect to 2FA challenge, not dashboard
        $response->assertRedirect(route('two-factor.show'));
        $this->assertGuest();

        Mail::assertSent(TwoFactorCodeMail::class);
    }
}
