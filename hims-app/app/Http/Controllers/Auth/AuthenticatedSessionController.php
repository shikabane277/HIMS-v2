<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     *
     * THERE IS NO TRUSTED-DEVICE BYPASS.
     *
     * This method used to start with a 30-day "remember this PC" branch that
     * called Auth::login() directly and skipped the second factor entirely. It is
     * gone, along with the checkbox and the cookie, and the branch is not to be
     * reintroduced here: a durable cookie that stands in for a second factor is a
     * single secret an attacker can copy off a workstation, and it survives the
     * password change that is supposed to invalidate access. Everyone completes
     * two-factor on every login, on every machine.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $code = TwoFactorService::generateCode();

        $user->update([
            'two_factor_code' => hash('sha256', $code),
            'two_factor_expires_at' => now()->addMinutes(5),
        ]);

        $request->session()->put('login.id', $user->id);

        $sent = TwoFactorService::sendCode($user, $code);

        /*
         * A FAILED DELIVERY IS NOT A REASON TO SHOW THE CODE.
         *
         * This used to flash "Your verification code is: {$code}" onto the
         * challenge page whenever mail failed — outside local, deliberately, so a
         * misconfigured SMTP would not lock everyone out. That made the mail
         * server the second factor's weakest link in the worst possible
         * direction: anyone holding a password but not the mailbox could read the
         * code straight off the screen, and could reach that state at will by
         * inducing a delivery failure. The lockout it was protecting against is a
         * support call; the bypass it created is an account takeover.
         *
         * So the code is never rendered anywhere. The visitor is told plainly that
         * delivery failed and is left on the challenge page, where resend is
         * available. The code itself only ever exists in the user's mailbox.
         */
        if (! $sent) {
            Log::critical('2FA email delivery failed — no code shown, challenge page held', [
                'user_id' => $user->id,
            ]);

            return redirect()->route('two-factor.show')
                ->with('error', 'We could not send your verification code. Check your connection and try resending, or contact IT support.');
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
