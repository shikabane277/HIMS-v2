<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportHonestScoresTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'department_id' => $id,
            'name' => $name.' '.Str::random(4),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function role(): string
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'role_id' => $id,
            'role_name' => 'Staff Nurse '.Str::random(4),
            'role_slug' => 'staff-nurse-'.Str::lower(Str::random(6)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(string $departmentId, string $email, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('employees')->insert(array_merge([
            'employee_id' => $id,
            'employee_code' => 'EMP-'.Str::upper(Str::random(6)),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $email,
            'department_id' => $departmentId,
            'role_id' => $this->role(),
            'employment_status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function cycle(string $createdBy): string
    {
        $id = (string) Str::uuid();
        DB::table('review_cycles')->insert([
            'cycle_id' => $id,
            'cycle_name' => 'Annual Review '.Str::random(4),
            'cycle_type' => 'annual',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_reviews_without_kpi_scores_render_em_dash_not_fabricated_scores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $deptId = $this->department('Cardiology');
        $empId = $this->employee($deptId, 'nurse.jane@hospital.ph', ['first_name' => 'Jane', 'last_name' => 'Doe']);
        $cycleId = $this->cycle($empId);

        $reviewId = (string) Str::uuid();
        DB::table('performance_reviews')->insert([
            'review_id' => $reviewId,
            'employee_id' => $empId,
            'cycle_id' => $cycleId,
            'status' => 'draft',
            'overall_score' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
        $response->assertDontSee('3.25');
        $response->assertDontSee('3.50');
        $response->assertSee('—');
    }

    public function test_appraisal_detail_shows_not_assessed_for_missing_self_score(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $deptId = $this->department('Pediatrics');
        $empId = $this->employee($deptId, 'nurse.carlos@hospital.ph', ['first_name' => 'Carlos', 'last_name' => 'Mendoza']);
        $cycleId = $this->cycle($empId);

        $reviewId = (string) Str::uuid();
        DB::table('performance_reviews')->insert([
            'review_id' => $reviewId,
            'employee_id' => $empId,
            'cycle_id' => $cycleId,
            'status' => 'submitted',
            'overall_score' => 4.00,
            'employee_response' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.appraisal.show', $reviewId));

        $response->assertStatus(200);
        $response->assertSee('Not assessed');
        $response->assertDontSee('3.80');
        $response->assertDontSee('Completed all assigned mandatory competencies');
        $response->assertSee('No self-evaluation comments submitted by employee');
    }
}
