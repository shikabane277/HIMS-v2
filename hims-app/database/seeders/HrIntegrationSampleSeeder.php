<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HrIntegrationSampleSeeder extends Seeder
{
    public function run(): void
    {
        $employees = DB::table('employees')->get();
        if ($employees->isEmpty()) {
            return;
        }

        $empMap = $employees->keyBy('employee_code');
        $admin = $employees->first();

        // ── 1. Populate Sample HR1 Credentials ────────────────────────────────────
        $sampleCredentials = [
            ['EMP-0001', 'PRC Registered Nurse License', 'RN-0829141', 'Professional Regulation Commission', -400, 365 * 2],
            ['EMP-0001', 'BLS Healthcare Provider', 'BLS-2025-912', 'Philippine Heart Association', -180, 25], // Expiring soon
            ['EMP-0001', 'ACLS Advanced Provider', 'ACLS-2024-411', 'American Heart Association', -300, 400],
            ['EMP-0001', 'IV Therapy Nurse Certification', 'IVT-ANSAP-6612', 'ANSAP Philippines', -200, 500],
            ['EMP-0002', 'PRC Registered Nurse License', 'RN-0994123', 'Professional Regulation Commission', -500, 200],
            ['EMP-0002', 'BLS Healthcare Provider', 'BLS-2023-118', 'Philippine Heart Association', -730, -14], // Expired
            ['EMP-0002', 'IV Therapy Nurse Certification', 'IVT-ANSAP-8819', 'ANSAP Philippines', -400, 320],
            ['EMP-0003', 'PRC Physician License', 'MD-0551920', 'Professional Regulation Commission', -800, 600],
            ['EMP-0003', 'ACLS Advanced Provider', 'ACLS-2024-882', 'Philippine Heart Association', -200, 18], // Expiring soon
            ['EMP-0003', 'Diplomate Emergency Medicine', 'DEM-PBEM-331', 'Philippine Board of Emergency Medicine', -600, 900],
            ['EMP-0004', 'PRC Pharmacist License', 'RPH-0412891', 'Professional Regulation Commission', -450, 450],
            ['EMP-0004', 'Sterile Compounding Certification', 'SCC-PAP-102', 'Philippine Pharmacists Association', -300, 12], // Expiring soon
            ['EMP-0005', 'Certified HR Professional', 'CHRP-PH-2021', 'Human Resources Development Institute', -700, 800],
            ['EMP-0007', 'Certified Quality Healthcare Professional', 'CQHP-JCI-91', 'Healthcare Quality Association', -350, 550],
            ['EMP-0008', 'PRC Registered Nurse License', 'RN-1149201', 'Professional Regulation Commission', -600, 700],
            ['EMP-0008', 'BLS Healthcare Provider', 'BLS-2023-909', 'Philippine Heart Association', -750, -5], // Expired
        ];

        $credCount = 0;
        foreach ($sampleCredentials as [$code, $type, $number, $body, $issueDays, $expiryDays]) {
            $emp = $empMap->get($code) ?? $employees->first();
            $issueDate = now()->addDays($issueDays)->toDateString();
            $expiryDate = now()->addDays($expiryDays)->toDateString();

            $existing = DB::table('employee_credentials')
                ->where('employee_id', $emp->employee_id)
                ->where('credential_type', $type)
                ->first();

            if (! $existing) {
                $credId = (string) Str::uuid();
                DB::table('employee_credentials')->insert([
                    'credential_id' => $credId,
                    'employee_id' => $emp->employee_id,
                    'credential_type' => $type,
                    'credential_number' => Crypt::encryptString($number),
                    'issuing_body' => $body,
                    'issue_date' => $issueDate,
                    'expiry_date' => $expiryDate,
                    'verified_by' => $admin->employee_id,
                    'verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $credCount++;
            }
        }

        // ── 2. Populate Sample HR2 Competency Framework & Assessments ──────────
        $domains = [
            [
                'name' => 'Clinical Safety & Governance',
                'description' => 'Hospital clinical standards, infection control, and JCI safety goals.',
                'categories' => [
                    [
                        'name' => 'Infection Prevention & Control',
                        'jci' => 'PCI.5',
                        'competencies' => [
                            ['COMP-IPC-01', 'Aseptic Technique & Hand Hygiene Protocols', 5, true, 'Adherence to hospital standard infection prevention.'],
                            ['COMP-IPC-02', 'Isolation Protocols & PPE Management', 4, true, 'Strict transmission-based isolation compliance.'],
                            ['COMP-IPC-03', 'Central Line & Surgical Site Sterile Care', 4, false, 'Sterile field maintenance during invasive procedures.'],
                        ],
                    ],
                    [
                        'name' => 'Patient Safety & Clinical Risk',
                        'jci' => 'IPSG.1',
                        'competencies' => [
                            ['COMP-IPS-01', 'Two-Identifier Patient Verification', 5, true, 'Standard patient identification prior to interventions.'],
                            ['COMP-IPS-02', 'High-Alert Medication Double-Check Protocol', 5, true, 'Independent double verification of high-risk drugs.'],
                            ['COMP-IPS-03', 'Early Warning Deterioration Escalation (MEWS)', 4, false, 'Timely escalation of clinically unstable patients.'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Emergency & Critical Care',
                'description' => 'Life-support protocols, resuscitation, and rapid response standards.',
                'categories' => [
                    [
                        'name' => 'Resuscitation Protocols',
                        'jci' => 'COP.3',
                        'competencies' => [
                            ['COMP-RES-01', 'Code Blue & Rapid Response Activation', 5, true, 'Execution of emergency resuscitation algorithms.'],
                            ['COMP-RES-02', 'Automated Defibrillation & Cardioversion', 4, true, 'Safe defibrillator operation during cardiac emergencies.'],
                            ['COMP-RES-03', 'Airway Management & Bag-Mask Ventilation', 4, false, 'Emergency airway opening and oxygen titration.'],
                        ],
                    ],
                ],
            ],
        ];

        $compList = [];
        foreach ($domains as $dData) {
            $domain = DB::table('competency_domains')->where('domain_name', $dData['name'])->first();
            if (! $domain) {
                $domainId = (string) Str::uuid();
                DB::table('competency_domains')->insert([
                    'domain_id' => $domainId,
                    'domain_name' => $dData['name'],
                    'description' => $dData['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $domainId = $domain->domain_id;
            }

            foreach ($dData['categories'] as $catData) {
                $cat = DB::table('competency_categories')
                    ->where('domain_id', $domainId)
                    ->where('category_name', $catData['name'])
                    ->first();

                if (! $cat) {
                    $catId = (string) Str::uuid();
                    DB::table('competency_categories')->insert([
                        'category_id' => $catId,
                        'domain_id' => $domainId,
                        'category_name' => $catData['name'],
                        'jci_standard_code' => $catData['jci'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $catId = $cat->category_id;
                }

                foreach ($catData['competencies'] as [$cCode, $cName, $cProf, $cMand, $cDesc]) {
                    $c = DB::table('competencies')->where('competency_code', $cCode)->first();
                    if (! $c) {
                        $compObjId = (string) Str::uuid();
                        DB::table('competencies')->insert([
                            'competency_id' => $compObjId,
                            'category_id' => $catId,
                            'competency_code' => $cCode,
                            'competency_name' => $cName,
                            'description' => $cDesc,
                            'required_proficiency' => $cProf,
                            'is_mandatory' => $cMand,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $c = (object) ['competency_id' => $compObjId, 'required_proficiency' => $cProf];
                    }
                    $compList[] = $c;
                }
            }
        }

        // Seed assessments for employees
        $assessmentCount = 0;
        foreach ($employees->take(6) as $emp) {
            foreach ($compList as $comp) {
                $existingAss = DB::table('competency_assessments')
                    ->where('employee_id', $emp->employee_id)
                    ->where('competency_id', $comp->competency_id)
                    ->first();

                if (! $existingAss) {
                    $score = rand(3, 5);
                    $gap = $score - $comp->required_proficiency;
                    DB::table('competency_assessments')->insert([
                        'assessment_id' => (string) Str::uuid(),
                        'employee_id' => $emp->employee_id,
                        'competency_id' => $comp->competency_id,
                        'assessed_by' => $emp->supervisor_id ?: $admin->employee_id,
                        'assessment_method' => 'supervisor_rating',
                        'current_proficiency' => $score,
                        'gap' => $gap,
                        'notes' => 'Automated assessment synchronized from HR2 Talent & Development.',
                        'assessed_date' => now()->subMonths(rand(1, 6))->toDateString(),
                        'next_assessment_due' => now()->addMonths(rand(6, 12))->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $assessmentCount++;
                }
            }
        }

        // ── 3. Keep system_integrations in Awaiting Configuration State ───────
        DB::table('system_integrations')->where('system_code', 'hr1')->update([
            'base_url' => null,
            'api_key' => null,
            'is_active' => false,
            'last_synced_at' => null,
            'last_sync_status' => 'idle',
            'last_sync_message' => 'Pending API configuration. Ready to connect when HR1 endpoint is provided.',
            'synced_records_count' => 0,
            'updated_at' => now(),
        ]);

        DB::table('system_integrations')->where('system_code', 'hr2')->update([
            'base_url' => null,
            'api_key' => null,
            'is_active' => false,
            'last_synced_at' => null,
            'last_sync_status' => 'idle',
            'last_sync_message' => 'Pending API configuration. Ready to connect when HR2 endpoint is provided.',
            'synced_records_count' => 0,
            'updated_at' => now(),
        ]);
    }
}
