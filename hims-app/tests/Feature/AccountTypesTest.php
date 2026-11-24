<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Account types: Training Officer, Trainee (time-limited), Staff label, and
 * the session-instructor capability.
 */
class AccountTypesTest extends TestCase
{
    use RefreshDatabase;

    // ── Training Officer ─────────────────────────────────────

    public function test_training_officer_runs_the_learning_programme(): void
    {
        $to = $this->user('training_officer', $this->employee());

        $this->actingAs($to)->get(route('dashboard'))->assertOk()->assertSee('Training &amp; Development Dashboard', false);
        $this->actingAs($to)->get(route('learning.index'))->assertOk();
        $this->actingAs($to)->get(route('learning.assignments.index'))->assertOk();
        $this->actingAs($to)->get(route('learning.renewals.rules'))->assertOk();
        $this->actingAs($to)->get(route('learning.accreditation'))->assertOk();
        $this->actingAs($to)->get(route('learning.accounts'))->assertOk();
        $this->actingAs($to)->get(route('training.index'))->assertOk();
        $this->actingAs($to)->get(route('competency.gap.index'))->assertOk()->assertSee('open to supervisors and HR only');

        $this->actingAs($to)->post(route('learning.courses.store'), [
            'title' => 'Blood Transfusion Safety', 'category' => 'clinical', 'cpd_hours' => 2, 'difficulty_level' => 'intermediate',
        ])->assertRedirect();
        $this->assertDatabaseHas('courses', ['title' => 'Blood Transfusion Safety']);

        $this->actingAs($to)->post(route('training.venues.store'), ['venue_name' => 'Skills Lab 2', 'capacity' => 16])->assertRedirect();
        $this->assertDatabaseHas('training_venues', ['venue_name' => 'Skills Lab 2']);
    }

    public function test_training_officer_sees_all_learners_cpd_but_no_performance_or_succession(): void
    {
        $to = $this->user('training_officer', $this->employee());
        $nurse = $this->employee();
        DB::table('cpd_records')->insert(['cpd_id' => (string) Str::uuid(), 'employee_id' => $nurse, 'source_type' => 'external',
            'activity_name' => 'Wound Care Conference', 'cpd_hours' => 6, 'date_earned' => now()->toDateString(), 'verified' => false,
            'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($to)->get(route('learning.cpd.index'))->assertOk()->assertSee('Wound Care Conference');

        $this->actingAs($to)->get(route('performance.index'))->assertForbidden();
        $this->actingAs($to)->get(route('succession.index'))->assertForbidden();
        $this->actingAs($to)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($to)->get(route('users.index'))->assertForbidden();
        $this->actingAs($to)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($to)->get(route('competency.gap.employee', $nurse))->assertForbidden();
    }

    // ── Trainee ──────────────────────────────────────────────

    public function test_admin_creates_trainee_only_with_an_end_date(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Student Nurse', 'email' => 'student@example.org', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!',
            'role' => 'trainee',
        ])->assertSessionHasErrors('access_expires_at');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Student Nurse', 'email' => 'student@example.org', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!',
            'role' => 'trainee', 'access_expires_at' => now()->addMonths(3)->toDateString(),
        ])->assertRedirect();

        $u = User::where('email', 'student@example.org')->first();
        $this->assertSame('trainee', $u->role);
        $this->assertNotNull($u->access_expires_at);
    }

    public function test_trainee_has_learning_but_no_performance(): void
    {
        $trainee = $this->user('trainee', $this->employee(), now()->addMonth());

        $this->actingAs($trainee)->get(route('dashboard'))->assertOk()->assertSee('My Training Dashboard')->assertSee('Access Until')->assertDontSee('My Reviews');
        $this->actingAs($trainee)->get(route('learning.index'))->assertOk();
        $this->actingAs($trainee)->get(route('training.index'))->assertOk();
        $this->actingAs($trainee)->get(route('performance.index'))->assertForbidden();
        $this->actingAs($trainee)->get(route('reports.index'))->assertForbidden();
    }

    public function test_trainee_cannot_sign_in_or_stay_signed_in_after_end_date(): void
    {
        $trainee = $this->user('trainee', $this->employee(), now()->subDay());

        $this->post(route('login'), ['email' => $trainee->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($trainee)->withSession(['hims_last_activity' => time()])
            ->get(route('dashboard'))->assertRedirect(route('landing', ['reason' => 'expired']));
    }

    public function test_staff_is_labelled_staff_not_trainee(): void
    {
        $this->assertSame('Staff', Roles::label('staff'));
        $this->assertSame('Trainee', Roles::label('trainee'));
        $this->assertSame('Training Officer', Roles::label('training_officer'));

        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('Account types')->assertSee('Training Officer');
    }

    // ── Session instructor ───────────────────────────────────

    public function test_staff_instructor_takes_attendance_for_own_session_only(): void
    {
        $instructorEmp = $this->employee();
        $instructor = $this->user('staff', $instructorEmp);
        $learner = $this->employee();

        $own = $this->trainingSession($instructorEmp);
        $other = $this->trainingSession($this->employee());
        $regOwn = $this->register($own, $learner);
        $regOther = $this->register($other, $learner);

        $this->actingAs($instructor)->get(route('training.sessions.show', $own))->assertOk()->assertSee('checkin-form', false);
        $this->actingAs($instructor)->post(route('training.sessions.checkin', $own), ['registrations' => [$regOwn => 'attended']])->assertRedirect();
        $this->assertSame('attended', DB::table('training_registrations')->where('registration_id', $regOwn)->value('status'));

        $this->actingAs($instructor)->post(route('training.sessions.checkin', $other), ['registrations' => [$regOther => 'attended']])->assertForbidden();
    }

    // ── Helpers ──────────────────────────────────────────────

    private function user(string $role, ?string $employeeId = null, $expires = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' User', 'email' => $role.'-'.Str::lower(Str::random(6)).'@example.org', 'password' => bcrypt('password'),
            'role' => $role, 'email_verified_at' => now(), 'employee_id' => $employeeId, 'access_expires_at' => $expires,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    private function employee(): string
    {
        $dept = (string) Str::uuid();
        DB::table('departments')->insert(['department_id' => $dept, 'name' => 'Ward '.Str::random(6), 'created_at' => now(), 'updated_at' => now()]);
        $role = (string) Str::uuid();
        DB::table('roles')->insert(['role_id' => $role, 'role_name' => 'Nurse '.Str::random(5), 'role_slug' => 'n-'.Str::lower(Str::random(8)), 'created_at' => now(), 'updated_at' => now()]);
        $id = (string) Str::uuid();
        DB::table('employees')->insert(['employee_id' => $id, 'employee_code' => 'EMP-'.Str::upper(Str::random(6)), 'first_name' => 'Test', 'last_name' => 'Person',
            'email' => Str::random(6).'@example.org', 'department_id' => $dept, 'role_id' => $role, 'employment_status' => 'active',
            'hire_date' => now()->subYear()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function trainingSession(string $instructorId): string
    {
        $id = (string) Str::uuid();
        DB::table('training_sessions')->insert(['session_id' => $id, 'title' => 'BLS Refresher '.Str::random(3), 'category' => 'clinical',
            'instructor_id' => $instructorId, 'session_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00',
            'capacity' => 12, 'status' => 'scheduled', 'cpd_hours' => 3, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function register(string $sessionId, string $employeeId): string
    {
        $id = (string) Str::uuid();
        DB::table('training_registrations')->insert(['registration_id' => $id, 'session_id' => $sessionId, 'employee_id' => $employeeId, 'status' => 'registered']);

        return $id;
    }
}
