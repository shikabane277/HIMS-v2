<?php

namespace App\Console\Commands;

use App\Mail\SupervisorDigestMail;
use App\Support\AuditTrail;
use App\Support\CredentialStatus;
use App\Support\CycleStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSupervisorDigest extends Command
{
    protected $signature = 'hims:send-supervisor-digest {--supervisor= : Specific supervisor email to target} {--dry-run : Generate digest data and print summary without sending mail}';

    protected $description = 'Send weekly email digest of pending reviews, expiring credentials, and upcoming training to department supervisors';

    public function handle(): int
    {
        $this->info('Compiling supervisor digests...');

        $query = DB::table('users as u')
            ->leftJoin('employees as e', 'u.employee_id', '=', 'e.employee_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where(function ($q) {
                $q->where('u.role', 'supervisor')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('departments')
                            ->whereColumn('departments.head_employee_id', 'u.employee_id');
                    });
            })
            ->where('u.is_active', true)
            ->select('u.id as user_id', 'u.name as user_name', 'u.email as user_email', 'u.role',
                'e.employee_id', 'e.first_name', 'e.last_name', 'd.department_id', 'd.name as department_name');

        if ($targetEmail = $this->option('supervisor')) {
            $query->where('u.email', $targetEmail);
        }

        $supervisors = $query->get();

        if ($supervisors->isEmpty()) {
            $this->warn('No active supervisors or department heads found.');

            return self::SUCCESS;
        }

        $sentCount = 0;
        $dryRun = $this->option('dry-run');

        foreach ($supervisors as $sup) {
            $this->line("Processing digest for: {$sup->user_name} ({$sup->user_email})...");

            // Collect direct reports
            $directReports = DB::table('employees')
                ->where('employment_status', 'active')
                ->where(function ($q) use ($sup) {
                    if ($sup->employee_id) {
                        $q->where('supervisor_id', $sup->employee_id);
                    }
                    if ($sup->department_id) {
                        $q->orWhere('department_id', $sup->department_id);
                    }
                })
                ->select('employee_id', 'first_name', 'last_name', 'position_title', 'department_id')
                ->get();

            $reportIds = $directReports->pluck('employee_id')->filter()->toArray();

            if (empty($reportIds)) {
                $this->line(' - No team members assigned, skipping digest.');

                continue;
            }

            // Pending reviews
            $pendingReviews = CycleStatus::whereNotEnded(
                DB::table('performance_reviews as pr')
                    ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
                    ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
                    ->whereIn('pr.employee_id', $reportIds)
                    ->whereIn('pr.status', ['draft', 'scoring', 'acknowledged']),
                'rc.end_date'
            )->select('pr.review_id', 'pr.status', 'e.first_name', 'e.last_name', 'rc.cycle_name')
                ->limit(10)
                ->get();

            // Expiring credentials (next 30 days)
            $expiringCredentials = CredentialStatus::whereExpiring(
                DB::table('employee_credentials as ec')
                    ->join('employees as e', 'ec.employee_id', '=', 'e.employee_id')
                    ->whereIn('ec.employee_id', $reportIds),
                'ec.expiry_date'
            )->select('e.first_name', 'e.last_name', 'ec.credential_type', 'ec.expiry_date',
                DB::raw('DATEDIFF(ec.expiry_date, CURRENT_DATE) as days_until'))
                ->orderBy('ec.expiry_date')
                ->limit(10)
                ->get();

            // Upcoming training sessions in next 14 days
            $upcomingSessions = DB::table('training_sessions as ts')
                ->leftJoin('training_venues as tv', 'ts.venue_id', '=', 'tv.venue_id')
                ->where('ts.status', 'scheduled')
                ->whereBetween('ts.session_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
                ->select('ts.title', 'ts.session_date', 'ts.start_time', 'tv.venue_name')
                ->orderBy('ts.session_date')
                ->limit(5)
                ->get();

            $digestData = [
                'supervisor_name' => $sup->first_name ? ($sup->first_name.' '.$sup->last_name) : $sup->user_name,
                'department_name' => $sup->department_name ?? 'Clinical Team',
                'direct_reports' => $directReports,
                'pending_reviews' => $pendingReviews,
                'expiring_credentials' => $expiringCredentials,
                'upcoming_sessions' => $upcomingSessions,
            ];

            if ($dryRun) {
                $this->info(" [DRY RUN] Would send digest to {$sup->user_email} with ".count($pendingReviews).' reviews, '.count($expiringCredentials).' credentials, and '.count($upcomingSessions).' sessions.');
                $sentCount++;

                continue;
            }

            try {
                Mail::to($sup->user_email)->send(new SupervisorDigestMail($digestData));
                $this->info(" - Digest successfully sent to: {$sup->user_email}");

                AuditTrail::record('supervisor_digest_sent', 'users', (string) $sup->user_id, afterState: [
                    'recipient' => $sup->user_email,
                    'department' => $sup->department_name,
                    'pending_reviews_count' => count($pendingReviews),
                    'expiring_credentials_count' => count($expiringCredentials),
                ]);

                $sentCount++;
            } catch (\Throwable $e) {
                $this->error(" - Failed to deliver to {$sup->user_email}: ".$e->getMessage());
                Log::error("Supervisor digest delivery failure for {$sup->user_email}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Completed. Total digests processed: {$sentCount}");

        return self::SUCCESS;
    }
}
