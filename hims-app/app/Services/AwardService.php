<?php

namespace App\Services;

use App\Support\AuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues formal awards, generates their certificates and runs the automatic
 * award rules. Every issued award gets a unique certificate code; the public
 * page /awards/verify/{code} is the shareable, printable certificate.
 */
class AwardService
{
    public const CATEGORIES = [
        'service' => 'Length of service',
        'excellence' => 'Clinical & safety excellence',
        'compassion' => 'Compassionate care',
        'performance' => 'Outstanding performance',
        'innovation' => 'Innovation & suggestions',
        'conduct' => 'Exemplary conduct',
        'teamwork' => 'Teamwork',
        'leadership' => 'Leadership & mentoring',
        'learning' => 'Learning & development',
    ];

    public const AUTO_RULES = [
        'service_years' => 'Years of service reached (from hire date)',
        'cpd_hours_year' => 'Qualified CPD hours in a calendar year',
    ];

    public function __construct(private NotificationService $notifications) {}

    /**
     * Issue one award. Returns the new award_id.
     */
    public function issue(
        string $awardTypeId,
        string $employeeId,
        ?string $citation = null,
        ?string $awardedOn = null,
        ?string $periodLabel = null,
        string $source = 'manual',
        ?string $awardedBy = null,
    ): string {
        $type = DB::table('award_types')->where('award_type_id', $awardTypeId)->first();
        abort_if(! $type, 404, 'Award type not found.');

        $id = (string) Str::uuid();
        $date = $awardedOn ? Carbon::parse($awardedOn) : now();

        DB::table('employee_awards')->insert([
            'award_id' => $id,
            'award_type_id' => $awardTypeId,
            'employee_id' => $employeeId,
            'citation' => $citation,
            'awarded_on' => $date->toDateString(),
            'period_label' => $periodLabel,
            'awarded_by' => $awardedBy,
            'source' => $source,
            'certificate_code' => $this->newCode($date),
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::record('issue_award', 'employee_awards', $id, afterState: [
            'award_type' => $type->name,
            'employee_id' => $employeeId,
            'source' => $source,
            'awarded_on' => $date->toDateString(),
        ]);

        $this->notifications->notify(
            $employeeId,
            'award_received',
            'You received the '.$type->name,
            'Your digital certificate and badge are ready to view, download and share.',
            'employee_awards',
            $id,
        );

        return $id;
    }

    public function revoke(string $awardId, string $reason): void
    {
        $award = DB::table('employee_awards')->where('award_id', $awardId)->first();
        abort_if(! $award, 404);

        DB::table('employee_awards')->where('award_id', $awardId)->update([
            'status' => 'revoked',
            'revoked_reason' => $reason,
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::record('revoke_award', 'employee_awards', $awardId,
            beforeState: ['status' => $award->status],
            afterState: ['status' => 'revoked', 'reason' => $reason]);
    }

    /** Awards with their type and badge, newest first. */
    public static function awardsFor(string $employeeId, bool $includeRevoked = false): Collection
    {
        return DB::table('employee_awards as ea')
            ->join('award_types as at', 'ea.award_type_id', '=', 'at.award_type_id')
            ->where('ea.employee_id', $employeeId)
            ->when(! $includeRevoked, fn ($q) => $q->where('ea.status', 'issued'))
            ->select('ea.*', 'at.name as award_name', 'at.category', 'at.badge_icon', 'at.badge_color', 'at.certificate_title', 'at.points_value')
            ->orderByDesc('ea.awarded_on')
            ->get();
    }

    /** Full record for the certificate page (by certificate code). */
    public function certificate(string $code): ?object
    {
        $award = DB::table('employee_awards as ea')
            ->join('award_types as at', 'ea.award_type_id', '=', 'at.award_type_id')
            ->join('employees as e', 'ea.employee_id', '=', 'e.employee_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->leftJoin('employees as by', 'ea.awarded_by', '=', 'by.employee_id')
            ->where('ea.certificate_code', $code)
            ->select('ea.*', 'at.name as award_name', 'at.category', 'at.description as award_description', 'at.badge_icon', 'at.badge_color',
                'at.certificate_title', 'at.certificate_body', 'at.signatory_name', 'at.signatory_title',
                'e.first_name', 'e.last_name', 'e.position_title', 'e.hire_date', 'd.name as department_name',
                DB::raw("CASE WHEN by.employee_id IS NULL THEN NULL ELSE CONCAT(by.first_name,' ',by.last_name) END as awarded_by_name"))
            ->first();

        if ($award) {
            $award->body_text = $this->renderBody($award);
        }

        return $award;
    }

    /** Fill the certificate template placeholders. */
    public function renderBody(object $award): string
    {
        $years = $award->hire_date ? (int) Carbon::parse($award->hire_date)->diffInYears(Carbon::parse($award->awarded_on)) : '';
        $cpd = '';
        if (str_contains((string) $award->certificate_body, '{cpd_hours}')) {
            $year = Carbon::parse($award->awarded_on)->year;
            $cpd = rtrim(rtrim(number_format((float) DB::table('cpd_records')->where('employee_id', $award->employee_id)
                ->where('verified', true)->whereYear('date_earned', $year)->sum('cpd_hours'), 1), '0'), '.');
        }

        $text = strtr((string) ($award->certificate_body ?: 'In recognition of an outstanding contribution to the hospital. {citation}'), [
            '{name}' => trim($award->first_name.' '.$award->last_name),
            '{award}' => $award->award_name,
            '{years}' => (string) $years,
            '{date}' => Carbon::parse($award->awarded_on)->format('F j, Y'),
            '{period}' => $award->period_label ?: (string) Carbon::parse($award->awarded_on)->year,
            '{citation}' => (string) ($award->citation ?? ''),
            '{cpd_hours}' => $cpd,
        ]);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Apply every active automatic rule. Each (rule, employee) is awarded once
     * — service milestones once per milestone, CPD once per calendar year.
     *
     * @return array<string, int> awards issued per type name
     */
    public function runAutomaticAwards(): array
    {
        $issued = [];
        $types = DB::table('award_types')->where('is_active', true)->whereNotNull('auto_rule')->get();
        $employees = DB::table('employees')->whereIn('employment_status', ['active', 'probationary', 'on_leave'])->get(['employee_id', 'hire_date']);

        foreach ($types as $type) {
            $count = 0;

            if ($type->auto_rule === 'service_years' && $type->auto_value > 0) {
                foreach ($employees as $e) {
                    if (! $e->hire_date) {
                        continue;
                    }
                    $milestone = Carbon::parse($e->hire_date)->addYears((int) $type->auto_value);
                    if ($milestone->isFuture()) {
                        continue;
                    }
                    $already = DB::table('employee_awards')->where('award_type_id', $type->award_type_id)
                        ->where('employee_id', $e->employee_id)->exists();
                    if (! $already) {
                        $this->issue($type->award_type_id, $e->employee_id, null, $milestone->toDateString(), null, 'auto');
                        $count++;
                    }
                }
            }

            if ($type->auto_rule === 'cpd_hours_year' && $type->auto_value > 0) {
                $year = now()->year;
                $qualifiedHours = DB::table('cpd_records')->where('verified', true)->whereYear('date_earned', $year)
                    ->groupBy('employee_id')->selectRaw('employee_id, SUM(cpd_hours) as hours')->pluck('hours', 'employee_id');

                foreach ($qualifiedHours as $employeeId => $hours) {
                    if ((float) $hours < (float) $type->auto_value || ! $employees->firstWhere('employee_id', $employeeId)) {
                        continue;
                    }
                    $already = DB::table('employee_awards')->where('award_type_id', $type->award_type_id)
                        ->where('employee_id', $employeeId)->where('period_label', (string) $year)->exists();
                    if (! $already) {
                        $this->issue($type->award_type_id, $employeeId, null, now()->toDateString(), (string) $year, 'auto');
                        $count++;
                    }
                }
            }

            if ($count) {
                $issued[$type->name] = $count;
            }
        }

        return $issued;
    }

    private function newCode(Carbon $date): string
    {
        do {
            $code = 'HR3-AW-'.$date->format('Y').'-'.strtoupper(Str::random(8));
        } while (DB::table('employee_awards')->where('certificate_code', $code)->exists());

        return $code;
    }
}
