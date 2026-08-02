<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on users.role.
 *
 * Usage in routes: ->middleware('role:admin,hr_manager')
 *
 * Authentication is handled separately by the 'auth' middleware; this only
 * answers "is this account's role one of the permitted ones".
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole(...$roles)) {
            abort(403, 'Your role does not have access to this area.');
        }

        return $next($request);
    }
}
