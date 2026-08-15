<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private ?string $departmentId = null;

    private ?string $roleId = null;

    public function test_guests_cannot_search_the_system(): void
    {
        $this->getJson(route('search', ['q' => 'dashboard']))
            ->assertRedirect(route('login'));
    }

    public function test_staff_search_is_limited_to_their_own_records_and_public_modules(): void
    {
        $reviewerId = $this->employee('Review', 'Supervisor');
        $mine = $this->employee('VisibleNeedle', 'Nurse', $reviewerId);
        $other = $this->employee('HiddenNeedle', 'Nurse', $reviewerId);
        $staff = $this->user($mine, 'staff', 'Visible Needle');

        $cycleId = $this->reviewCycle($reviewerId, 'Needle Annual Cycle');
        $mineReview = $this->review($mine, $reviewerId, $cycleId);
        $otherReview = $this->review($other, $reviewerId, $cycleId);

        $positionId = (string) Str::uuid();
        DB::table('critical_positions')->insert([
            'position_id' => $positionId,
            'position_title' => 'Needle Chief Nurse',
            'department_id' => $this->department(),
            'is_critical' => true,
            'vacancy_risk' => 'high',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'course_id' => $courseId,
            'course_code' => 'NDL-101',
            'title' => 'Needle Safety Essentials',
            'category' => 'Clinical',
            'cpd_hours' => 2,
            'difficulty_level' => 'beginner',
            'passing_score' => 70,
            'is_active' => true,
            'is_mandatory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($staff)
            ->getJson(route('search', ['q' => 'Needle']))
            ->assertOk();

        $results = collect($response->json('results'));
        $ids = $results->pluck('id');

        $this->assertTrue($ids->contains('employee:'.$mine));
        $this->assertFalse($ids->contains('employee:'.$other));
        $this->assertTrue($ids->contains('review:'.$mineReview));
        $this->assertFalse($ids->contains('review:'.$otherReview));
        $this->assertFalse($ids->contains('position:'.$positionId));
        $this->assertTrue($ids->contains('course:'.$courseId));

        $employeeResult = $results->firstWhere('id', 'employee:'.$mine);
        $courseResult = $results->firstWhere('id', 'course:'.$courseId);
        $this->assertSame(route('employees.progression.mine'), $employeeResult['url']);
        $this->assertSame(route('learning.courses.show', $courseId), $courseResult['url']);
    }

    public function test_supervisor_search_follows_the_reporting_line_and_redacts_succession_ratings(): void
    {
        $supervisorId = $this->employee('Search', 'Supervisor');
        $otherSupervisorId = $this->employee('Other', 'Supervisor');
        $directId = $this->employee('DirectNeedle', 'Nurse', $supervisorId);
        $unrelatedId = $this->employee('PeerNeedle', 'Nurse', $otherSupervisorId);
        $supervisor = $this->user($supervisorId, 'supervisor', 'Search Supervisor');

        [$directPosition, $directCandidate] = $this->successionRecord(
            $directId,
            'Needle Direct Position',
            'ready_now'
        );
        [$otherPosition, $otherCandidate] = $this->successionRecord(
            $unrelatedId,
            'Needle Unrelated Position',
            'ready_now'
        );

        $results = collect($this->actingAs($supervisor)
            ->getJson(route('search', ['q' => 'Needle']))
            ->assertOk()
            ->json('results'));
        $ids = $results->pluck('id');

        $this->assertTrue($ids->contains('employee:'.$directId));
        $this->assertFalse($ids->contains('employee:'.$unrelatedId));
        $this->assertTrue($ids->contains('position:'.$directPosition));
        $this->assertTrue($ids->contains('candidate:'.$directCandidate));
        $this->assertFalse($ids->contains('position:'.$otherPosition));
        $this->assertFalse($ids->contains('candidate:'.$otherCandidate));

        $readinessIds = collect($this->actingAs($supervisor)
            ->getJson(route('search', ['q' => 'ready_now']))
            ->assertOk()
            ->json('results'))
            ->pluck('id');

        $this->assertFalse($readinessIds->contains('candidate:'.$directCandidate));
    }

    public function test_private_recognition_is_searchable_only_by_participants_and_moderators(): void
    {
        $authorId = $this->employee('Private', 'Author');
        $recipientId = $this->employee('Private', 'Recipient');
        $outsiderId = $this->employee('Private', 'Outsider');
        $moderatorId = $this->employee('Private', 'Moderator');

        $author = $this->user($authorId, 'staff', 'Private Author');
        $recipient = $this->user($recipientId, 'staff', 'Private Recipient');
        $outsider = $this->user($outsiderId, 'staff', 'Private Outsider');
        $moderator = $this->user($moderatorId, 'hr_manager', 'Private Moderator');

        $privatePost = $this->recognitionPost($authorId, $recipientId, 'SequoiaPrivateKeyword', false);
        $publicPost = $this->recognitionPost($authorId, $recipientId, 'SequoiaPublicKeyword', true);

        foreach ([$author, $recipient, $moderator] as $viewer) {
            $ids = collect($this->actingAs($viewer)
                ->getJson(route('search', ['q' => 'SequoiaPrivateKeyword']))
                ->assertOk()
                ->json('results'))
                ->pluck('id');

            $this->assertTrue($ids->contains('recognition:'.$privatePost));
        }

        $outsiderPrivate = collect($this->actingAs($outsider)
            ->getJson(route('search', ['q' => 'SequoiaPrivateKeyword']))
            ->assertOk()
            ->json('results'))
            ->pluck('id');
        $this->assertFalse($outsiderPrivate->contains('recognition:'.$privatePost));

        $outsiderPublic = collect($this->actingAs($outsider)
            ->getJson(route('search', ['q' => 'SequoiaPublicKeyword']))
            ->assertOk()
            ->json('results'))
            ->pluck('id');
        $this->assertTrue($outsiderPublic->contains('recognition:'.$publicPost));
    }

    public function test_admin_can_search_accounts_and_confidential_succession_terms(): void
    {
        $adminEmployee = $this->employee('Global', 'Admin');
        $candidateEmployee = $this->employee('AdminNeedle', 'Candidate');
        $admin = $this->user($adminEmployee, 'admin', 'Global Admin');
        $account = User::factory()->create([
            'name' => 'AccessNeedle Account',
            'email' => 'access-needle@example.org',
            'role' => 'staff',
            'employee_id' => null,
        ]);
        [, $candidateId] = $this->successionRecord(
            $candidateEmployee,
            'Admin Search Position',
            'ready_now'
        );

        $accountIds = collect($this->actingAs($admin)
            ->getJson(route('search', ['q' => 'AccessNeedle']))
            ->assertOk()
            ->json('results'))
            ->pluck('id');
        $this->assertTrue($accountIds->contains('user:'.$account->id));

        $readinessIds = collect($this->actingAs($admin)
            ->getJson(route('search', ['q' => 'ready_now']))
            ->assertOk()
            ->json('results'))
            ->pluck('id');
        $this->assertTrue($readinessIds->contains('candidate:'.$candidateId));
    }

    public function test_ai_conversation_search_is_isolated_to_the_owner_and_links_back_to_the_rail(): void
    {
        $owner = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $mine = (string) Str::uuid();
        $theirs = (string) Str::uuid();

        DB::table('ai_chat_sessions')->insert([
            [
                'id' => $mine,
                'user_id' => $owner->id,
                'title' => 'Needle Chat History',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $theirs,
                'user_id' => $other->id,
                'title' => 'Needle Chat History',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $results = collect($this->actingAs($owner)
            ->getJson(route('search', ['q' => 'Needle Chat']))
            ->assertOk()
            ->json('results'));

        $this->assertNotNull($result = $results->firstWhere('id', 'ai-session:'.$mine));
        $this->assertNull($results->firstWhere('id', 'ai-session:'.$theirs));
        $this->assertSame(route('dashboard', ['ai_session' => $mine]), $result['url']);
    }

    private function department(): string
    {
        if ($this->departmentId) {
            return $this->departmentId;
        }

        $this->departmentId = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $this->departmentId,
            'name' => 'Search Nursing '.Str::random(5),
            'department_code' => 'SRCH'.Str::upper(Str::random(3)),
            'is_clinical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->departmentId;
    }

    private function roleId(): string
    {
        if ($this->roleId) {
            return $this->roleId;
        }

        $this->roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $this->roleId,
            'role_name' => 'Search Nurse '.Str::random(5),
            'role_slug' => 'search-nurse-'.Str::lower(Str::random(6)),
            'department_id' => $this->department(),
            'is_clinical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->roleId;
    }

    private function employee(string $firstName, string $lastName, ?string $supervisorId = null): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(7)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Str::lower(Str::random(8)).'@example.org',
            'department_id' => $this->department(),
            'role_id' => $this->roleId(),
            'position_title' => 'Registered Nurse',
            'hire_date' => now()->subYear()->toDateString(),
            'employment_status' => 'active',
            'supervisor_id' => $supervisorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function user(string $employeeId, string $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => Str::lower(Str::random(8)).'@example.org',
            'role' => $role,
            'employee_id' => $employeeId,
        ]);
    }

    private function reviewCycle(string $createdBy, string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('review_cycles')->insert([
            'cycle_id' => $id,
            'cycle_name' => $name,
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function review(string $employeeId, string $reviewerId, string $cycleId): string
    {
        $id = (string) Str::uuid();
        DB::table('performance_reviews')->insert([
            'review_id' => $id,
            'employee_id' => $employeeId,
            'cycle_id' => $cycleId,
            'reviewer_id' => $reviewerId,
            'review_type' => 'standard',
            'status' => 'draft',
            'is_exception_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array{string, string} */
    private function successionRecord(string $employeeId, string $positionTitle, string $readiness): array
    {
        $positionId = (string) Str::uuid();
        $candidateId = (string) Str::uuid();

        DB::table('critical_positions')->insert([
            'position_id' => $positionId,
            'position_title' => $positionTitle,
            'department_id' => $this->department(),
            'is_critical' => true,
            'vacancy_risk' => 'high',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('succession_candidates')->insert([
            'candidate_id' => $candidateId,
            'position_id' => $positionId,
            'employee_id' => $employeeId,
            'performance_score' => 4,
            'potential_score' => 5,
            'nine_box_label' => 'High Potential',
            'readiness_level' => $readiness,
            'status' => 'proposed',
            'nominated_at' => now(),
        ]);

        return [$positionId, $candidateId];
    }

    private function recognitionPost(string $authorId, string $recipientId, string $message, bool $public): string
    {
        $id = (string) Str::uuid();
        DB::table('recognition_posts')->insert([
            'post_id' => $id,
            'author_id' => $authorId,
            'recipient_id' => $recipientId,
            'post_type' => 'peer',
            'message' => $message,
            'is_public' => $public,
            'is_featured' => false,
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
