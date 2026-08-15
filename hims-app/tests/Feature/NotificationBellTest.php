<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The topbar bell's "Mark all read" endpoint.
 *
 * Touches only `notifications`, so unlike the progression tests this runs on
 * the sqlite connection phpunit uses.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_all_read_clears_only_the_callers_notifications(): void
    {
        [$user, $mine] = $this->employeeUser('mine@example.org');
        [, $theirs] = $this->employeeUser('theirs@example.org');

        $this->notification($mine);
        $this->notification($mine);
        $this->notification($theirs);

        $response = $this->actingAs($user)->postJson(route('notifications.read-all'));

        $response->assertOk()->assertJson(['ok' => true, 'cleared' => 2]);

        $this->assertSame(0, DB::table('notifications')
            ->where('recipient_id', $mine)->where('is_read', false)->count());

        // The other employee's alert is untouched.
        $this->assertSame(1, DB::table('notifications')
            ->where('recipient_id', $theirs)->where('is_read', false)->count());
    }

    public function test_mark_all_read_is_a_no_op_for_an_unlinked_account(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Unlinked', 'email' => 'unlinked@example.org',
            'password' => bcrypt('password'), 'role' => 'staff',
            'email_verified_at' => now(), 'employee_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::find($userId))
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['cleared' => 0]);
    }

    public function test_guests_cannot_mark_notifications_read(): void
    {
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_one_notification_can_be_marked_read_without_touching_another_employees_feed(): void
    {
        [$user, $mine] = $this->employeeUser('single-mine@example.org');
        [, $theirs] = $this->employeeUser('single-theirs@example.org');
        $mineId = $this->notification($mine);
        $theirId = $this->notification($theirs);

        $this->actingAs($user)
            ->postJson(route('notifications.read', $mineId))
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 1]);

        $this->assertDatabaseHas('notifications', ['notification_id' => $mineId, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['notification_id' => $theirId, 'is_read' => false]);

        $this->actingAs($user)
            ->postJson(route('notifications.read', $theirId))
            ->assertOk()
            ->assertJson(['updated' => 0]);
    }

    public function test_notification_feed_keeps_read_items_and_adds_a_destination_and_visual_type(): void
    {
        [$user, $employeeId] = $this->employeeUser('feed@example.org');
        $unread = $this->notification($employeeId, false);
        $read = $this->notification($employeeId, true);

        $feed = app(NotificationService::class)->feedFor($user, 10);

        $this->assertEqualsCanonicalizing([$unread, $read], $feed->pluck('notification_id')->all());
        $this->assertSame(route('dashboard'), $feed->firstWhere('notification_id', $unread)->destination_url);
        $this->assertSame('warning', $feed->firstWhere('notification_id', $unread)->tone);
        $this->assertTrue((bool) $feed->firstWhere('notification_id', $read)->is_read);
    }

    public function test_credential_notifications_only_focus_records_the_recipient_can_access(): void
    {
        [$supervisor, $supervisorId] = $this->employeeUser('credential-supervisor@example.org', 'supervisor');
        [, $directReportId] = $this->employeeUser('credential-report@example.org', 'staff', $supervisorId);
        [, $otherEmployeeId] = $this->employeeUser('credential-other@example.org');

        $directCredential = $this->credential($directReportId, 'Direct-report licence');
        $otherCredential = $this->credential($otherEmployeeId, 'Escalated licence');
        $directNotification = $this->notification(
            $supervisorId,
            referenceType: 'employee_credentials',
            referenceId: $directCredential,
        );
        $otherNotification = $this->notification(
            $supervisorId,
            referenceType: 'employee_credentials',
            referenceId: $otherCredential,
        );

        $feed = app(NotificationService::class)->feedFor($supervisor, 10);

        $this->assertSame(
            route('competency.credentials.index', ['focus' => $directCredential]).'#credential-'.$directCredential,
            $feed->firstWhere('notification_id', $directNotification)->destination_url,
        );
        $this->assertSame(
            route('competency.credentials.index'),
            $feed->firstWhere('notification_id', $otherNotification)->destination_url,
        );
    }

    /** @return array{0: User, 1: string} */
    private function employeeUser(string $email, string $role = 'staff', ?string $supervisorId = null): array
    {
        $deptId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $deptId, 'name' => 'Nursing '.Str::random(4),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // roles.role_slug is NOT NULL and unique — omitting it fails the insert.
        $slug = 'nurse-'.Str::lower(Str::random(6));
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId, 'role_name' => 'Nurse '.Str::random(4),
            'role_slug' => $slug,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $empId = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $empId, 'employee_code' => 'EMP-'.Str::upper(Str::random(5)),
            'first_name' => 'Test', 'last_name' => 'Person', 'email' => $email,
            'department_id' => $deptId, 'role_id' => $roleId,
            'supervisor_id' => $supervisorId,
            'employment_status' => 'active', 'hire_date' => now()->subYear()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Person', 'email' => $email,
            'password' => bcrypt('password'), 'role' => $role,
            'email_verified_at' => now(), 'employee_id' => $empId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [User::find($userId), $empId];
    }

    private function credential(string $employeeId, string $type): string
    {
        $id = (string) Str::uuid();
        DB::table('employee_credentials')->insert([
            'credential_id' => $id,
            'employee_id' => $employeeId,
            'credential_type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function notification(
        string $employeeId,
        bool $read = false,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): string {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'notification_id' => $id,
            'recipient_id' => $employeeId,
            'notification_type' => 'credential_expiring_soon',
            'title' => 'BLS expires in 12 day(s)',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'is_read' => $read,
            'read_at' => $read ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
