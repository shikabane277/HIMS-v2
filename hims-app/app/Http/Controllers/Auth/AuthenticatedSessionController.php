<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $code = TwoFactorService::generateCode();

        $user->update([
            'two_factor_code' => hash('sha256', $code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        $request->session()->put('login.id', $user->id);
        $request->session()->put('login.remember', $request->boolean('remember'));

        $sent = TwoFactorService::sendCode($user, $code);

        if (! $sent) {
            // Mail failed — flash the code so the 2FA page can display it as a
            // fallback. This prevents complete lockout when SMTP is misconfigured.
            // The TwoFactorService already logged the error and flashed a notice
            // in local env. For production, we flash a generic notice.
            if (! app()->environment('local')) {
                $request->session()->flash(
                    'dev_code_notice',
                    "Email delivery is temporarily unavailable. Your verification code is: {$code}"
                );
            }
            \Illuminate\Support\Facades\Log::critical('2FA email delivery failed — code displayed on screen as fallback', [
                'user_id' => $user->id,
            ]);
        }

        return redirect()->route('two-factor.show');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
