<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\RenewalCycleService;
use App\Support\CredentialStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Nightly sweep for credentials that have crossed into the warning window or
 * lapsed entirely, competency assessments due for reassessment, and CPD renewal
 * cycles heading for a shortfall.
 *
 * Alerting on expiry is the whole point of holding expiry dates: a nurse whose
 * BLS card lapsed is a rostering problem, not a reporting one. Until now the
 * dates were only ever rendered on a dashboard nobody has a reason to open
 * daily.
 *
 * Deduplication uses `credential_alert_log`, which was migrated at the start
 * and never written to. One row per (credential, alert_type) means a credential
 * generates at most two alerts in its life — one when it enters the 30-day
 * window, one when it lapses — instead of the same warning every night for a
 * month. Re-dating a renewed credential clears its log rows so the next cycle
 * alerts again.
 *
 * Email is opt-in via CREDENTIAL_ALERT_EMAIL. In-app notifications and the
 * alert log are always written, so turning email off loses no record.
 *
 * ESCALATION AND THE NO-ACCOUNT FALLBACK
 *
 * In-app notifications are keyed on `employee_id`, so an employee tracked by
 * proxy receives them — but with no login they have no way to read one. Every
 * alert therefore also emails any escalation recipient with no `users` row,
 * using the address on their `employees` record. That makes email the only
 * channel that reaches an unlinked supervisor, which is exactly the case where
 * a lapse would otherwise go unnoticed.
 */
class ScanCredentialExpiry extends Command
{
    protected $signature = 'hims:scan-credential-expiry
                            {--dry-run : Report what would be sent without writing or emailing}';

    protected $description = 'Notify employees and supervisors about expiring/expired credentials and due reassessments';

    public function __construct(
        private NotificationService $notifications,
        private RenewalCycleService $cycles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — nothing will be written or emailed.');
        }

        $this->line('Window: credentials expiring on or before '.CredentialStatus::windowEnd().
                    ' (today is '.CredentialStatus::today().')');

        // Close out windows that have ended before looking for risk, so a cycle
        // that quietly ran out yesterday is reported as a shortfall rather than
        // still counted as open.
        if (! $dryRun) {
            $settled = $this->cycles->settleExpiredCycles();

            if ($settled['met'] || $settled['shortfall']) {
                $this->line("Settled {$settled['met']} met and {$settled['shortfall']} shortfall cycle(s).");
            }
        }

        $sent = $this->scanCredentials($dryRun)
              + $this->scanReassessments($dryRun)
              + $this->scanRenewalCycles($dryRun);

        $this->newLine();
        $this->info($dryRun
            ? "Dry run complete — {$sent} alert(s) would be raised."
            : "Done — {$sent} alert(s) raised.");

