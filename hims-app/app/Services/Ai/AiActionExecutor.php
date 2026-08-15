<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Performs a planned action by calling the controller method that the web form
 * would have called.
 *
 * WHY IT INVOKES THE CONTROLLER INSTEAD OF WRITING TO THE DATABASE
 *
 * Every write in this app carries rules that live in the controller, not the
 * schema: UserController::destroy() refuses to delete the last administrator or
 * your own account; SuccessionController::storeCandidate() reports a duplicate
 * nomination instead of hitting the unique index; PerformanceController::
 * storeCycle() fills created_by from the signed-in profile. A second
 * implementation for the assistant would have to restate all of it and would be
 * wrong the first time any of it changed. So there is no second implementation
 * — the executor builds a Request and calls the same method.
 *
 * The cost of that choice is that route middleware does not run, which is why
 * AiActionRegistry re-derives the role requirement from the route and why
 * execute() refuses anything absent from availableTo(). That check is the only
 * thing standing where `role:` normally stands, so it happens first, before any
 * argument is even resolved.
 */
final class AiActionExecutor
{
    public function __construct(private AiEntityResolver $resolver) {}

    /**
     * @param  array  $plan  From AiActionPlanner::plan()
     * @return array{ok: bool, message: string, resolved?: array, label?: string}
     */
    public function execute(array $plan, User $user, Request $original): array
    {
        $spec = AiActionRegistry::get($plan['action'], $user);

        if (! $spec) {
            return ['ok' => false, 'message' => 'Your role does not allow that action.'];
        }

        $prepared = $this->prepare($spec, $plan, $user);

        if (! $prepared['ok']) {
            return $prepared;
        }

        $route = Route::getRoutes()->getByName($spec['route']);
        $before = $this->snapshot($spec, $prepared['uri']);
        $since = now();

        if ($before && ! ($spec['destructive'] ?? false)) {
            $prepared['params'] = $this->prefill($spec, $prepared['params'], $before);
        }

        try {
            $this->invoke($route, $prepared['params'], $prepared['uri']);
        } catch (ValidationException $e) {
            return ['ok' => false, 'message' => 'That was rejected: '.implode(' ', $e->validator->errors()->all())];
        } catch (HttpExceptionInterface $e) {
            return ['ok' => false, 'message' => $e->getStatusCode() === 403
                ? 'You are not allowed to do that.'
                : 'That record could not be found.'];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'That could not be completed. Nothing was changed.'];
        }

        // Controllers report their own outcome by flashing; read it before the
        // banner leaks into the next page load this session renders.
        $flashOk = session('success');
        $flashErr = session('error');
        session()->forget(['success', 'error']);

        if ($flashErr) {
            return ['ok' => false, 'message' => (string) $flashErr];
        }

        $resourceId = $prepared['uri']
            ? (string) reset($prepared['uri'])
            : $this->newestId($spec, $since);

        AuditTrail::record(
            $this->auditAction($spec, $prepared['uri']),
            $spec['table'],
            $resourceId,
            $original->ip() ?? '0.0.0.0',
            $original->userAgent(),
            $before,
            $prepared['params'],
            [
                'action_key' => $plan['action'],
                'prompt' => $plan['prompt'] ?? null,
                'session_id' => $plan['session_id'] ?? null,
                'provider' => config('services.ai.provider'),
            ],
        );

