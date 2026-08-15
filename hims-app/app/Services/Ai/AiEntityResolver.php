<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns the human names a person types in chat into the UUIDs the controllers
 * expect. "delete the employee Juan Dela Cruz" has to become an employee_id
 * before EmployeeController::destroy() can be called.
 *
 * THE THREE OUTCOMES MATTER EQUALLY
 *
 * Exactly one match resolves. Zero matches and more than one match both stop
 * the action and ask the person to be specific — guessing between two people
 * called "Santos" is exactly the mistake that must never happen when the next
 * step is a delete. resolve() returns a result struct rather than throwing so
 * the caller can turn either failure into an ordinary chat reply.
 *
 * Employee lookups are scoped: a supervisor searching "Maria" only matches
 * within their own department. Someone they cannot see reads as not-found.
 *
 * That department rule is this class's own, and it is deliberately *wider* than
 * the reporting-line rule Controller::scopeToVisibleEmployees() now applies to
 * the web UI. The assistant is therefore not a way around review authority: a
 * name it resolves still has to survive the controller's own check, because
 * AiActionExecutor calls that controller rather than writing the row itself.
 * Narrowing this to the reporting line is a live option; it was left alone only
 * because the AI layer was out of scope for the authority change.
 */
final class AiEntityResolver
{
    /**
     * type => [table, pk column, [columns to search], label expression]
     *
     * The label is what gets echoed back in a confirmation prompt, so it has to
     * read like something a person would recognise.
     */
    private const LOOKUPS = [
        'cycle' => ['review_cycles', 'cycle_id', ['cycle_name'], 'cycle_name'],
        'competency' => ['competencies', 'competency_id', ['competency_name'], 'competency_name'],
        'course' => ['courses', 'course_id', ['title'], 'title'],
        'session' => ['training_sessions', 'session_id', ['title'], 'title'],
        'position' => ['critical_positions', 'position_id', ['position_title'], 'position_title'],
        'department' => ['departments', 'department_id', ['name', 'department_code'], 'name'],
    ];

    /**
     * Resolve one value of the given type.
     *
     * @return array{ok: bool, id?: string, label?: string, error?: string}
     */
    public function resolve(string $type, string $value, User $user): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['ok' => false, 'error' => 'no value given'];
        }

        return match ($type) {
            'employee' => $this->resolveEmployee($value, $user),
            'user' => $this->resolveUser($value),
            default => $this->resolveSimple($type, $value),
        };
    }

    /**
     * A value that is already a UUID is taken as-is. The planner is told to
     * pass names, but a person may well paste an id straight from a URL.
     */
    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    private function resolveEmployee(string $value, User $user): array
    {
        $query = DB::table('employees as e')
            ->select('e.employee_id', 'e.employee_code', 'e.first_name', 'e.last_name');

        // Row-level visibility: the same rule the employee list uses.
        $this->scopeEmployees($query, $user);

        if ($this->looksLikeUuid($value)) {
            $query->where('e.employee_id', $value);
        } else {
            $query->where(function ($q) use ($value) {
                $q->where('e.employee_code', $value)
                    ->orWhere('e.email', $value)
                    ->orWhereRaw("CONCAT(COALESCE(e.first_name,''),' ',COALESCE(e.last_name,'')) LIKE ?", ['%'.$value.'%'])
                    ->orWhere('e.last_name', 'like', '%'.$value.'%')
                    ->orWhere('e.first_name', 'like', '%'.$value.'%');
            });
        }

        // One extra row is enough to know the match was ambiguous.
        $rows = $query->limit(6)->get();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'error' => "no employee matching \"{$value}\" that you have access to"];
        }

        if ($rows->count() > 1) {
            $names = $rows->take(5)->map(fn ($r) => trim("{$r->first_name} {$r->last_name}")." ({$r->employee_code})");

            return ['ok' => false, 'error' => "\"{$value}\" matches more than one employee: ".$names->implode('; ').'. Which one?'];
        }

        $row = $rows->first();

        return [
            'ok' => true,
            'id' => $row->employee_id,
            'label' => trim("{$row->first_name} {$row->last_name}")." ({$row->employee_code})",
        ];
    }

    /**
     * Login accounts, for the user.* actions. Not scoped by department —
     * user management is admin-only at the route level, and an admin sees all.
     */
    private function resolveUser(string $value): array
    {
        $rows = DB::table('users')
            ->select('id', 'name', 'email')
            ->where(function ($q) use ($value) {
                $q->where('email', $value)
                    ->orWhere('name', 'like', '%'.$value.'%');
            })
            ->limit(6)
            ->get();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'error' => "no login account matching \"{$value}\""];
        }

        if ($rows->count() > 1) {
            $names = $rows->take(5)->map(fn ($r) => "{$r->name} <{$r->email}>");

            return ['ok' => false, 'error' => "\"{$value}\" matches more than one account: ".$names->implode('; ').'. Which one?'];
        }

        $row = $rows->first();

        return ['ok' => true, 'id' => (string) $row->id, 'label' => "{$row->name} <{$row->email}>"];
    }

    private function resolveSimple(string $type, string $value): array
    {
        if (! isset(self::LOOKUPS[$type])) {
            return ['ok' => false, 'error' => "cannot look up \"{$type}\""];
        }

        [$table, $pk, $searchCols, $labelCol] = self::LOOKUPS[$type];

        $query = DB::table($table)->select($pk, $labelCol);

        if ($this->looksLikeUuid($value)) {
            $query->where($pk, $value);
        } else {
            $query->where(function ($q) use ($searchCols, $value) {
                foreach ($searchCols as $i => $col) {
                    $i === 0
                        ? $q->where($col, 'like', '%'.$value.'%')
                        : $q->orWhere($col, 'like', '%'.$value.'%');
                }
            });
        }

        $rows = $query->limit(6)->get();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'error' => "no {$type} matching \"{$value}\""];
        }

        if ($rows->count() > 1) {
            // An exact, case-insensitive hit wins over the LIKE candidates —
            // "2027 Annual" should not be ambiguous just because "2027 Annual
            // Review (Draft)" also exists.
            $exact = $rows->filter(fn ($r) => strcasecmp((string) $r->{$labelCol}, $value) === 0);

            if ($exact->count() !== 1) {
                $labels = $rows->take(5)->map(fn ($r) => $r->{$labelCol});

                return ['ok' => false, 'error' => "\"{$value}\" matches more than one {$type}: ".$labels->implode('; ').'. Which one?'];
            }

            $rows = $exact;
        }

        $row = $rows->first();

        return ['ok' => true, 'id' => (string) $row->{$pk}, 'label' => (string) $row->{$labelCol}];
    }

    /**
     * Admin and HR resolve every record, a supervisor resolves their own
     * department, everyone else only their own row.
     *
     * This is a name-resolution scope, not an authority check, and it is not the
     * same rule as Controller::scopeToVisibleEmployees() — see the class
     * docblock. Resolving a name grants nothing; the controller invoked
     * afterwards applies the real rule.
     */
    private function scopeEmployees($query, User $user): void
    {
        if (in_array($user->role, ['admin', 'hr_manager'], true)) {
            return;
        }

        $employeeId = $user->employee_id;

        if ($user->role === 'supervisor' && $employeeId) {
            $deptId = DB::table('employees')->where('employee_id', $employeeId)->value('department_id');

            $query->where(function ($q) use ($deptId, $employeeId) {
                $q->where('e.department_id', $deptId)->orWhere('e.employee_id', $employeeId);
            });

            return;
        }

        // Staff, or an account with no linked profile: own record only.
        $query->where('e.employee_id', $employeeId ?? '-none-');
    }
}
