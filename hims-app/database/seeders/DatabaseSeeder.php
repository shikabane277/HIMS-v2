<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. DEPARTMENTS ─────────────────────────────────
        $depts = [
            ['id' => Str::uuid(), 'name' => 'Nursing Services',        'code' => 'NS',  'is_clinical' => true],
            ['id' => Str::uuid(), 'name' => 'Emergency Medicine',       'code' => 'EM',  'is_clinical' => true],
            ['id' => Str::uuid(), 'name' => 'Medical Imaging',          'code' => 'MI',  'is_clinical' => true],
            ['id' => Str::uuid(), 'name' => 'Pharmacy',                 'code' => 'PH',  'is_clinical' => true],
            ['id' => Str::uuid(), 'name' => 'Human Resources',          'code' => 'HR',  'is_clinical' => false],
            ['id' => Str::uuid(), 'name' => 'Finance & Accounting',     'code' => 'FA',  'is_clinical' => false],
            ['id' => Str::uuid(), 'name' => 'Information Technology',   'code' => 'IT',  'is_clinical' => false],
            ['id' => Str::uuid(), 'name' => 'Quality & Patient Safety', 'code' => 'QPS', 'is_clinical' => true],
        ];

        foreach ($depts as $d) {
            DB::table('departments')->insert([
                'department_id'   => $d['id'],
                'name'            => $d['name'],
                'department_code' => $d['code'],
                'is_clinical'     => $d['is_clinical'],
                'created_at'      => now(), 'updated_at' => now(),
            ]);
        }

        // ── 2. ROLES ───────────────────────────────────────
        $roles = [
            ['id'=>Str::uuid(),'name'=>'Head Nurse',           'slug'=>'head_nurse',    'dept'=>'NS'],
            ['id'=>Str::uuid(),'name'=>'Staff Nurse',          'slug'=>'staff_nurse',   'dept'=>'NS'],
            ['id'=>Str::uuid(),'name'=>'Emergency Physician',  'slug'=>'em_physician',  'dept'=>'EM'],
            ['id'=>Str::uuid(),'name'=>'Radiologist',          'slug'=>'radiologist',   'dept'=>'MI'],
            ['id'=>Str::uuid(),'name'=>'Clinical Pharmacist',  'slug'=>'pharmacist',    'dept'=>'PH'],
            ['id'=>Str::uuid(),'name'=>'HR Manager',           'slug'=>'hr_manager',    'dept'=>'HR'],
            ['id'=>Str::uuid(),'name'=>'HR Officer',           'slug'=>'hr_officer',    'dept'=>'HR'],
            ['id'=>Str::uuid(),'name'=>'Quality Analyst',      'slug'=>'quality_analyst','dept'=>'QPS'],
        ];

        $deptMap = collect($depts)->pluck('id','code');
        foreach ($roles as $r) {
            DB::table('roles')->insert([
                'role_id'       => $r['id'],
                'role_name'     => $r['name'],
                'role_slug'     => $r['slug'],
                'department_id' => $deptMap[$r['dept']],
                'is_clinical'   => !in_array($r['dept'],['HR','FA','IT']),
                'created_at'    => now(), 'updated_at' => now(),
            ]);
        }

        // ── 3. EMPLOYEES ───────────────────────────────────
        $roleMap = collect($roles)->pluck('id','slug');
        $employees = [
            ['code'=>'EMP-0001','first'=>'Maria',    'last'=>'Santos',    'email'=>'m.santos@hospital.ph',  'role'=>'head_nurse',    'dept'=>'NS','title'=>'Head Nurse, ICU'],
            ['code'=>'EMP-0002','first'=>'Jose',     'last'=>'Reyes',     'email'=>'j.reyes@hospital.ph',   'role'=>'staff_nurse',   'dept'=>'NS','title'=>'Staff Nurse II'],
            ['code'=>'EMP-0003','first'=>'Anna',     'last'=>'Cruz',      'email'=>'a.cruz@hospital.ph',    'role'=>'em_physician',  'dept'=>'EM','title'=>'Emergency Medicine Resident'],
            ['code'=>'EMP-0004','first'=>'Carlos',   'last'=>'Mendoza',   'email'=>'c.mendoza@hospital.ph', 'role'=>'pharmacist',    'dept'=>'PH','title'=>'Senior Clinical Pharmacist'],
            ['code'=>'EMP-0005','first'=>'Luisa',    'last'=>'Garcia',    'email'=>'l.garcia@hospital.ph',  'role'=>'hr_manager',   'dept'=>'HR','title'=>'HR Manager'],
            ['code'=>'EMP-0006','first'=>'Roberto',  'last'=>'Lim',       'email'=>'r.lim@hospital.ph',     'role'=>'hr_officer',   'dept'=>'HR','title'=>'HR Officer I'],
            ['code'=>'EMP-0007','first'=>'Rosalinda','last'=>'Bautista',  'email'=>'r.bautista@hospital.ph','role'=>'quality_analyst','dept'=>'QPS','title'=>'Quality Improvement Analyst'],
            ['code'=>'EMP-0008','first'=>'Miguel',   'last'=>'Torres',    'email'=>'m.torres@hospital.ph',  'role'=>'staff_nurse',  'dept'=>'NS','title'=>'Staff Nurse I'],
        ];

        $empIds = [];
        foreach ($employees as $e) {
            $id = Str::uuid();
            $empIds[$e['code']] = $id;
            DB::table('employees')->insert([
                'employee_id'       => $id,
                'employee_code'     => $e['code'],
                'first_name'        => $e['first'],
                'last_name'         => $e['last'],
                'email'             => $e['email'],
                'department_id'     => $deptMap[$e['dept']],
                'role_id'           => $roleMap[$e['role']],
                'position_title'    => $e['title'],
                'hire_date'         => now()->subYears(rand(1,8))->toDateString(),
                'employment_status' => 'active',
                'created_at'        => now(), 'updated_at' => now(),
            ]);
        }

        // ── 4. LOGIN ACCOUNTS ──────────────────────────────
        // Each account is linked to an employees row and given an explicit
        // role. Both matter: role drives every authorisation check, and
        // employee_id is written into NOT NULL FK columns by most write paths
        // (recognition posts, enrolments, assessments), so an unlinked account
        // cannot use the system.
        //
        // The administrator needs an employee profile of its own, so give it
        // an Administration department, role and employee record.
        $adminDeptId = Str::uuid();
        DB::table('departments')->insert([
            'department_id'   => $adminDeptId,
            'name'            => 'Hospital Administration',
            'department_code' => 'ADM',
            'is_clinical'     => false,
            'created_at'      => now(), 'updated_at' => now(),
        ]);

        $adminRoleId = Str::uuid();
        DB::table('roles')->insert([
            'role_id'       => $adminRoleId,
            'role_name'     => 'System Administrator',
            'role_slug'     => 'system_admin',
            'department_id' => $adminDeptId,
            'is_clinical'   => false,
            'created_at'    => now(), 'updated_at' => now(),
        ]);

        $adminEmpId = Str::uuid();
        DB::table('employees')->insert([
            'employee_id'       => $adminEmpId,
            'employee_code'     => 'EMP-0009',
            'first_name'        => 'HIMS',
            'last_name'         => 'Administrator',
            'email'             => 'admin@hospital.ph',
            'department_id'     => $adminDeptId,
            'role_id'           => $adminRoleId,
            'position_title'    => 'System Administrator',
            'hire_date'         => now()->subYears(2)->toDateString(),
            'employment_status' => 'active',
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        $accounts = [
            ['HIMS Administrator', 'admin@hospital.ph',       'admin',      $adminEmpId],
            ['Luisa Garcia',       'l.garcia@hospital.ph',    'hr_manager', $empIds['EMP-0005']],
            ['Roberto Lim',        'r.lim@hospital.ph',       'staff',      $empIds['EMP-0006']],
            ['Maria Santos',       'm.santos@hospital.ph',    'supervisor', $empIds['EMP-0001']],
            ['Jose Reyes',         'j.reyes@hospital.ph',     'staff',      $empIds['EMP-0002']],
        ];

        foreach ($accounts as [$name, $email, $role, $employeeId]) {
            DB::table('users')->insert([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make('password'),
                'role'              => $role,
                'employee_id'       => $employeeId,
                'email_verified_at' => now(),
                'created_at'        => now(), 'updated_at' => now(),
            ]);
        }

        // ── 5. RECOGNITION BADGES ─────────────────────────
        $badges = [
            ['name'=>'Excellence Award',    'icon'=>'🏆','value'=>'Excellence',    'pts'=>10],
            ['name'=>'Patient Champion',    'icon'=>'❤️','value'=>'Compassion',    'pts'=>8],
            ['name'=>'Team Player',         'icon'=>'🤝','value'=>'Collaboration', 'pts'=>5],
            ['name'=>'Innovation Star',     'icon'=>'💡','value'=>'Innovation',    'pts'=>7],
            ['name'=>'Safety Guardian',     'icon'=>'🛡️','value'=>'Safety',        'pts'=>9],
            ['name'=>'Learning Hero',       'icon'=>'📚','value'=>'Growth',        'pts'=>6],
            ['name'=>'Leadership Star',     'icon'=>'⭐','value'=>'Leadership',    'pts'=>8],
            ['name'=>'Above and Beyond',    'icon'=>'🚀','value'=>'Excellence',    'pts'=>10],
        ];

        foreach ($badges as $b) {
            DB::table('recognition_badges')->insert([
                'badge_id'       => Str::uuid(),
                'badge_name'     => $b['name'],
                'badge_icon'     => $b['icon'],
                'hospital_value' => $b['value'],
                'points_value'   => $b['pts'],
                'is_active'      => true,
                'created_at'     => now(), 'updated_at' => now(),
            ]);
        }

        // ── 6. REVIEW CYCLE ───────────────────────────────
        $cycleId = Str::uuid();
        DB::table('review_cycles')->insert([
            'cycle_id'   => $cycleId,
            'cycle_name' => '2026 Annual Performance Review',
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'status'     => 'active',
            'created_by' => $empIds['EMP-0005'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── 7. SAMPLE REVIEW ──────────────────────────────
        DB::table('performance_reviews')->insert([
            'review_id'    => Str::uuid(),
            'employee_id'  => $empIds['EMP-0001'],
            'cycle_id'     => $cycleId,
            'reviewer_id'  => $empIds['EMP-0005'],
            'review_type'  => 'standard',
            'status'       => 'supervisor_review',
            'self_rating'  => 4.20,
            'supervisor_rating' => null,
            'overall_score'=> null,
            'created_at'   => now(), 'updated_at' => now(),
        ]);

        // ── 8. TRAINING VENUE ─────────────────────────────
        DB::table('training_venues')->insert([
            'venue_id'   => Str::uuid(),
            'venue_name' => 'Training Center A',
            'building'   => 'Main Building',
            'floor'      => '3rd Floor',
            'capacity'   => 50,
            'is_active'  => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── 9. SAMPLE COURSE ──────────────────────────────
        DB::table('courses')->insert([
            'course_id'       => Str::uuid(),
            'course_code'     => 'CLN-001',
            'title'           => 'Basic Life Support (BLS) Certification',
            'category'        => 'clinical',
            'cpd_hours'       => 8.0,
            'difficulty_level'=> 'intermediate',
            'passing_score'   => 80.00,
            'is_mandatory'    => true,
            'is_active'       => true,
            'created_by'      => $empIds['EMP-0005'],
            'description'     => 'Standard BLS certification course for all clinical staff.',
            'created_at'      => now(), 'updated_at' => now(),
        ]);

        // ── 10. RECOGNITION POST ──────────────────────────
        DB::table('recognition_posts')->insert([
            'post_id'           => Str::uuid(),
            'author_id'         => $empIds['EMP-0005'],
            'recipient_id'      => $empIds['EMP-0001'],
            'post_type'         => 'management',
            'message'           => 'Outstanding leadership during the ICU capacity surge last week. Your calm, decisive management helped the team deliver excellent patient outcomes. Salamat at mabuhay!',
            'is_public'         => true,
            'moderation_status' => 'approved',
            'is_featured'       => true,
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        // ── 11. COMPETENCY FRAMEWORK, KPIs, ASSESSMENTS ────
        $this->call(CompetencyFrameworkSeeder::class);

        echo "✅ HIMS seed data installed successfully.\n";
        echo "   Logins (all password: password)\n";
        echo "     admin@hospital.ph      — admin       (full access)\n";
        echo "     l.garcia@hospital.ph   — hr_manager  (org-wide HR)\n";
        echo "     m.santos@hospital.ph   — supervisor  (Nursing Services only)\n";
        echo "     j.reyes@hospital.ph    — staff       (own records only)\n";
        echo "     r.lim@hospital.ph      — staff       (own records only)\n";
    }
}
