<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
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

        $throttleKey = '2fa|' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "Too many invalid verification attempts. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        if (! $user->two_factor_expires_at || now()->gt($user->two_factor_expires_at)) {
            throw ValidationException::withMessages([
                'code' => 'The verification code has expired. Please click "Resend Code" to receive a new one.',
            ]);
        }

        $inputCode = trim((string) $request->input('code'));

        if (! $user->two_factor_code || ! hash_equals($user->two_factor_code, hash('sha256', $inputCode))) {
            RateLimiter::hit($throttleKey, 600);

            throw ValidationException::withMessages([
                'code' => 'The verification code is incorrect. Case matters: uppercase and lowercase characters must match exactly.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $user->resetTwoFactorCode();

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
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

        $resendThrottleKey = '2fa-resend|' . $user->id;
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
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        TwoFactorService::sendCode($user, $code);

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
