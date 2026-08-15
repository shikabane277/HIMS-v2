<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes to the `notifications` table, which was migrated in the very first
 * release and then never used by anything — the topbar bell was hard-coded to
 * "You're all caught up." regardless of what was actually happening.
 *
 * Recipients are employees, not users: `notifications.recipient_id` is an FK to
 * `employees.employee_id`. An account with no linked employee profile cannot
 * receive notifications, and every write here drops silently rather than
 * failing a NOT NULL FK — a nightly scan should not abort because one account
 * is unlinked.
 */
class NotificationService
{
    /**
     * Record one notification. Returns the new id, or null if it was skipped.
     */
    public function notify(
        ?string $recipientEmployeeId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): ?string {
        if (! $recipientEmployeeId) {
            return null;
        }

        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'notification_id' => $id,
            'recipient_id' => $recipientEmployeeId,
            'notification_type' => $type,
            'title' => Str::limit($title, 295),
            'message' => $message,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Same payload to several people, skipping blanks and duplicates.
     *
     * @param  array<int, string|null>  $recipientEmployeeIds
     * @return array<int, string> the ids actually written
     */
    public function notifyMany(
        array $recipientEmployeeIds,
        string $type,
        string $title,
        ?string $message = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): array {
        $written = [];

        foreach (array_unique(array_filter($recipientEmployeeIds)) as $recipient) {
            if ($id = $this->notify($recipient, $type, $title, $message, $referenceType, $referenceId)) {
                $written[] = $id;
            }
        }

        return $written;
    }

    /**
     * Who should be told about something happening to this employee: the
     * employee themselves, their supervisor, and the head of their department.
     *
     * The department head is included because supervisor_id is nullable — for
     * staff with no direct supervisor recorded, the head is the only person who
     * would otherwise hear about an expiring licence.
     *
     * @return array<int, string>
     */
    public function escalationChain(string $employeeId): array
    {
        $employee = DB::table('employees')
            ->where('employee_id', $employeeId)
            ->select('employee_id', 'supervisor_id', 'department_id')
            ->first();

        if (! $employee) {
            return [];
        }

        $head = DB::table('departments')
            ->where('department_id', $employee->department_id)
            ->value('head_employee_id');

        return array_values(array_unique(array_filter([
            $employee->employee_id,
            $employee->supervisor_id,
            $head,
        ])));
    }

    /**
     * Unread notifications for the bell, newest first.
     */
    public function unreadFor(?string $employeeId, int $limit = 10): Collection
    {
        if (! $employeeId) {
            return collect();
        }

        return DB::table('notifications')
            ->where('recipient_id', $employeeId)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Recent feed items for the topbar, including items already read.
     *
     * A Facebook-style notification panel is a short activity history rather
     * than an inbox that erases a row as soon as it is acknowledged. Presentation
     * metadata is added here so the Blade shell never has to understand domain
     * reference types or decide where a sensitive record should link.
     */
    public function feedFor(User $user, int $limit = 12): Collection
    {
        if (! $user->employee_id) {
            return collect();
        }

        return DB::table('notifications')
            ->where('recipient_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($notification) use ($user) {
                [$notification->icon, $notification->tone] = $this->appearanceFor(
                    $notification->notification_type
                );
                $notification->destination_url = $this->destinationFor($notification, $user);

                return $notification;
            });
    }

    public function unreadCount(?string $employeeId): int
    {
        if (! $employeeId) {
            return 0;
        }

        return DB::table('notifications')
            ->where('recipient_id', $employeeId)
            ->where('is_read', false)
            ->count();
    }

    public function markAllRead(?string $employeeId): int
    {
        if (! $employeeId) {
            return 0;
        }

        return DB::table('notifications')
            ->where('recipient_id', $employeeId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function markRead(?string $employeeId, string $notificationId): int
    {
        if (! $employeeId) {
            return 0;
        }

        // Scoped by recipient so one employee cannot dismiss another's alerts.
        return DB::table('notifications')
            ->where('notification_id', $notificationId)
            ->where('recipient_id', $employeeId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** @return array{string, string} */
    private function appearanceFor(string $type): array
    {
        return match (true) {
            str_starts_with($type, 'recognition_') => ['bi-stars', 'celebration'],
            str_starts_with($type, 'credential_expired') => ['bi-patch-exclamation-fill', 'danger'],
            str_starts_with($type, 'credential_') => ['bi-patch-exclamation', 'warning'],
            str_starts_with($type, 'competency_') => ['bi-bullseye', 'info'],
            str_starts_with($type, 'cycle_shortfall') => ['bi-arrow-repeat', 'danger'],
            str_starts_with($type, 'cycle_') => ['bi-arrow-repeat', 'warning'],
            $type === 'course_completed' => ['bi-mortarboard-fill', 'success'],
            $type === 'cpd_verified' => ['bi-patch-check-fill', 'success'],
            default => ['bi-bell-fill', 'neutral'],
        };
    }

    private function destinationFor(object $notification, User $user): string
    {
        return match ($notification->reference_type) {
            'recognition_post' => route('recognition.index', [
                'focus' => $notification->reference_id,
            ]).'#recognition-post-'.$notification->reference_id,
            'employee_credentials' => $this->credentialDestination($notification, $user),
            'competency_assessments' => route('competency.index'),
            'employee_renewal_cycles' => $user->can('view-compliance')
                ? route('learning.renewals.index')
                : route('learning.cycles.mine'),
            'course_enrollments' => route('employees.progression.mine'),
            'cpd_records' => route('learning.cpd.index', [
                'focus' => $notification->reference_id,
            ]).'#cpd-'.$notification->reference_id,
            default => route('dashboard'),
        };
    }

    /**
     * Keep a credential alert useful without generating a deep link that the
     * recipient's reporting-line scope will immediately filter out. Department
     * heads can be part of the escalation chain even when they are not the
     * employee's recorded supervisor, so those alerts fall back to the module
     * rather than pretending the referenced row is available to them.
     */
    private function credentialDestination(object $notification, User $user): string
    {
        $credentialId = $notification->reference_id;
        $employeeId = $credentialId
            ? DB::table('employee_credentials')->where('credential_id', $credentialId)->value('employee_id')
            : null;

        if (! $this->canSeeEmployeeRecord($user, $employeeId)) {
            return route('competency.credentials.index');
        }

        return route('competency.credentials.index', [
            'focus' => $credentialId,
        ]).'#credential-'.$credentialId;
    }

    private function canSeeEmployeeRecord(User $user, ?string $employeeId): bool
    {
        if (! $employeeId || ! $user->employee_id) {
            return false;
        }

        if ($user->seesWholeOrganisation() || $user->employee_id === $employeeId) {
            return true;
        }

        return $user->isSupervisor()
            && DB::table('employees')->where('employee_id', $employeeId)->value('supervisor_id') === $user->employee_id;
    }
}
