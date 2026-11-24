<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authoritative idle timer, and the live-session activity check.
 *
 * HOSPITAL POLICY: three minutes of no activity ends the session, with no warning
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
 * The limit is config('session.idle_timeout') in SECONDS (SESSION_IDLE_TIMEOUT,
 * default 120 = 2 minutes). The browser script and session/ping read the same value via
 * idleTimeoutSeconds(), so all three agree.
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
                return $this->endSession($request, 'Your account has been deactivated.', ['reason' => 'deactivated']);
            }

            // A trainee's placement ended: same effect as deactivation.
            if (method_exists($user, 'accessHasExpired') && $user->accessHasExpired()) {
                return $this->endSession($request, 'Your access period has ended.', ['reason' => 'expired']);
            }

            $timeoutSeconds = $this->idleTimeoutSeconds();

            $lastActivity = $request->session()->get('hims_last_activity');

            if ($lastActivity && (time() - (int) $lastActivity > $timeoutSeconds)) {
                return $this->endSession($request, 'Your session has expired due to inactivity.');
            }

            // The status probe must never count as activity, or a timer could
            // hold a forgotten tab open. Everything else — including the
            // input-driven heartbeat (session/activity) — does.
            if (! $request->routeIs('session.ping')) {
                $request->session()->put('hims_last_activity', time());
            }
        }

        return $next($request);
    }

    /**
     * Idle limit in seconds, from config('session.idle_timeout') only.
     *
     * Previously this read env() directly and treated the value as minutes,
     * while the browser treated the same setting as seconds — a .env value of
     * 180 meant 3 minutes in the tab and 3 hours on the server. env() also
     * returns null once `php artisan config:cache` has run.
     */
    public static function idleTimeoutSeconds(): int
    {
        return max(60, (int) config('session.idle_timeout', 120));
    }

    /**
     * Log out and send the visitor somewhere that explains why.
     */
    private function endSession(Request $request, string $message, array $query = ['timeout' => 1]): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'redirect' => route('landing', $query),
            ], 401);
        }

        // Back to the public landing page rather than the sign-in form, so an
        // unattended screen shows nothing about anyone.
        return redirect()->route('landing', $query);
    }
}
