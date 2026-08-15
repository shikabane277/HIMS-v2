<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes to the `audit_trails` table.
 *
 * Coverage is deliberately partial, not comprehensive: AI-executed writes and
 * compliance actions (training assignment, renewal-rule changes) are recorded;
 * ordinary UI edits are not. Hash-chain columns (`before_state_hash`,
 * `after_state_hash`, `chain_hash`) remain null — tamper-proof chaining is
 * documented as not implemented, and a half-built chain would be worse than an
 * absent one.
 */
class AuditTrail
{
    /**
     * Record one action to the audit trail.
     *
     * `$ipAddress`, `$userAgent`, `$requestMethod` and `$requestPath` default to
     * the live request when omitted, so a controller can log with just the first
     * three arguments plus named args:
     *
     *     AuditTrail::record('assign_training', 'training_assignments', $id,
     *         afterState: $payload);
     *
     * @param  string  $action  Verb, max 30 chars (ai_create, assign_training, ...)
     * @param  string  $resourceType  Table name (employees, review_cycles, ...)
     * @param  string|null  $resourceId  Target UUID — best effort on creates
     * @param  string|null  $ipAddress  Defaults to the current request's IP
     * @param  string|null  $userAgent  Defaults to the current request's agent
     * @param  array|null  $beforeState  Row snapshot for update/delete, null for create
     * @param  array|null  $afterState  Resulting values
     * @param  array|null  $metadata  Free-form context
     * @param  string|null  $requestMethod  Defaults to the current request's verb
     * @param  string|null  $requestPath  Defaults to the current request's path
     * @return string The inserted audit_id
     */
    public static function record(
        string $action,
        string $resourceType,
        ?string $resourceId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?array $metadata = null,
        ?string $requestMethod = null,
        ?string $requestPath = null,
    ): string {
        $id = (string) Str::uuid();
        $request = request();

        DB::table('audit_trails')->insert([
            'audit_id' => $id,
            'timestamp' => now(),
            'user_id' => (string) auth()->id(),
            'employee_id' => self::currentEmployeeId(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            // NOT NULL in the schema, so it always needs a value.
            'ip_address' => $ipAddress ?: ($request?->ip() ?: '0.0.0.0'),
            'user_agent' => $userAgent ?: $request?->userAgent(),
            'request_method' => $requestMethod ?: ($request?->method() ?: 'CLI'),
            'request_path' => $requestPath ?: ($request ? '/'.$request->path() : null),
            'before_state' => $beforeState ? json_encode($beforeState) : null,
            'after_state' => $afterState ? json_encode($afterState) : null,
            // Hash-chain columns stay null: chaining is not implemented.
            'before_state_hash' => null,
            'after_state_hash' => null,
            'chain_hash' => null,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * The signed-in user's employee_id, or null if no link exists.
     * Mirrors Controller::currentEmployeeId() — the audit helper must not
     * depend on the Controller class.
     */
    private static function currentEmployeeId(): ?string
    {
        $user = auth()->user();

        return $user?->employee_id;
    }
}