        return [
            'ok' => true,
            'message' => (string) ($flashOk ?: 'Done.'),
        ];
    }

    /**
     * Whitelist the params, resolve names to ids, and refuse early when
     * something required is missing or ambiguous.
     *
     * @return array{ok: bool, message?: string, params?: array, uri?: array, label?: string}
     */
    public function prepare(array $spec, array $plan, User $user): array
    {
        $resolvers = $spec['resolve'] ?? [];
        $labels = [];
        $params = [];

        // Anything not declared in the registry is dropped: the model cannot
        // reach a column the action does not advertise.
        foreach ($spec['params'] as $name => $_) {
            if (! array_key_exists($name, $plan['params'])) {
                continue;
            }

            $value = $plan['params'][$name];

            if ($value === null || $value === '') {
                continue;
            }

            if (isset($resolvers[$name])) {
                $result = $this->resolveValue($resolvers[$name], $value, $user);

                if (! $result['ok']) {
                    return ['ok' => false, 'message' => 'I need that to be clearer — '.$result['error']];
                }

                $params[$name] = $result['id'];
                $labels[$name] = $result['label'];

                continue;
            }

            $params[$name] = $value;
        }

        // Laravel's `confirmed` rule expects a second field holding the same
        // value — it exists to catch a human mistyping into two password boxes.
        // Chat has one value and no second box, so the check cannot do its job
        // here; the registry names the companion field and it is filled from the
        // value already given. The rule still runs, it just cannot fail this way.
        foreach ($spec['mirror'] ?? [] as $from => $to) {
            if (isset($params[$from])) {
                $params[$to] = $params[$from];
            }
        }

        // Route segments, in the order the URI declares them.
        $uri = [];

        foreach ($spec['uri'] ?? [] as $segment) {
            $value = $plan['params'][$segment] ?? $plan['params']['id'] ?? null;

            if ($value === null || $value === '') {
                return ['ok' => false, 'message' => 'Tell me which record you mean and I will do it.'];
            }

            $type = $resolvers[$segment] ?? $this->defaultUriType($spec);
            $result = $this->resolveValue($type, $value, $user);

            if (! $result['ok']) {
                return ['ok' => false, 'message' => 'I need that to be clearer — '.$result['error']];
            }

            $uri[$segment] = $result['id'];
            $labels[$segment] = $result['label'];
        }

        return [
            'ok' => true,
            'params' => $params,
            'uri' => $uri,
            'label' => $labels ? implode(', ', $labels) : ($spec['label'] ?? ''),
        ];
    }

    /**
     * Resolve one value, or every element when the type ends in [].
     *
     * @return array{ok: bool, id?: mixed, label?: string, error?: string}
     */
    private function resolveValue(string $type, mixed $value, User $user): array
    {
        if (str_ends_with($type, '[]')) {
            $inner = substr($type, 0, -2);
            $ids = [];
            $labels = [];

            foreach ((array) $value as $one) {
                $result = $this->resolver->resolve($inner, (string) $one, $user);

                if (! $result['ok']) {
                    return $result;
                }

                $ids[] = $result['id'];
                $labels[] = $result['label'];
            }

            return ['ok' => true, 'id' => $ids, 'label' => implode(', ', $labels)];
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        return $this->resolver->resolve($type, (string) $value, $user);
    }

    /**
     * When a URI segment has no explicit resolver, infer the entity from the
     * table the action writes to — succession.milestone.* addresses a candidate,
     * performance.review.status addresses a review.
     */
    private function defaultUriType(array $spec): string
    {
        return match ($spec['table']) {
            'review_cycles' => 'cycle',
            'courses', 'course_enrollments' => 'course',
            'training_sessions' => 'session',
            'critical_positions' => 'position',
            'departments' => 'department',
            'users' => 'user',
            'employees' => 'employee',
            default => 'unsupported',
        };
    }

    /**
     * Call the route's controller method (or closure) with a synthesized
     * Request. Arguments are matched by reflection so a method taking no
     * Request, or a route-bound model, both work.
     */
    private function invoke($route, array $params, array $uri): void
    {
        $sub = Request::create('/ai/action', 'POST', $params);
        $sub->setLaravelSession(request()->session());
        $sub->setUserResolver(fn () => auth()->user());

        $uses = $route->getAction('uses');

        if ($uses instanceof \Closure) {
            $reflection = new ReflectionFunction($uses);
            $uses(...$this->arguments($reflection, $sub, $uri));

            return;
        }

        [$class, $method] = explode('@', $uses);
        $controller = app($class);
        $reflection = new ReflectionMethod($controller, $method);

        $controller->{$method}(...$this->arguments($reflection, $sub, $uri));
    }

    /**
     * Build the positional argument list: the Request where one is declared, a
     * bound model where a Model subclass is declared, and the next URI value
     * otherwise.
     */
    private function arguments(ReflectionFunction|ReflectionMethod $reflection, Request $sub, array $uri): array
    {
        $values = array_values($uri);
        $next = 0;
        $args = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

            if ($name && is_a($name, Request::class, true)) {
                $args[] = $sub;

                continue;
            }

            $value = $values[$next] ?? null;
            $next++;

            if ($name && is_a($name, Model::class, true)) {
                // Route-model binding, done by hand: UserController::destroy
                // declares User, not a string id.
                $args[] = $name::findOrFail($value);

                continue;
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * Fill the fields the instruction did not mention from the row as it
     * currently stands.
     *
     * Update controllers here are written against a web form, which always
     * posts the whole record: every field is `required` and every column is
     * overwritten. A chat instruction names one thing ("set her status to
     * probationary"), so without this the call is rejected for fields nobody
     * mentioned — and if it were not rejected it would blank them. The edit
     * form's own starting point is this same row, so this is the payload the
     * form would have submitted.
     *
     * Only registry-declared params are filled, so the whitelist still decides
     * what the model can reach. Creates never reach here: they have no URI
     * segment, so there is no row to read.
     */
    private function prefill(array $spec, array $params, array $row): array
    {
        foreach (array_keys($spec['params']) as $name) {
            if (array_key_exists($name, $params) || ! array_key_exists($name, $row)) {
                continue;
            }

            // A null column is the same as an absent field to a `nullable` rule,
            // and passing null to a `required` one would fail either way.
            if ($row[$name] !== null) {
                $params[$name] = $row[$name];
            }
        }

        return $params;
    }

    /** The row as it stood before an update or delete, for the audit trail. */
    private function snapshot(array $spec, array $uri): ?array
    {
        if (! $uri) {
            return null;
        }

        try {
            $row = DB::table($spec['table'])->where($spec['pk'], reset($uri))->first();
        } catch (Throwable) {
            return null;
        }

        return $row ? (array) $row : null;
    }

    /**
     * Best-effort id of a row created by this call. Concurrency could pick a
     * different row; the audit entry is still correct about who did what, and
     * resource_id is nullable for exactly this reason.
     */
    private function newestId(array $spec, $since): ?string
    {
        try {
            $id = DB::table($spec['table'])
                ->where('created_at', '>=', $since)
                ->orderByDesc('created_at')
                ->value($spec['pk']);
        } catch (Throwable) {
            return null;
        }

        return $id !== null ? (string) $id : null;
    }

    /** audit_trails.action is capped at 30 characters. */
    private function auditAction(array $spec, array $uri): string
    {
        if ($spec['destructive'] ?? false) {
            return 'ai_delete';
        }

        return $uri ? 'ai_update' : 'ai_create';
    }
}
