<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GDPR Article 25 & Article 32 — Data Protection by Design and Security of Processing.
 *
 * Applies strict HTTP security, anti-tracking, and cache-control headers to
 * prevent browser caching of personal data on shared hospital terminals,
 * clickjacking, MIME sniffing, and cross-origin referrer leakage.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Prevent shared hospital workstation browsers from caching authenticated PII pages.
        if (Auth::check() && ! $response->headers->has('Content-Disposition')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}

