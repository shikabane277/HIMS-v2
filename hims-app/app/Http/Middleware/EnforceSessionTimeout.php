<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authoritative idle timer, and the live-session activity check.
 *
 * HOSPITAL POLICY: five minutes of no activity ends the session, with no warning
 * of any kind. There is deliberately no "your session expires in 60 seconds"
 * modal and no "Stay signed in" button — an untouched tab on a shared ward
 * terminal has to stop being a way into staff records, and a prompt is a way of
 * keeping it alive. The client-side script in partials/idle-logout exists only to
 * end an unused tab promptly; this middleware is what actually decides, so a
 * re-render, a second tab, or JavaScript switched off all still expire.
 *
 * WHY THE DEADLINE IS SERVED-SIDE AND NOT IN THE BROWSER
 *
 * A browser clock is the visitor's to set. This compares against the server's
 * `time()` and stores the last-seen stamp in the session, so a client that keeps
 * pinging cannot hold a session open past the limit (see routes/auth.php
 * `session/ping`, which now checks rather than refreshes).
 *
 * The config value is read the same way Laravel reads session.lifetime so the
 * two agree: env('SESSION_IDLE_TIMEOUT') minutes if set, else the session
 * lifetime in minutes, else 5.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            /*
             * A deactivated account must lose its session on the next request,
             * not merely be refused a new login.
             *
             * `is_active` was checked only in LoginRequest, so an HR admin
             * deactivating a terminating employee left that employee's open tab
             * fully privileged — and because every request refreshed
             * hims_last_activity, the idle timer never fired to end it either.
             * A revocation that only takes effect at the next login is not a
             * revocation, so this check lives here, on every request.
             */
            if (isset($user->is_active) && ! $user->is_active) {
                return $this->endSession($request, 'Your account has been deactivated.');
            }

            $timeoutSeconds = $this->idleTimeoutSeconds();

            $lastActivity = $request->session()->get('hims_last_activity');

            if ($lastActivity && (time() - (int) $lastActivity > $timeoutSeconds)) {
                return $this->endSession($request, 'Your session has expired due to inactivity.');
            }

            $request->session()->put('hims_last_activity', time());
        }

        return $next($request);
    }

    /**
     * Idle limit in seconds, resolved the way Laravel resolves session.lifetime.
     *
     * env() is consulted first rather than only config(), because .env may set
     * SESSION_LIFETIME while config/session.php's default chain would otherwise
     * hand back a smaller value than the operator asked for. Both are in
     * minutes, matching Laravel's own units.
     */
    private function idleTimeoutSeconds(): int
    {
        $minutes = (int) (env('SESSION_IDLE_TIMEOUT') ?: (env('SESSION_LIFETIME') ?: config('session.lifetime', 5)));

        // A zero or negative value would expire every request instantly; fall
        // back to the hospital's 5 minutes rather than locking everyone out.
        return max(60, $minutes * 60);
    }

    /**
     * Log out and send the visitor somewhere that explains why.
     */
    private function endSession(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'redirect' => route('login', ['timeout' => 1]),
            ], 401);
        }

        return redirect()->route('login', ['timeout' => 1]);
    }
}
