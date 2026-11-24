<?php

namespace App\Services;

use App\Support\AuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Decides whether a CPD entry QUALIFIES — i.e. whether there is evidence the
 * employee actually did the activity — and keeps `cpd_records.verified` in
 * step with that decision so renewal cycles and dashboards only count
 * qualified hours.
 *
 *   course   → qualified only if the employee has a COMPLETED enrollment
 *   training → qualified only if attendance was recorded (status "attended"
 *              or a check-in time) for that session
 *   external → qualified once HR has verified the certificate
 *
 * Qualified hours are capped at the hours the course / session is worth, so
 * a 2-hour session cannot be claimed as 20.
 */
class CpdQualificationService
{
    public const QUALIFIED = 'qualified';

    public const NOT_QUALIFIED = 'not_qualified';

    public const PENDING = 'pending';

    /**
     * Evaluate one record. Returns:
     *   status, label, badge, reason, eligible_hours
     */
    public function evaluate(object $record): array
    {
        return $this->evaluateMany(collect([$record]))->first();
    }

    /**
     * Evaluate many records with one query per source table.
     *
     * @return Collection<string, array> keyed by cpd_id
     */
    public function evaluateMany(Collection $records): Collection
    {
        $courseIds = $records->where('source_type', 'course')->pluck('source_id')->filter()->unique()->values();
        $sessionIds = $records->where('source_type', 'training')->pluck('source_id')->filter()->unique()->values();
        $employeeIds = $records->pluck('employee_id')->unique()->values();

        $courses = $courseIds->isEmpty() ? collect() : DB::table('courses')->whereIn('course_id', $courseIds)->get()->keyBy('course_id');
        $sessions = $sessionIds->isEmpty() ? collect() : DB::table('training_sessions')->whereIn('session_id', $sessionIds)->get()->keyBy('session_id');

        $enrollments = $courseIds->isEmpty() ? collect() : DB::table('course_enrollments')
            ->whereIn('course_id', $courseIds)->whereIn('employee_id', $employeeIds)->get()
            ->keyBy(fn ($e) => $e->employee_id.'|'.$e->course_id);

        $registrations = $sessionIds->isEmpty() ? collect() : DB::table('training_registrations')
            ->whereIn('session_id', $sessionIds)->whereIn('employee_id', $employeeIds)->get()
            ->keyBy(fn ($r) => $r->employee_id.'|'.$r->session_id);

        return $records->mapWithKeys(function ($r) use ($courses, $sessions, $enrollments, $registrations) {
            $claimed = (float) $r->cpd_hours;

            $result = match ($r->source_type) {
                'course' => $this->course($r, $courses->get($r->source_id), $enrollments->get($r->employee_id.'|'.$r->source_id), $claimed),
                'training' => $this->training($r, $sessions->get($r->source_id), $registrations->get($r->employee_id.'|'.$r->source_id), $claimed),
                default => $r->verified
                    ? $this->result(self::QUALIFIED, 'Certificate verified by HR.', $claimed)
                    : $this->result(self::PENDING, 'External activity — waiting for HR to check the certificate.', 0),
            };

            return [$r->cpd_id => $result];
        });
    }

    private function course(object $r, ?object $course, ?object $enrollment, float $claimed): array
    {
        if (! $r->source_id || ! $course) {
            return $this->result(self::NOT_QUALIFIED, 'No course is linked to this entry.', 0);
        }
        if (! $enrollment) {
            return $this->result(self::NOT_QUALIFIED, "Not enrolled in \"{$course->title}\".", 0);
        }
        if ($enrollment->status !== 'completed') {
            return $this->result(self::NOT_QUALIFIED, "Enrolled in \"{$course->title}\" but not completed (".str_replace('_', ' ', $enrollment->status).', '.(int) $enrollment->progress_pct.'%).', 0);
        }

        $cap = (float) $course->cpd_hours;
        $eligible = $cap > 0 ? min($claimed, $cap) : $claimed;

        return $this->result(self::QUALIFIED, "Completed \"{$course->title}\"".($cap > 0 && $claimed > $cap ? " — capped at the course's {$cap} h." : '.'), $eligible);
    }

