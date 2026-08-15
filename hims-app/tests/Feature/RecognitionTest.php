<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * First-version Social Recognition behavior.
 *
 * These tests deliberately stay on sqlite: the controller must not depend on
 * the MySQL leaderboard view for core posting, history, or moderation paths.
 */
class RecognitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_hospital_value_badges_are_available_after_migration(): void
    {
        $this->assertDatabaseHas('recognition_badges', ['badge_name' => 'Compassion (Kalinga)']);
        $this->assertDatabaseHas('recognition_badges', ['badge_name' => 'Teamwork (Bayanihan)']);
        $this->assertDatabaseHas('recognition_badges', ['badge_name' => 'Innovation (Diskarte)']);
        $this->assertDatabaseHas('recognition_badges', ['badge_name' => 'Clinical Excellence']);
    }

    public function test_a_linked_employee_can_recognize_a_colleague_and_the_recipient_is_notified(): void
    {
        $department = $this->department('Nursing');
        $authorId = $this->employee($department, 'author@example.org', 'Ana', 'Santos');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Ben', 'Cruz');
        $author = $this->user('staff', $authorId, 'author@example.org');
        $badgeId = DB::table('recognition_badges')->where('badge_name', 'Teamwork (Bayanihan)')->value('badge_id');

        $this->actingAs($author)->post(route('recognition.posts.store'), [
            'recipient_id' => $recipientId,
            'badge_id' => $badgeId,
            'message' => 'Stayed after shift to help the ward complete a safe handover.',
        ])->assertRedirect(route('recognition.index'));

        $post = DB::table('recognition_posts')->first();

        $this->assertNotNull($post);
        $this->assertSame($authorId, $post->author_id);
        $this->assertSame($recipientId, $post->recipient_id);
        $this->assertSame('peer', $post->post_type);
        $this->assertSame('approved', $post->moderation_status);
        $this->assertNull($post->link_to_review_id);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $recipientId,
            'notification_type' => 'recognition_received',
            'reference_type' => 'recognition_post',
            'reference_id' => $post->post_id,
        ]);
    }

    public function test_a_supervisors_post_is_classified_by_the_reporting_line_and_does_not_touch_reviews(): void
    {
        $department = $this->department('Emergency');
        $supervisorId = $this->employee($department, 'lead@example.org', 'Lina', 'Reyes');
        $recipientId = $this->employee($department, 'report@example.org', 'Marco', 'Diaz', [
            'supervisor_id' => $supervisorId,
        ]);
        $supervisor = $this->user('supervisor', $supervisorId, 'lead@example.org');

        $this->actingAs($supervisor)->post(route('recognition.posts.store'), [
            'recipient_id' => $recipientId,
            'message' => 'Handled the emergency transfer calmly and kept the team coordinated.',
        ])->assertRedirect(route('recognition.index'));

        $this->assertDatabaseHas('recognition_posts', [
            'author_id' => $supervisorId,
            'recipient_id' => $recipientId,
            'post_type' => 'supervisor',
            'link_to_review_id' => null,
        ]);
        $this->assertDatabaseCount('performance_reviews', 0);
        $this->assertDatabaseCount('review_kpi_scores', 0);
    }

    public function test_self_recognition_is_rejected(): void
    {
        $department = $this->department('Laboratory');
        $employeeId = $this->employee($department, 'self@example.org', 'Iris', 'Lim');
        $user = $this->user('staff', $employeeId, 'self@example.org');

        $this->actingAs($user)->post(route('recognition.posts.store'), [
            'recipient_id' => $employeeId,
            'message' => 'I did excellent work.',
        ])->assertSessionHasErrors('recipient_id');

        $this->assertDatabaseCount('recognition_posts', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_an_unlinked_account_cannot_create_recognition(): void
    {
        $department = $this->department('Radiology');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Nina', 'Yu');
        $user = User::factory()->create(['role' => 'staff', 'employee_id' => null]);

        $this->actingAs($user)->post(route('recognition.posts.store'), [
            'recipient_id' => $recipientId,
            'message' => 'Excellent support.',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('recognition_posts', 0);
    }

    public function test_reactions_toggle_and_notify_the_post_author_once(): void
    {
        $department = $this->department('Pharmacy');
        $authorId = $this->employee($department, 'author@example.org', 'Ava', 'Flores');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Bea', 'Ramos');
        $reactorId = $this->employee($department, 'reactor@example.org', 'Carlo', 'Tan');
        $reactor = $this->user('staff', $reactorId, 'reactor@example.org');
        $postId = $this->createPostRow($authorId, $recipientId, 'A careful medication reconciliation.');

        $this->actingAs($reactor)->post(route('recognition.react', $postId), [
            'reaction_type' => 'clap',
        ])->assertRedirect();

        $this->assertDatabaseHas('recognition_reactions', [
            'post_id' => $postId,
            'employee_id' => $reactorId,
            'reaction_type' => 'clap',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $authorId,
            'notification_type' => 'recognition_reaction',
            'reference_id' => $postId,
        ]);

        $this->actingAs($reactor)->post(route('recognition.react', $postId), [
            'reaction_type' => 'clap',
        ])->assertRedirect();

        $this->assertDatabaseCount('recognition_reactions', 0);
        $this->assertSame(1, DB::table('notifications')->where('notification_type', 'recognition_reaction')->count());
    }

    public function test_comments_are_stored_and_notify_the_author_and_recipient(): void
    {
        $department = $this->department('Surgery');
        $authorId = $this->employee($department, 'author@example.org', 'Ari', 'Lopez');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Belle', 'Sy');
        $commenterId = $this->employee($department, 'commenter@example.org', 'Chris', 'Ong');
        $commenter = $this->user('staff', $commenterId, 'commenter@example.org');
        $postId = $this->createPostRow($authorId, $recipientId, 'Kept the surgical checklist clear and complete.');

        $this->actingAs($commenter)->post(route('recognition.comments.store', $postId), [
            'comment_text' => 'Well deserved. The handoff was exceptionally clear.',
        ])->assertRedirect();

        $this->assertDatabaseHas('recognition_comments', [
            'post_id' => $postId,
            'author_id' => $commenterId,
            'moderation_status' => 'approved',
        ]);
        $this->assertSame(2, DB::table('notifications')
            ->where('notification_type', 'recognition_comment')
            ->whereIn('recipient_id', [$authorId, $recipientId])
            ->count());
    }

    public function test_feed_and_sent_received_history_only_show_the_expected_approved_posts(): void
    {
        $department = $this->department('Outpatient');
        $meId = $this->employee($department, 'me@example.org', 'Mia', 'Go');
        $otherId = $this->employee($department, 'other@example.org', 'Noel', 'Uy');
        $thirdId = $this->employee($department, 'third@example.org', 'Pia', 'Co');
        $user = $this->user('staff', $meId, 'me@example.org');

        $sent = $this->createPostRow($meId, $otherId, 'Sent recognition');
        $received = $this->createPostRow($otherId, $meId, 'Received recognition');
        $this->createPostRow($otherId, $thirdId, 'Visible team recognition');
        $removed = $this->createPostRow($thirdId, $otherId, 'Removed recognition');
        DB::table('recognition_posts')->where('post_id', $removed)->update(['moderation_status' => 'removed']);

        $this->actingAs($user)->get(route('recognition.index'))
            ->assertOk()
            ->assertSee('Visible team recognition')
            ->assertDontSee('Removed recognition');

        $this->actingAs($user)->get(route('recognition.index', ['view' => 'sent']))
            ->assertOk()
            ->assertViewHas('posts', fn ($posts) => $posts->count() === 1 && $posts->first()->post_id === $sent);

        $this->actingAs($user)->get(route('recognition.index', ['view' => 'received']))
            ->assertOk()
            ->assertViewHas('posts', fn ($posts) => $posts->count() === 1 && $posts->first()->post_id === $received);
    }

    public function test_private_recognition_is_visible_only_to_participants_and_moderators(): void
    {
        $department = $this->department('Outpatient');
        $authorId = $this->employee($department, 'author@example.org', 'Mia', 'Go');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Noel', 'Uy');
        $outsiderId = $this->employee($department, 'outsider@example.org', 'Pia', 'Co');
        $hrId = $this->employee($this->department('Human Resources'), 'hr@example.org', 'Helen', 'Roque');
        $author = $this->user('staff', $authorId, 'author@example.org');
        $recipient = $this->user('staff', $recipientId, 'recipient@example.org');
        $outsider = $this->user('staff', $outsiderId, 'outsider@example.org');
        $hr = $this->user('hr_manager', $hrId, 'hr@example.org');

        $this->actingAs($author)->post(route('recognition.posts.store'), [
            'recipient_id' => $recipientId,
            'message' => 'Private handover appreciation',
            'is_public' => '0',
        ])->assertRedirect();

        $post = DB::table('recognition_posts')->where('message', 'Private handover appreciation')->first();
        $this->assertFalse((bool) $post->is_public);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'recognition_created',
            'resource_id' => $post->post_id,
        ]);

        $this->actingAs($outsider)->get(route('recognition.index'))
            ->assertOk()->assertDontSee('Private handover appreciation');
        $this->actingAs($author)->get(route('recognition.index', ['view' => 'sent']))
            ->assertOk()->assertSee('Private handover appreciation');
        $this->actingAs($recipient)->get(route('recognition.index', ['view' => 'received']))
            ->assertOk()->assertSee('Private handover appreciation');
        $this->actingAs($hr)->get(route('recognition.index', ['view' => 'private']))
            ->assertOk()->assertSee('Private handover appreciation');
    }

    public function test_private_recognition_reactions_and_comments_are_limited_to_the_same_audience(): void
    {
        $department = $this->department('Surgery');
        $authorId = $this->employee($department, 'author@example.org', 'Ari', 'Lopez');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Belle', 'Sy');
        $outsiderId = $this->employee($department, 'outsider@example.org', 'Chris', 'Ong');
        $author = $this->user('staff', $authorId, 'author@example.org');
        $outsider = $this->user('staff', $outsiderId, 'outsider@example.org');
        $postId = $this->createPostRow($authorId, $recipientId, 'Private clinical appreciation', false);

        $this->actingAs($outsider)->post(route('recognition.react', $postId), [
            'reaction_type' => 'clap',
        ])->assertNotFound();
        $this->actingAs($outsider)->post(route('recognition.comments.store', $postId), [
            'comment_text' => 'Should not be visible',
        ])->assertNotFound();

        $this->actingAs($author)->post(route('recognition.react', $postId), [
            'reaction_type' => 'clap',
        ])->assertRedirect();
        $this->actingAs($author)->post(route('recognition.comments.store', $postId), [
            'comment_text' => 'Visible to the private audience',
        ])->assertRedirect();

        $this->assertDatabaseHas('recognition_reactions', ['post_id' => $postId, 'employee_id' => $authorId]);
        $this->assertDatabaseHas('recognition_comments', ['post_id' => $postId, 'author_id' => $authorId]);
    }

    public function test_hr_can_moderate_posts_and_comments_while_staff_cannot(): void
    {
        $department = $this->department('ICU');
        $authorId = $this->employee($department, 'author@example.org', 'Rae', 'Lee');
        $recipientId = $this->employee($department, 'recipient@example.org', 'Sam', 'Chua');
        $staffId = $this->employee($department, 'staff@example.org', 'Toni', 'Ang');
        $hrId = $this->employee($this->department('Human Resources'), 'hr@example.org', 'Helen', 'Roque');
        $staff = $this->user('staff', $staffId, 'staff@example.org');
        $hr = $this->user('hr_manager', $hrId, 'hr@example.org');
        $postId = $this->createPostRow($authorId, $recipientId, 'Recognition requiring moderation.');
        $commentId = $this->comment($postId, $staffId, 'Comment requiring moderation.');

        $this->actingAs($staff)->patch(route('recognition.posts.moderate', $postId), [
            'moderation_status' => 'removed',
        ])->assertForbidden();

        $this->actingAs($hr)->patch(route('recognition.posts.moderate', $postId), [
            'moderation_status' => 'removed',
            'moderation_note' => 'Contains confidential patient details.',
        ])->assertRedirect();

        $this->actingAs($hr)->patch(route('recognition.comments.moderate', $commentId), [
            'moderation_status' => 'removed',
        ])->assertRedirect();

        $this->assertDatabaseHas('recognition_posts', [
            'post_id' => $postId,
            'moderation_status' => 'removed',
            'moderated_by' => $hrId,
            'moderation_note' => 'Contains confidential patient details.',
        ]);
        $this->assertDatabaseHas('recognition_comments', [
            'comment_id' => $commentId,
            'moderation_status' => 'removed',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'recognition_moderated',
            'resource_type' => 'recognition_posts',
            'resource_id' => $postId,
        ]);
    }

    public function test_only_hr_and_admin_can_create_value_badges(): void
    {
        $department = $this->department('Admin');
        $staffId = $this->employee($department, 'staff@example.org', 'Uma', 'Dee');
        $hrId = $this->employee($department, 'hr@example.org', 'Vera', 'Que');
        $staff = $this->user('staff', $staffId, 'staff@example.org');
        $hr = $this->user('hr_manager', $hrId, 'hr@example.org');
        $payload = [
            'badge_name' => 'Patient Safety Advocate',
            'badge_icon' => 'bi bi-shield-check',
            'badge_color' => '#0f766e',
            'hospital_value' => 'Safety',
            'description' => 'Recognises prevention and early escalation of safety risks.',
            'points_value' => 5,
        ];

        $this->actingAs($staff)->post(route('recognition.badges.store'), $payload)->assertForbidden();
        $this->actingAs($hr)->post(route('recognition.badges.store'), $payload)
            ->assertRedirect(route('recognition.index'));

        $this->assertDatabaseHas('recognition_badges', [
            'badge_name' => 'Patient Safety Advocate',
            'hospital_value' => 'Safety',
            'is_active' => true,
        ]);
    }

    public function test_guests_cannot_reach_recognition(): void
    {
        $this->get('/recognition')->assertRedirect(route('login'));
        $this->post('/recognition/posts')->assertRedirect(route('login'));
    }

    private function department(string $name): string
    {
        $id = (string) Str::uuid();

        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::lower(Str::random(4)),
            'department_code' => Str::upper(Str::random(6)),
            'is_clinical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(
        string $departmentId,
        string $email,
        string $firstName,
        string $lastName,
        array $overrides = [],
    ): string {
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => 'Role '.Str::random(7),
            'role_slug' => 'role-'.Str::lower(Str::random(8)),
            'department_id' => $departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(7)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $roleId,
            'hire_date' => now()->subYear()->toDateString(),
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function user(string $role, string $employeeId, string $email): User
    {
        return User::factory()->create([
            'name' => 'Recognition User',
            'email' => $email,
            'role' => $role,
            'employee_id' => $employeeId,
        ]);
    }

    private function createPostRow(string $authorId, string $recipientId, string $message, bool $isPublic = true): string
    {
        $id = (string) Str::uuid();

        DB::table('recognition_posts')->insert([
            'post_id' => $id,
            'author_id' => $authorId,
            'recipient_id' => $recipientId,
            'badge_id' => null,
            'post_type' => 'peer',
            'message' => $message,
            'is_public' => $isPublic,
            'is_featured' => false,
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function comment(string $postId, string $authorId, string $text): string
    {
        $id = (string) Str::uuid();

        DB::table('recognition_comments')->insert([
            'comment_id' => $id,
            'post_id' => $postId,
            'author_id' => $authorId,
            'comment_text' => $text,
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