        return self::SUCCESS;
    }

    /**
     * Credentials that are expiring soon or already expired.
     */
    private function scanCredentials(bool $dryRun): int
    {
        // Anything not yet "active" and not open-ended. The status column keeps
        // this in step with what the dashboard shows for the same credential.
        $due = DB::table('employee_credentials as ec')
            ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
            ->whereNotNull('ec.expiry_date')
            ->whereDate('ec.expiry_date', '<=', CredentialStatus::windowEnd())
            ->where('e.employment_status', 'active')
            ->select('ec.credential_id', 'ec.employee_id', 'ec.credential_type', 'ec.expiry_date',
                'e.first_name', 'e.last_name', 'e.email')
            ->selectRaw(CredentialStatus::caseSql('ec.expiry_date').' as status', CredentialStatus::caseBindings())
            ->orderBy('ec.expiry_date')
            ->get();

        if ($due->isEmpty()) {
            $this->line('No credentials in the alert window.');

            return 0;
        }

        // One query instead of one per credential.
        $alreadySent = DB::table('credential_alert_log')
            ->whereIn('credential_id', $due->pluck('credential_id')->all())
            ->get()
            ->map(fn ($row) => $row->credential_id.'|'.$row->alert_type)
            ->flip();

        $count = 0;

        foreach ($due as $credential) {
            if ($alreadySent->has($credential->credential_id.'|'.$credential->status)) {
                continue;
            }

            $name = trim($credential->first_name.' '.$credential->last_name);
            $days = CredentialStatus::daysRemaining($credential->expiry_date);

            [$title, $message] = $credential->status === CredentialStatus::EXPIRED
                ? [
                    "{$credential->credential_type} has expired",
                    "{$name}'s {$credential->credential_type} expired on {$credential->expiry_date} (".abs($days).' day(s) ago). Renewal is required before further clinical assignment.',
                ]
                : [
                    "{$credential->credential_type} expires in {$days} day(s)",
                    "{$name}'s {$credential->credential_type} expires on {$credential->expiry_date}. Please start the renewal process.",
                ];

            $recipients = $this->notifications->escalationChain($credential->employee_id);

            $this->line(sprintf(
                '  [%s] %-28s %-22s %s  → %d recipient(s)',
                str_pad($credential->status, 13),
                Str::limit($name, 26),
                Str::limit($credential->credential_type, 20),
                $credential->expiry_date,
                count($recipients),
            ));

            if ($dryRun) {
                $count++;

                continue;
            }

            $this->notifications->notifyMany(
                $recipients,
                'credential_'.$credential->status,
                $title,
                $message,
                'employee_credentials',
                $credential->credential_id,
            );

            DB::table('credential_alert_log')->insert([
                'alert_id' => (string) Str::uuid(),
                'subject_type' => 'credential',
                'subject_id' => $credential->credential_id,
                'credential_id' => $credential->credential_id,
                'employee_id' => $credential->employee_id,
                'alert_type' => $credential->status,
                'sent_to' => json_encode($recipients),
                'sent_at' => now(),
            ]);

            $this->emailAlert($credential->email, $recipients, $title, $message);

            $count++;
        }

        return $count;
    }

    /**
     * Competency assessments whose next_assessment_due has arrived.
     *
     * next_assessment_due has been written on every assessment since the module
     * shipped (defaulting to a year out) but nothing ever read it back.
     */
    private function scanReassessments(bool $dryRun): int
    {
        $due = DB::table('competency_assessments as ca')
            ->join('employees as e', 'ca.employee_id', '=', 'e.employee_id')
            ->join('competencies as c', 'ca.competency_id', '=', 'c.competency_id')
            ->whereNotNull('ca.next_assessment_due')
            ->whereDate('ca.next_assessment_due', '<=', CredentialStatus::windowEnd())
            ->where('e.employment_status', 'active')
            ->select('ca.assessment_id', 'ca.employee_id', 'ca.next_assessment_due',
                'c.competency_name', 'e.first_name', 'e.last_name', 'e.email')
            ->orderBy('ca.next_assessment_due')
            ->get();

        if ($due->isEmpty()) {
            $this->line('No competency reassessments due.');

            return 0;
        }

        // Reassessments have no alert log of their own, so dedupe against the
        // notifications already raised for the same assessment.
        $alreadyNotified = DB::table('notifications')
            ->where('reference_type', 'competency_assessments')
            ->whereIn('reference_id', $due->pluck('assessment_id')->all())
            ->pluck('reference_id')
            ->flip();

        $count = 0;

        foreach ($due as $assessment) {
            if ($alreadyNotified->has($assessment->assessment_id)) {
                continue;
            }

            $name = trim($assessment->first_name.' '.$assessment->last_name);
            $days = CredentialStatus::daysRemaining($assessment->next_assessment_due);

            $title = $days < 0
                ? "{$assessment->competency_name} reassessment overdue"
                : "{$assessment->competency_name} reassessment due in {$days} day(s)";

            $message = "{$name} is due for reassessment on {$assessment->competency_name} "
                     ."(scheduled {$assessment->next_assessment_due}).";

            $recipients = $this->notifications->escalationChain($assessment->employee_id);

            $this->line(sprintf(
                '  [reassessment] %-28s %-22s %s  → %d recipient(s)',
                Str::limit($name, 26),
                Str::limit($assessment->competency_name, 20),
                $assessment->next_assessment_due,
                count($recipients),
            ));

            if (! $dryRun) {
                $this->notifications->notifyMany(
                    $recipients,
                    'competency_reassessment_due',
                    $title,
                    $message,
                    'competency_assessments',
                    $assessment->assessment_id,
                );

                $this->emailAlert($assessment->email, $recipients, $title, $message);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Renewal cycles heading for — or already in — a shortfall.
     *
     * This is the alert the flat lifetime CPD total could never raise: hours are
     * counted inside the cycle window, so someone with a long history and a
     * quiet three years is flagged while there is still time to fix it.
     *
     * Dedupe reuses `credential_alert_log` with `subject_type = 'renewal_cycle'`
     * and a null `credential_id` for CPD rules that have no credential attached.
     * One ledger keeps one dedupe path; a parallel table would drift from this
     * one the first time either changed.
     */
    private function scanRenewalCycles(bool $dryRun): int
    {
        $due = $this->cycles->atRisk();

        if ($due->isEmpty()) {
            $this->line('No renewal cycles at risk.');

            return 0;
        }

        $alreadySent = DB::table('credential_alert_log')
            ->where('subject_type', 'renewal_cycle')
            ->whereIn('subject_id', $due->pluck('cycle_id')->all())
            ->get()
            ->map(fn ($row) => $row->subject_id.'|'.$row->alert_type)
            ->flip();

        $count = 0;

        foreach ($due as $cycle) {
            $alertType = 'cycle_'.$cycle->risk;   // cycle_at_risk | cycle_shortfall

            if ($alreadySent->has($cycle->cycle_id.'|'.$alertType)) {
                continue;
            }

            $name = trim($cycle->first_name.' '.$cycle->last_name);
            $owed = $cycle->hours_remaining;

            [$title, $message] = $cycle->risk === 'shortfall'
                ? [
                    Str::limit("{$cycle->label} cycle closed short by {$owed} hour(s)", 290),
                    "{$name} ended the {$cycle->label} cycle on {$cycle->cycle_end} with "
                    ."{$cycle->hours_attained} of {$cycle->hours_required_snapshot} required hour(s) — "
                    ."short by {$owed}. Renewal may be refused until the deficit is cleared.",
                ]
                : [
                    Str::limit("{$cycle->label}: {$owed} hour(s) still needed", 290),
                    "{$name} has {$cycle->hours_attained} of {$cycle->hours_required_snapshot} required "
                    ."hour(s) for {$cycle->label}, with {$cycle->days_left} day(s) left in the cycle "
                    ."(ends {$cycle->cycle_end}). At the current pace the requirement will not be met.",
                ];

            $recipients = $this->notifications->escalationChain($cycle->employee_id);

            $this->line(sprintf(
                '  [%s] %-28s %-22s %s  → %d recipient(s)',
                str_pad($cycle->risk, 13),
                Str::limit($name, 26),
                Str::limit($cycle->label, 20),
                $cycle->cycle_end,
                count($recipients),
            ));

            if ($dryRun) {
                $count++;

                continue;
            }

            $this->notifications->notifyMany(
                $recipients,
                $alertType,
                $title,
                $message,
                'employee_renewal_cycles',
                $cycle->cycle_id,
            );

            DB::table('credential_alert_log')->insert([
                'alert_id' => (string) Str::uuid(),
                'subject_type' => 'renewal_cycle',
                'subject_id' => $cycle->cycle_id,
                // Null for a CPD rule with no credential behind it.
                'credential_id' => $cycle->credential_id,
                'employee_id' => $cycle->employee_id,
                'alert_type' => $alertType,
                'sent_to' => json_encode($recipients),
                'sent_at' => now(),
            ]);

            $this->emailAlert($cycle->email, $recipients, $title, $message);

            $count++;
        }

        return $count;
    }

    /**
     * Email the alert: the subject employee, plus any escalation recipient who
     * has no login account.
     *
     * The recipients all get an in-app notification regardless, but nobody
     * without an account can open the bell to read one — so for them email is
     * not a duplicate, it is the only delivery. Addresses are deduped, so an
     * unlinked subject is not mailed twice.
     *
     * @param  array<int, string>  $recipientEmployeeIds
     */
    private function emailAlert(?string $subjectAddress, array $recipientEmployeeIds, string $subject, string $body): void
    {
        if (! config('hims.credential_alert_email')) {
            return;
        }

        $addresses = $recipientEmployeeIds
            ? DB::table('employees as e')
                ->leftJoin('users as u', 'u.employee_id', '=', 'e.employee_id')
                ->whereIn('e.employee_id', $recipientEmployeeIds)
                ->whereNull('u.id')
                ->whereNotNull('e.email')
                ->pluck('e.email')
                ->all()
            : [];

        if ($subjectAddress) {
            array_unshift($addresses, $subjectAddress);
        }

        foreach (array_unique($addresses) as $address) {
            $this->emailIfEnabled($address, $subject, $body);
        }
    }

    /**
     * Send the alert by email when CREDENTIAL_ALERT_EMAIL is on.
     *
     * A failed send must not abort the sweep — the in-app notification and the
     * log row are already committed, so the alert is not lost.
     */
    private function emailIfEnabled(?string $address, string $subject, string $body): void
    {
        if (! config('hims.credential_alert_email') || ! $address) {
            return;
        }

        try {
            Mail::raw($body, function ($mail) use ($address, $subject) {
                $mail->to($address)->subject('[HIMS] '.$subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Credential alert email failed', [
                'to' => $address,
                'error' => $e->getMessage(),
            ]);
            $this->warn('    email failed: '.$e->getMessage());
        }
    }
}
