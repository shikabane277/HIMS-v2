<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorAuthenticatedSessionController extends Controller
{
    /**
     * Show the two-factor authentication challenge view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('login.id'));
        if (! $user) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        return view('auth.two-factor', [
            'maskedEmail' => TwoFactorService::maskEmail($user->email),
            'rememberDevice' => (bool) $request->session()->get('login.remember_device'),
        ]);
    }

    /**
     * Verify the two-factor authentication code.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('login.id'));
        if (! $user) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        $throttleKey = '2fa|'.$user->id.'|'.$request->ip();
        $userThrottleKey = '2fa|'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($userThrottleKey, 10)) {
            $seconds = max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($userThrottleKey));

            throw ValidationException::withMessages([
                'code' => "Too many invalid verification attempts. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        $inputCode = trim((string) $request->input('code'));

        $totpService = app(TotpService::class);
        $isTotpValid = ! empty($user->totp_secret) && $totpService->verify($user->totp_secret, $inputCode);
        $isEmailValid = ! empty($user->two_factor_code) && $user->two_factor_expires_at && now()->lte($user->two_factor_expires_at) && hash_equals($user->two_factor_code, hash('sha256', $inputCode));

        if (! $isTotpValid && ! $isEmailValid) {
            RateLimiter::hit($throttleKey, 600);
            RateLimiter::hit($userThrottleKey, 600);

            if ($user->two_factor_expires_at && now()->gt($user->two_factor_expires_at) && empty($user->totp_secret)) {
                throw ValidationException::withMessages([
                    'code' => 'The verification code has expired. Please click "Resend Code" to receive a new one.',
                ]);
            }

            throw ValidationException::withMessages([
                'code' => 'The verification code is incorrect or expired. Enter the 6-character code from your email or the 6-digit code from your authenticator app.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($userThrottleKey);
        $user->resetTwoFactorCode();

        $rememberDevice = $request->boolean('remember_device') || (bool) $request->session()->get('login.remember_device');

        $request->session()->forget(['login.id', 'login.remember', 'login.remember_device']);

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('hims_last_activity', time());

        $cookie = null;
        if ($rememberDevice) {
            $cookie = TwoFactorService::trustDevice($request, $user);
        }

        $redirect = $user->must_change_password
            ? redirect()->route('profile.edit')->with('warning', 'Please change your temporary password before proceeding.')
            : redirect()->intended(route('dashboard', absolute: false));

        if ($cookie) {
            $redirect->withCookie($cookie);
        }

        return $redirect;
    }

    /**
     * Resend a fresh 6-character two-factor code.
     */
    public function resend(Request $request): RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('login.id'));
        if (! $user) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        $resendThrottleKey = '2fa-resend|'.$user->id;
        if (RateLimiter::tooManyAttempts($resendThrottleKey, 1)) {
            $seconds = RateLimiter::availableIn($resendThrottleKey);

            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting a new code.",
            ]);
        }

        RateLimiter::hit($resendThrottleKey, 30);

        $code = TwoFactorService::generateCode();
        $user->update([
            'two_factor_code' => hash('sha256', $code),
            'two_factor_expires_at' => now()->addMinutes(2),
        ]);

        $sent = TwoFactorService::sendCode($user, $code);

        if (! $sent && ! app()->environment('local')) {
            $request->session()->flash(
                'dev_code_notice',
                "Email delivery is temporarily unavailable. Your verification code is: {$code}"
            );
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Cancel the two-factor authentication challenge and return to login.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['login.id', 'login.remember']);

        return redirect()->route('login');
    }
}
