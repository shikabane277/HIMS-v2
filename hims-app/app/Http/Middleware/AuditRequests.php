<?php

namespace App\Http\Middleware;

use App\Support\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline audit coverage for the whole application.
 *
 * Domain code still writes rich entries (before/after state) where it matters
 * most. This middleware guarantees that nothing slips through the gaps:
 *
 *   - every successful state-changing request (POST/PUT/PATCH/DELETE) by a
 *     signed-in user is recorded as `http_<verb>` with the route name, route
 *     parameters and the submitted field values (secrets removed); and
 *   - opening a sensitive record (employee file, credentials, reviews, gap
 *     analysis, succession, appraisal reports, audit log) is recorded as
 *     `view`, because "who looked at this file" is a standard hospital
 *     privacy question.
 *
 * Failed requests (4xx/5xx, or a redirect carrying validation errors) are not
 * logged here; they changed nothing.
 */
class AuditRequests
{
    /** Routes whose GET is a record view worth auditing. */
    private const VIEW_ROUTES = [
        'employees.show', 'employees.progression', 'employees.progression.mine',
        'competency.credentials.index', 'competency.gap.employee', 'competency.gap.employee.json',
        'performance.show', 'performance.reviews.score',
        'succession.candidates.show', 'succession.positions.show',
        'reports.appraisal.show', 'reports.performance', 'reports.compliance',
        'audit.history', 'profile.export-data', 'profile.export-csv',
    ];

    /** Write routes that are housekeeping, not record changes. */
    private const SKIP_ROUTES = [
        'session.activity', 'session.ping', 'logout', 'log-error',
        'notifications.read', 'notifications.read-all',
        'ai.query', 'ai.sessions.store', 'ai.sessions.update',
        'record-access.verify', 'record-access.unlock', 'record-access.lock',
        'cron.execute',
    ];

    private const SECRET_FIELDS = '/pass(word)?|secret|token|api_?key|totp|code|otp|_method/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            // Auditing must never take the request down with it.
            Log::error('AuditRequests failed', ['exception' => $e->getMessage(), 'path' => $request->path()]);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        if (! auth()->check()) {
            return;
        }

        $route = $request->route();
        $name = $route?->getName();

        if (! $name || in_array($name, self::SKIP_ROUTES, true)) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            return;
        }

        $method = strtoupper($request->method());
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if (! $isWrite && ! in_array($name, self::VIEW_ROUTES, true)) {
            return;
        }

        // Validation bounced this request back unchanged. Only errors flashed by
        // *this* request count — errors from the previous request are still in
        // the session for one more hop and must not hide a successful retry.
        if ($isWrite && $request->hasSession()
            && in_array('errors', (array) $request->session()->get('_flash.new', []), true)) {
            return;
        }

        $params = collect($route->parameters())
            ->map(fn ($v) => is_scalar($v) ? (string) $v : null)
            ->filter()
            ->all();

        $resourceId = collect($params)->first(fn ($v) => Str::isUuid($v)) ?? null;

        AuditTrail::record(
            $isWrite ? 'http_'.strtolower($method) : 'view',
            Str::limit($name, 50, ''),
            $resourceId,
            afterState: $isWrite ? $this->sanitisedInput($request) : null,
            metadata: array_filter([
                'route' => $name,
                'params' => $params ?: null,
                'status' => $status,
                'query' => $isWrite ? null : ($request->query() ? $this->sanitise($request->query()) : null),
            ]),
        );
    }

    private function sanitisedInput(Request $request): ?array
    {
        $input = $request->except(['_token', '_method']);

        return $input ? $this->sanitise($input) : null;
    }

    private function sanitise(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_FIELDS, $key)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = $this->sanitise($value);
            } elseif (is_object($value)) {
                $out[$key] = '[file]';
            } else {
                $out[$key] = is_string($value) ? Str::limit($value, 500) : $value;
            }
        }

        return $out;
    }
}
