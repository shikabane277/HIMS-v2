<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes to the `audit_trails` table as a tamper-evident hash chain.
 *
 * Every row carries:
 *   - `chain_seq`          strictly increasing position in the chain
 *   - `before_state_hash`  SHA-256 of the canonical before-state (or null)
 *   - `after_state_hash`   SHA-256 of the canonical after-state (or null)
 *   - `chain_hash`         SHA-256(previous chain_hash + this row's content hash)
 *
 * Editing, deleting or re-ordering any row breaks every hash after it, which
 * `php artisan hims:audit-verify` detects. The chain head (last seq + hash) is
 * kept in system_settings under `audit_chain_head` and is locked while a row is
 * appended so two concurrent writers cannot fork the chain.
 *
 * Hashes are computed over a canonical form (recursively key-sorted JSON) so
 * MySQL's JSON column re-formatting does not produce false alarms on verify.
 */
class AuditTrail
{
    public const HEAD_KEY = 'audit_chain_head';

    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Record one action to the audit trail.
     *
     *     AuditTrail::record('assign_training', 'training_assignments', $id,
     *         afterState: $payload);
     *
     * @param  string  $action  Verb, max 30 chars (ai_create, assign_training, ...)
     * @param  string  $resourceType  Table name (employees, review_cycles, ...)
     * @param  string|null  $resourceId  Target UUID — best effort on creates
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
        $now = now()->format('Y-m-d H:i:s');

        $row = [
            'audit_id' => $id,
            'timestamp' => $now,
            'user_id' => (string) auth()->id(),
            'employee_id' => auth()->user()?->employee_id,
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
            'before_state_hash' => $beforeState ? hash('sha256', self::canonical($beforeState)) : null,
            'after_state_hash' => $afterState ? hash('sha256', self::canonical($afterState)) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::transaction(function () use (&$row) {
            [$seq, $previous] = self::lockHead();

            $row['chain_seq'] = $seq + 1;
            $row['chain_hash'] = hash('sha256', $previous.self::contentHash($row));

            DB::table('audit_trails')->insert($row);

            DB::table('system_settings')->updateOrInsert(
                ['key' => self::HEAD_KEY],
                ['value' => $row['chain_seq'].':'.$row['chain_hash'], 'updated_at' => now()]
            );
        });

        return $id;
    }

    /**
     * SHA-256 over the fields that make up a row's meaning. Used both when the
     * row is written and by hims:audit-verify, so the two must stay identical.
     */
    public static function contentHash(array|object $row): string
    {
        $r = (array) $row;

        $metadata = $r['metadata'] ?? null;
        if (is_string($metadata) && $metadata !== '') {
            $metadata = json_decode($metadata, true);
        } elseif ($metadata === '') {
            $metadata = null;
        }

        $parts = [
            'seq' => (int) ($r['chain_seq'] ?? 0),
            'audit_id' => (string) $r['audit_id'],
            'timestamp' => substr((string) $r['timestamp'], 0, 19),
            'user_id' => (string) ($r['user_id'] ?? ''),
            'employee_id' => ($r['employee_id'] ?? null) ?: null,
            'action' => (string) $r['action'],
            'resource_type' => (string) $r['resource_type'],
            'resource_id' => ($r['resource_id'] ?? null) ?: null,
            'ip_address' => (string) $r['ip_address'],
            'request_method' => ($r['request_method'] ?? null) ?: null,
            'request_path' => ($r['request_path'] ?? null) ?: null,
            'before_state_hash' => ($r['before_state_hash'] ?? null) ?: null,
            'after_state_hash' => ($r['after_state_hash'] ?? null) ?: null,
            'metadata' => $metadata,
        ];

        return hash('sha256', self::canonical($parts));
    }

    /**
     * Deterministic JSON: object keys sorted at every depth.
     */
    public static function canonical(mixed $value): string
    {
        $sort = static function ($v) use (&$sort) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        return (string) json_encode($sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Lock and read the chain head. Returns [lastSeq, lastHash].
     */
    private static function lockHead(): array
    {
        $head = DB::table('system_settings')->where('key', self::HEAD_KEY)->lockForUpdate()->value('value');

        if ($head && str_contains($head, ':')) {
            [$seq, $hash] = explode(':', $head, 2);

            return [(int) $seq, $hash];
        }

        // First chained row: continue after any rows already chained.
        $last = DB::table('audit_trails')->whereNotNull('chain_seq')->orderByDesc('chain_seq')->first();

        return $last ? [(int) $last->chain_seq, (string) $last->chain_hash] : [0, self::GENESIS];
    }
}
