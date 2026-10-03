<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuccessionSampleSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('critical_positions')->count();
        if ($existing > 0) {
            return;
        }

        $departments = DB::table('departments')->get();
        if ($departments->isEmpty()) {
            return;
        }

        $deptMap = [];
        foreach ($departments as $d) {
            $deptMap[$d->name] = $d->department_id;
        }

        $employees = DB::table('employees')->get();
        $nurseHead = $employees->firstWhere('position_title', 'Head Nurse, ICU')
            ?? $employees->firstWhere('last_name', 'Santos')
            ?? $employees->first();

        $pharmHead = $employees->firstWhere('last_name', 'Mendoza')
            ?? $employees->skip(1)->first();

        $qualityHead = $employees->firstWhere('last_name', 'Bautista')
            ?? $employees->skip(2)->first();

        $emHead = $employees->firstWhere('last_name', 'Cruz')
            ?? $employees->skip(3)->first();

        $samplePositions = [
            [
                'title' => 'Director of Nursing Services',
                'dept_name' => 'Nursing Services',
                'holder_id' => $nurseHead?->employee_id,
                'risk' => 'critical',
                'impact' => 'Clinical governance, hospital nursing operations continuity, patient safety compliance.',
            ],
            [
                'title' => 'Emergency Medicine Department Chair',
                'dept_name' => 'Emergency Medicine',
                'holder_id' => $emHead?->employee_id,
                'risk' => 'high',
                'impact' => 'Trauma response leadership, emergency physician oversight, JCI acute care standards.',
            ],
            [
                'title' => 'Chief Clinical Pharmacist',
                'dept_name' => 'Pharmacy',
                'holder_id' => $pharmHead?->employee_id,
                'risk' => 'medium',
                'impact' => 'Hospital pharmacy administration, formulary safety, chemotherapy and sterile compounding.',
            ],
            [
                'title' => 'Quality & Patient Safety Director',
                'dept_name' => 'Quality & Patient Safety',
                'holder_id' => $qualityHead?->employee_id,
                'risk' => 'high',
                'impact' => 'Hospital accreditation standards (DOH, PhilHealth, JCI), incident investigation, clinical audits.',
            ],
            [
                'title' => 'Chief Information Officer (CIO)',
                'dept_name' => 'Information Technology',
                'holder_id' => null,
                'risk' => 'medium',
                'impact' => 'Electronic Health Records (EHR) infrastructure, hospital cybersecurity, IT disaster recovery.',
            ],
        ];

        foreach ($samplePositions as $p) {
            $deptId = $deptMap[$p['dept_name']] ?? $departments->first()->department_id;

            DB::table('critical_positions')->insert([
                'position_id' => (string) Str::uuid(),
                'position_title' => $p['title'],
                'department_id' => $deptId,
                'current_holder_id' => $p['holder_id'],
                'is_critical' => true,
                'vacancy_risk' => $p['risk'],
                'impact_description' => $p['impact'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
