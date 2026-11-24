<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is this succession candidate QUALIFIED to be promoted into the position?
 *
 * Each check is evidence from HIMS, not a judgement typed into a form:
 *   1. Nomination approved by HR
 *   2. Readiness assessed as "ready now"
 *   3. Latest completed performance review ≥ the position's minimum score
 *   4. No open performance improvement plan
 *   5. No critical competency gaps against the TARGET role's requirements
 *   6. Minimum years of service (if the position sets one)
 *   7. Required licences/credentials held and not expired, and no expired
 *      credential of any kind
 *   8. No overdue mandatory training
 *
 * Salary is not handled in HR3 — pay changes follow in HR1 after promotion.
 */
class SuccessionQualificationService
{
    public const DEFAULT_MIN_SCORE = 3.5;

    public function __construct(private CompetencyGapAnalysisService $gaps) {}

    /**
     * @return array{qualified: bool, passed: int, total: int, checks: array<int, array>}
     */
    public function evaluate(object $candidate, object $position): array
    {
        $employeeId = $candidate->employee_id;
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();
        $checks = [];

        // 1. Nomination approved
        $checks[] = $this->check('approved', 'Nomination approved by HR',
            in_array($candidate->status, ['approved', 'promoted'], true),
            'Status: '.ucfirst((string) $candidate->status).'.');

        // 2. Readiness
        $readiness = (string) $candidate->readiness_level;
        $checks[] = $this->check('readiness', 'Assessed as ready now',
            $readiness === 'ready_now',
            'Readiness: '.str_replace('_', ' ', $readiness ?: 'not set').'.');

        // 3. Performance
        $minScore = (float) ($position->min_performance_score ?? 0) ?: self::DEFAULT_MIN_SCORE;
        $review = DB::table('performance_reviews')->where('employee_id', $employeeId)
            ->whereNotNull('overall_score')->whereIn('status', ['completed', 'finished', 'approved', 'acknowledged'])
            ->orderByDesc('updated_at')->first();
        $checks[] = $this->check('performance', 'Latest performance score ≥ '.number_format($minScore, 2),
            $review ? (float) $review->overall_score >= $minScore : false,
            $review ? 'Latest completed review: '.number_format((float) $review->overall_score, 2).' / 5.00.' : 'No completed performance review on record.');

        // 4. No open PIP
        $openPip = Schema::hasTable('performance_improvement_plans')
            ? DB::table('performance_improvement_plans')->where('employee_id', $employeeId)
                ->whereNotIn('status', ['completed', 'closed', 'cancelled', 'successful'])->exists()
            : false;
        $checks[] = $this->check('pip', 'No open performance improvement plan', ! $openPip,
            $openPip ? 'An improvement plan is still open.' : 'None open.');

        // 5. Competencies against the target role
        $roleId = $position->target_role_id ?? null;
        if ($roleId) {
            $analysis = $this->gaps->analyseEmployee($employeeId, false, $roleId);
            $critical = collect($analysis['gaps'] ?? [])->where('severity', 'critical');
            $roleName = DB::table('roles')->where('role_id', $roleId)->value('role_name');
            $checks[] = $this->check('competency', 'No critical competency gaps for '.($roleName ?: 'the target role'),
                ($analysis['summary']['competencies_required'] ?? 0) > 0 && $critical->isEmpty(),
                ($analysis['summary']['competencies_required'] ?? 0) === 0
                    ? 'The target role has no competency requirements defined yet.'
                    : 'Readiness '.$analysis['summary']['readiness_pct'].'%, coverage '.$analysis['summary']['proficiency_pct'].'%. '
                        .($critical->isEmpty() ? 'No critical gaps.' : 'Critical gaps: '.$critical->pluck('competency_name')->take(4)->implode(', ').($critical->count() > 4 ? '…' : '').'.'),
                ['gaps_url' => route('competency.gap.employee', $employeeId)]);
        } else {
            $checks[] = $this->check('competency', 'Competencies for the target role', null,
                'Set the position\'s target job role to check competencies against it.');
        }

        // 6. Years of service
        $minYears = (int) ($position->min_years_service ?? 0);
        $years = $employee?->hire_date ? Carbon::parse($employee->hire_date)->floatDiffInYears(now()) : 0;
        if ($minYears > 0) {
            $checks[] = $this->check('service', "At least {$minYears} year(s) of service", $years >= $minYears,
                'Service: '.number_format($years, 1).' years (hired '.($employee?->hire_date ? Carbon::parse($employee->hire_date)->format('M Y') : 'unknown').').');
        }

        // 7. Credentials
        $credentials = DB::table('employee_credentials')->where('employee_id', $employeeId)->get();
        $expired = $credentials->filter(fn ($c) => $c->expiry_date && Carbon::parse($c->expiry_date)->isPast());
        $required = collect(json_decode((string) ($position->required_credentials ?? '[]'), true) ?: [])->filter()->values();
        $missing = $required->filter(function ($type) use ($credentials) {
            return ! $credentials->contains(fn ($c) => strcasecmp(trim($c->credential_type), trim($type)) === 0
                && (! $c->expiry_date || Carbon::parse($c->expiry_date)->isFuture()));
        });
        $checks[] = $this->check('credentials', $required->isEmpty() ? 'No expired licences or credentials' : 'Required credentials valid: '.$required->implode(', '),
            $expired->isEmpty() && $missing->isEmpty(),
            collect([
                $missing->isNotEmpty() ? 'Missing or expired: '.$missing->implode(', ').'.' : null,
                $expired->isNotEmpty() ? 'Expired: '.$expired->pluck('credential_type')->implode(', ').'.' : null,
            ])->filter()->implode(' ') ?: 'All credentials current.');

        // 8. Mandatory training
        $overdue = DB::table('course_enrollments')->where('employee_id', $employeeId)->whereNotNull('assignment_id')
            ->where('status', '!=', 'completed')->whereNotNull('due_date')->where('due_date', '<', now()->toDateString())->count();
        $checks[] = $this->check('training', 'No overdue mandatory training', $overdue === 0,
            $overdue ? "{$overdue} mandatory course(s) past their due date." : 'Up to date.');

        $scored = collect($checks)->whereNotNull('passed');

        return [
            'qualified' => $scored->every(fn ($c) => $c['passed'] === true) && collect($checks)->whereNull('passed')->isEmpty(),
            'passed' => $scored->where('passed', true)->count(),
            'total' => count($checks),
            'checks' => $checks,
        ];
    }

    private function check(string $key, string $label, ?bool $passed, string $detail, array $extra = []): array
    {
        return array_merge(['key' => $key, 'label' => $label, 'passed' => $passed, 'detail' => $detail], $extra);
    }
}
