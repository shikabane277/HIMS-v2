<?php

namespace App\Services;

use App\Support\CredentialStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Renewal cycles: what CPD an employee actually owes, and whether they are on
 * track to have it before the credential lapses.
 *
 * The Learning tab used to show a flat lifetime CPD total, which cannot answer
 * the only question that matters at renewal — "does this person have enough
 * hours *in this cycle*". A nurse with 200 lifetime hours and 3 in the current
 * three-year window is a compliance failure the old number reported as a
 * success.
 *
 * Attained hours are summed from `cpd_records` on every read rather than being
 * stored on the cycle. CPD gets verified days or weeks after it is logged, so a
 * stored total is stale the moment a verification lands, and a recount job is
 * one more thing to run and get wrong.
 *
 * Nothing here needs a `users` row. Cycles hang off `employee_id`.
 */
class RenewalCycleService
{
    /** A cycle is "at risk" when the run-rate needed from here on exceeds this multiple of the pace so far. */
    private const PACE_FACTOR = 1.5;

    /**
     * Ensure every active rule has an open cycle for the given employee,
     * creating the ones that are missing and rolling over the ones that ended.
     *
     * Credential rules key their window to the credential's own expiry, so the
     * cycle ends when the licence does. CPD rules with no credential run from
     * the hire date, since that is the only per-employee anchor available.
     *
     * @return int cycles opened
     */
    public function syncCycles(string $employeeId): int
    {
        $rules = DB::table('renewal_rules')->where('is_active', true)->get();
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();

        if (! $employee || $rules->isEmpty()) {
            return 0;
        }

        $opened = 0;

        foreach ($rules as $rule) {
            $credential = $rule->subject_type === 'credential'
                ? DB::table('employee_credentials')
                    ->where('employee_id', $employeeId)
                    ->where('credential_type', $rule->subject_key)
                    ->orderByDesc('expiry_date')
                    ->first()
                : null;

            // A credential rule only applies to someone who holds that credential.
            if ($rule->subject_type === 'credential' && ! $credential) {
                continue;
            }

            $end = $credential?->expiry_date
                ? Carbon::parse($credential->expiry_date)
                : $this->projectedEnd($employee->hire_date, $rule->cycle_months);

            $start = $end->copy()->subMonths($rule->cycle_months);

            $exists = DB::table('employee_renewal_cycles')
                ->where('employee_id', $employeeId)
                ->where('rule_id', $rule->rule_id)
                ->where('cycle_start', $start->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('employee_renewal_cycles')->insert([
                'cycle_id' => (string) Str::uuid(),
                'employee_id' => $employeeId,
                'rule_id' => $rule->rule_id,
                'cycle_start' => $start->toDateString(),
                'cycle_end' => $end->toDateString(),
                'hours_required_snapshot' => $rule->required_hours,
                'credential_id' => $credential->credential_id ?? null,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $opened++;
        }

        return $opened;
    }

    /**
     * Roll the cycle window forward from the hire date until it covers today,
     * so a five-year employee on a 36-month cycle lands in their second window
     * rather than one that closed two years ago.
     */
    private function projectedEnd(?string $hireDate, int $cycleMonths): Carbon
    {
        $months = max(1, $cycleMonths);
        $end = Carbon::parse($hireDate ?: now())->addMonths($months);

        while ($end->isPast()) {
            $end->addMonths($months);
        }

        return $end;
    }

    /**
     * One employee's cycles, each with hours attained, remaining, and risk.
     */
    public function cyclesFor(string $employeeId): Collection
    {
        return DB::table('employee_renewal_cycles as erc')
            ->join('renewal_rules as rr', 'rr.rule_id', '=', 'erc.rule_id')
            ->leftJoin('employee_credentials as ec', 'ec.credential_id', '=', 'erc.credential_id')
            ->where('erc.employee_id', $employeeId)
            ->whereIn('erc.status', ['open', 'met', 'shortfall'])
            ->orderBy('erc.cycle_end')
            ->select([
                'erc.cycle_id',
                'erc.employee_id',
                'erc.cycle_start',
                'erc.cycle_end',
                'erc.hours_required_snapshot',
                'erc.status',
                'erc.credential_id',
                'rr.label',
                'rr.subject_type',
                'rr.subject_key',
                'rr.grace_days',
                'ec.expiry_date',
                'ec.credential_number',
            ])
            ->get()
            ->map(fn ($cycle) => $this->decorate($cycle));
    }

    /**
     * Attach attained/remaining/risk to a cycle row.
     */
    public function decorate(object $cycle): object
    {
        $cycle->hours_attained = $this->attainedHours(
            $cycle->employee_id,
            $cycle->cycle_start,
            $cycle->cycle_end,
        );

        $required = (float) $cycle->hours_required_snapshot;

        // Cast both: min()/max() return the int literal when they clamp and a
        // float when they do not, so without this the same field is sometimes
        // int and sometimes float depending on the data.
        $cycle->hours_remaining = (float) max(0, round($required - $cycle->hours_attained, 1));
        $cycle->pct_complete = $required > 0
            ? (float) min(100, round($cycle->hours_attained / $required * 100, 1))
            : 100.0;

        $cycle->days_left = CredentialStatus::daysRemaining($cycle->cycle_end) ?? 0;
        $cycle->risk = $this->riskLevel($cycle);
        $cycle->credential_status = isset($cycle->expiry_date)
            ? CredentialStatus::of($cycle->expiry_date)
            : null;

        return $cycle;
    }

    /**
     * Verified CPD hours dated inside the window.
     *
     * Unverified hours are excluded on purpose: an employee cannot clear a
     * compliance requirement by typing a number into a form.
     */
    public function attainedHours(string $employeeId, string $start, string $end): float
    {
        return (float) DB::table('cpd_records')
            ->where('employee_id', $employeeId)
            ->where('verified', true)
            ->whereBetween('date_earned', [$start, $end])
            ->sum('cpd_hours');
    }

    /**
     * How badly a cycle is tracking.
     *
     *   met       — the hours are already in
     *   shortfall — the window has closed without them
     *   at_risk   — finishing now needs a run-rate PACE_FACTOR times the pace so
     *               far, or the window ends within the credential warning window
     *               with hours still owed
     *   on_track  — everything else
     */
    public function riskLevel(object $cycle): string
    {
        if ($cycle->hours_remaining <= 0) {
            return 'met';
        }

        if ($cycle->days_left < 0) {
            return 'shortfall';
        }

        if ($cycle->days_left <= CredentialStatus::WINDOW_DAYS) {
            return 'at_risk';
        }

        $start = Carbon::parse($cycle->cycle_start);
        $end = Carbon::parse($cycle->cycle_end);
        $elapsed = max(1, $start->diffInDays(CredentialStatus::today()));
        $total = max(1, $start->diffInDays($end));

        $paceSoFar = $cycle->hours_attained / $elapsed;
        $paceNeeded = $cycle->hours_remaining / max(1, $cycle->days_left);

        // Someone with zero hours and most of the window gone is at risk even
        // though their pace-so-far is 0 and the ratio is undefined.
        if ($paceSoFar <= 0) {
            return $elapsed / $total >= 0.5 ? 'at_risk' : 'on_track';
        }

        return $paceNeeded > $paceSoFar * self::PACE_FACTOR ? 'at_risk' : 'on_track';
    }

    /**
     * Everyone heading for a shortfall — the oversight list.
     *
     * `$scope` is a callable so the caller can hand in
     * `Controller::scopeToVisibleEmployees()`: a supervisor sees their own
     * department's risk, HR sees the hospital's.
     *
     * @param  callable|null  $scope  fn($query) => $query
     */
    public function atRisk(?callable $scope = null, ?string $departmentId = null): Collection
    {
        $query = DB::table('employee_renewal_cycles as erc')
            ->join('renewal_rules as rr', 'rr.rule_id', '=', 'erc.rule_id')
            ->join('employees as e', 'e.employee_id', '=', 'erc.employee_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->leftJoin('employee_credentials as ec', 'ec.credential_id', '=', 'erc.credential_id')
            ->where('erc.status', 'open')
            ->where('e.employment_status', 'active')
            ->select([
                'erc.cycle_id',
                'erc.employee_id',
                'erc.cycle_start',
                'erc.cycle_end',
                'erc.hours_required_snapshot',
                'erc.status',
                'erc.credential_id',
                'e.employee_code',
                'e.first_name',
                'e.last_name',
                'e.email',
                'e.supervisor_id',
                'd.name as department_name',
                'rr.label',
                'rr.subject_type',
                'rr.subject_key',
                'rr.grace_days',
                'ec.expiry_date',
                'ec.credential_number',
            ]);

        if ($departmentId) {
            $query->where('e.department_id', $departmentId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->orderBy('erc.cycle_end')
            ->get()
            ->map(fn ($cycle) => $this->decorate($cycle))
            ->filter(fn ($cycle) => in_array($cycle->risk, ['at_risk', 'shortfall'], true))
            ->values();
    }

    /**
     * Close out cycles whose window has passed, stamping the outcome so the
     * history shows whether each one was met. Called by the nightly scan.
     *
     * @return array{met: int, shortfall: int}
     */
    public function settleExpiredCycles(): array
    {
        $due = DB::table('employee_renewal_cycles')
            ->where('status', 'open')
            ->where('cycle_end', '<', CredentialStatus::today())
            ->get();

        $met = $shortfall = 0;

        foreach ($due as $cycle) {
            $attained = $this->attainedHours($cycle->employee_id, $cycle->cycle_start, $cycle->cycle_end);
            $status = $attained >= (float) $cycle->hours_required_snapshot ? 'met' : 'shortfall';

            DB::table('employee_renewal_cycles')
                ->where('cycle_id', $cycle->cycle_id)
                ->update(['status' => $status, 'updated_at' => now()]);

            $status === 'met' ? $met++ : $shortfall++;
        }

        return ['met' => $met, 'shortfall' => $shortfall];
    }
}
