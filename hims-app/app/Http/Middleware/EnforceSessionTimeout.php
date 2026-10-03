<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    /**
     * Handle an incoming request and enforce inactive session timeout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Idle timeout in seconds: defaults to session lifetime (in minutes) * 60 or 900s (15 min)
            $lifetimeMinutes = (int) config('session.lifetime', 15);
            $timeoutSeconds = (int) config('session.idle_timeout', $lifetimeMinutes * 60);

            $lastActivity = $request->session()->get('hims_last_activity');

            if ($lastActivity && (time() - $lastActivity > $timeoutSeconds)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Your session has expired due to inactivity.',
                        'redirect' => route('login', ['timeout' => 1]),
                    ], 401);
                }

                return redirect()->route('login', ['timeout' => 1]);
            }

            // Refresh last active timestamp on valid user activity
            $request->session()->put('hims_last_activity', time());
        }

        return $next($request);
    }
}
