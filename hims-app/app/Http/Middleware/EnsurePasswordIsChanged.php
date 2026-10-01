<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $exemptRoutes = [
                'profile.edit',
                'password.update',
                'logout',
                '2fa.challenge',
                '2fa.verify',
                '2fa.resend',
            ];

            if (! in_array($request->route()?->getName(), $exemptRoutes, true)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'You must change your temporary password before accessing the system.',
                        'must_change_password' => true,
                    ], 403);
                }

                return redirect()->route('profile.edit')
                    ->with('warning', 'You must set a new password before accessing the system.');
            }
        }

        return $next($request);
    }
}