    private function training(object $r, ?object $session, ?object $registration, float $claimed): array
    {
        if (! $r->source_id || ! $session) {
            return $this->result(self::NOT_QUALIFIED, 'No training session is linked to this entry.', 0);
        }
        if (! $registration) {
            return $this->result(self::NOT_QUALIFIED, "Not registered for \"{$session->title}\".", 0);
        }

        $attended = $registration->status === 'attended' || ! empty($registration->check_in_time);
        if (! $attended) {
            $why = match ($registration->status) {
                'absent', 'no_show' => 'marked absent',
                'cancelled' => 'registration cancelled',
                'waitlisted' => 'still on the waitlist',
                default => 'attendance not recorded yet',
            };

            return $this->result(self::NOT_QUALIFIED, "Registered for \"{$session->title}\" but {$why}.", 0);
        }

        $cap = (float) $session->cpd_hours;
        $eligible = $cap > 0 ? min($claimed, $cap) : $claimed;

        return $this->result(self::QUALIFIED, "Attended \"{$session->title}\"".($cap > 0 && $claimed > $cap ? " — capped at the session's {$cap} h." : '.'), $eligible);
    }

    private function result(string $status, string $reason, float $eligible): array
    {
        return [
            'status' => $status,
            'label' => match ($status) {
                self::QUALIFIED => 'Qualified',
                self::NOT_QUALIFIED => 'Not qualified',
                default => 'Pending verification',
            },
            'badge' => match ($status) {
                self::QUALIFIED => 'green',
                self::NOT_QUALIFIED => 'red',
                default => 'yellow',
            },
            'icon' => match ($status) {
                self::QUALIFIED => 'bi-check-circle-fill',
                self::NOT_QUALIFIED => 'bi-x-circle-fill',
                default => 'bi-hourglass-split',
            },
            'reason' => $reason,
            'eligible_hours' => round($eligible, 1),
        ];
    }

    /**
     * For course / training entries the evidence is in HIMS, so `verified`
     * simply mirrors the qualification. Run before reporting so an
     * attendance recorded after the CPD entry is picked up.
     */
    public function syncVerifiedFlags(Collection $records): void
    {
        $results = $this->evaluateMany($records->whereIn('source_type', ['course', 'training']));

        foreach ($results as $cpdId => $res) {
            $record = $records->firstWhere('cpd_id', $cpdId);
            $shouldBe = $res['status'] === self::QUALIFIED;
            if ((bool) $record->verified !== $shouldBe) {
                DB::table('cpd_records')->where('cpd_id', $cpdId)->update([
                    'verified' => $shouldBe,
                    'updated_at' => now(),
                ]);
                $record->verified = $shouldBe;
            }
        }
    }

    /**
     * Attendance was recorded: bank the session's CPD automatically (once).
     * Attendance withdrawn: the same entry stops qualifying.
     */
    public function syncFromAttendance(string $sessionId, string $employeeId, ?string $recordedBy = null): void
    {
        $session = DB::table('training_sessions')->where('session_id', $sessionId)->first();
        if (! $session) {
            return;
        }

        $registration = DB::table('training_registrations')->where('session_id', $sessionId)->where('employee_id', $employeeId)->first();
        $attended = $registration && ($registration->status === 'attended' || ! empty($registration->check_in_time));

        $existing = DB::table('cpd_records')->where('employee_id', $employeeId)
            ->where('source_type', 'training')->where('source_id', $sessionId)->first();

        if ($existing) {
            DB::table('cpd_records')->where('cpd_id', $existing->cpd_id)->update(['verified' => $attended, 'updated_at' => now()]);

            return;
        }

        if ($attended && (float) $session->cpd_hours > 0) {
            $id = (string) Str::uuid();
            DB::table('cpd_records')->insert([
                'cpd_id' => $id,
                'employee_id' => $employeeId,
                'source_type' => 'training',
                'source_id' => $sessionId,
                'activity_name' => Str::limit($session->title, 290, ''),
                'cpd_hours' => $session->cpd_hours,
                'date_earned' => substr((string) $session->session_date, 0, 10) ?: now()->toDateString(),
                'renewal_period' => (string) now()->year,
                'verified' => true,
                'verified_by' => $recordedBy,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            AuditTrail::record('cpd_auto_from_attendance', 'cpd_records', $id, afterState: [
                'employee_id' => $employeeId, 'session_id' => $sessionId, 'cpd_hours' => (float) $session->cpd_hours,
            ]);
        }
    }
}
