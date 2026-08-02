<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the competency framework, job-role requirements, KPI library, and a
 * realistic spread of assessments and credentials.
 *
 * Without this data the Competency module, the dashboard skills-gap panels and
 * the AI gap analysis all render empty: the tables exist but nothing fills them.
 *
 * Safe to re-run — every insert is guarded on its natural key.
 */
class CompetencyFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $competencies = $this->seedFramework();
        $this->seedRoleRequirements($competencies);
        $this->seedKpiLibrary();
        $this->seedAssessments($competencies);
        $this->seedCredentials();
        $this->seedReviewScores();

        $this->command?->info('   Competency framework, KPIs, assessments and credentials seeded.');
    }

    /* ─────────────────────── framework definition ─────────────────────── */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function framework(): array
    {
        return [
            [
                'domain'      => 'Clinical Care',
                'description' => 'Direct patient care competencies required of clinical staff.',
                'categories'  => [
                    [
                        'name' => 'Patient Assessment',
                        'jci'  => 'AOP.1',
                        'competencies' => [
                            ['code' => 'CLN-ASMT-01', 'name' => 'Initial Patient Assessment',        'required' => 4, 'mandatory' => true,  'desc' => 'Complete and document a structured initial assessment within required timeframes.'],
                            ['code' => 'CLN-ASMT-02', 'name' => 'Pain Assessment & Management',      'required' => 4, 'mandatory' => true,  'desc' => 'Assess, document and escalate pain using validated scales.'],
                            ['code' => 'CLN-ASMT-03', 'name' => 'Early Warning Score Recognition',   'required' => 4, 'mandatory' => false, 'desc' => 'Recognise and escalate deteriorating patients using MEWS.'],
                        ],
                    ],
                    [
                        'name' => 'Medication Management',
                        'jci'  => 'MMU.4',
                        'competencies' => [
                            ['code' => 'CLN-MED-01', 'name' => 'Safe Medication Administration', 'required' => 5, 'mandatory' => true,  'desc' => 'Apply the rights of medication administration without deviation.'],
                            ['code' => 'CLN-MED-02', 'name' => 'High-Alert Medication Handling', 'required' => 5, 'mandatory' => true,  'desc' => 'Independent double-check and safe handling of high-alert drugs.'],
                            ['code' => 'CLN-MED-03', 'name' => 'IV Therapy & Titration',         'required' => 4, 'mandatory' => false, 'desc' => 'Prepare, administer and titrate intravenous therapy safely.'],
                        ],
                    ],
                    [
                        'name' => 'Emergency Response',
                        'jci'  => 'COP.3',
                        'competencies' => [
                            ['code' => 'CLN-EMR-01', 'name' => 'Basic Life Support',          'required' => 5, 'mandatory' => true,  'desc' => 'Perform BLS to current AHA guidelines.'],
                            ['code' => 'CLN-EMR-02', 'name' => 'Advanced Cardiac Life Support','required' => 4, 'mandatory' => false, 'desc' => 'Lead or participate in ACLS resuscitation.'],
                            ['code' => 'CLN-EMR-03', 'name' => 'Code Blue Team Coordination',  'required' => 4, 'mandatory' => false, 'desc' => 'Coordinate roles and communication during a code.'],
                        ],
                    ],
                ],
            ],
            [
                'domain'      => 'Patient Safety & Quality',
                'description' => 'Competencies underpinning JCI patient-safety goals.',
                'categories'  => [
                    [
                        'name' => 'Infection Prevention',
                        'jci'  => 'PCI.5',
                        'competencies' => [
                            ['code' => 'QPS-IPC-01', 'name' => 'Hand Hygiene Compliance',        'required' => 5, 'mandatory' => true,  'desc' => 'Apply WHO five moments for hand hygiene.'],
                            ['code' => 'QPS-IPC-02', 'name' => 'Isolation Precautions',          'required' => 4, 'mandatory' => true,  'desc' => 'Select and apply correct transmission-based precautions.'],
                            ['code' => 'QPS-IPC-03', 'name' => 'Sterile Technique',              'required' => 4, 'mandatory' => false, 'desc' => 'Maintain a sterile field during invasive procedures.'],
                        ],
                    ],
                    [
                        'name' => 'Risk & Incident Management',
                        'jci'  => 'QPS.8',
                        'competencies' => [
                            ['code' => 'QPS-RSK-01', 'name' => 'Incident Reporting',        'required' => 4, 'mandatory' => true,  'desc' => 'Report adverse events and near misses promptly and factually.'],
                            ['code' => 'QPS-RSK-02', 'name' => 'Root Cause Analysis',       'required' => 3, 'mandatory' => false, 'desc' => 'Participate in structured RCA and contribute corrective actions.'],
                            ['code' => 'QPS-RSK-03', 'name' => 'Patient Identification',    'required' => 5, 'mandatory' => true,  'desc' => 'Use two identifiers before every intervention.'],
                        ],
                    ],
                ],
            ],
            [
                'domain'      => 'Communication & Professionalism',
                'description' => 'Interpersonal competencies required of all hospital staff.',
                'categories'  => [
                    [
                        'name' => 'Clinical Communication',
                        'jci'  => 'IPSG.2',
                        'competencies' => [
                            ['code' => 'COM-CLN-01', 'name' => 'SBAR Handover',              'required' => 4, 'mandatory' => true,  'desc' => 'Deliver structured handover using SBAR.'],
                            ['code' => 'COM-CLN-02', 'name' => 'Patient & Family Education', 'required' => 4, 'mandatory' => false, 'desc' => 'Explain care plans in language the patient understands.'],
                            ['code' => 'COM-CLN-03', 'name' => 'Difficult Conversations',    'required' => 3, 'mandatory' => false, 'desc' => 'Handle complaints and breaking bad news with empathy.'],
                        ],
                    ],
                    [
                        'name' => 'Documentation',
                        'jci'  => 'MOI.9',
                        'competencies' => [
                            ['code' => 'COM-DOC-01', 'name' => 'Clinical Documentation Accuracy', 'required' => 4, 'mandatory' => true,  'desc' => 'Document care completely, legibly and contemporaneously.'],
                            ['code' => 'COM-DOC-02', 'name' => 'Health Records Confidentiality', 'required' => 5, 'mandatory' => true,  'desc' => 'Apply Data Privacy Act requirements to patient information.'],
                        ],
                    ],
                ],
            ],
            [
                'domain'      => 'Leadership & Management',
                'description' => 'Supervisory and management competencies for senior roles.',
                'categories'  => [
                    [
                        'name' => 'People Leadership',
                        'jci'  => 'GLD.3',
                        'competencies' => [
                            ['code' => 'LDR-PPL-01', 'name' => 'Team Supervision',       'required' => 4, 'mandatory' => false, 'desc' => 'Allocate work, supervise and support a clinical team.'],
                            ['code' => 'LDR-PPL-02', 'name' => 'Performance Coaching',   'required' => 4, 'mandatory' => false, 'desc' => 'Conduct constructive performance conversations.'],
                            ['code' => 'LDR-PPL-03', 'name' => 'Conflict Resolution',    'required' => 3, 'mandatory' => false, 'desc' => 'Mediate interpersonal conflict within the team.'],
                        ],
                    ],
                    [
                        'name' => 'Operational Management',
                        'jci'  => 'GLD.7',
                        'competencies' => [
                            ['code' => 'LDR-OPS-01', 'name' => 'Staff Rostering & Skill Mix', 'required' => 4, 'mandatory' => false, 'desc' => 'Build rosters that meet safe skill-mix requirements.'],
                            ['code' => 'LDR-OPS-02', 'name' => 'Resource & Budget Awareness', 'required' => 3, 'mandatory' => false, 'desc' => 'Manage supplies and staffing within budget.'],
                        ],
                    ],
                ],
            ],
            [
                'domain'      => 'Digital & Information Literacy',
                'description' => 'Competencies for working safely with hospital information systems.',
                'categories'  => [
                    [
                        'name' => 'Health Information Systems',
                        'jci'  => 'MOI.2',
                        'competencies' => [
                            ['code' => 'DIG-HIS-01', 'name' => 'EMR Navigation & Data Entry', 'required' => 4, 'mandatory' => true,  'desc' => 'Use the electronic medical record accurately and efficiently.'],
                            ['code' => 'DIG-HIS-02', 'name' => 'Information Security Practice','required' => 4, 'mandatory' => true,  'desc' => 'Apply password, access and phishing-awareness practices.'],
                            ['code' => 'DIG-HIS-03', 'name' => 'Clinical Data Reporting',      'required' => 3, 'mandatory' => false, 'desc' => 'Extract and interpret basic clinical quality reports.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string,string>  competency_code => competency_id
     */
    private function seedFramework(): array
    {
        foreach ($this->framework() as $domain) {
            $domainId = $this->ensureDomain($domain['domain'], $domain['description']);

            foreach ($domain['categories'] as $category) {
                $categoryId = $this->ensureCategory($domainId, $category['name'], $category['jci']);

                foreach ($category['competencies'] as $competency) {
                    $this->ensureCompetency($categoryId, $competency);
                }
            }
        }

        return DB::table('competencies')
            ->whereNotNull('competency_code')
            ->pluck('competency_id', 'competency_code')
            ->all();
    }

    private function ensureDomain(string $name, string $description): string
    {
        $existing = DB::table('competency_domains')->where('domain_name', $name)->value('domain_id');

        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();

        DB::table('competency_domains')->insert([
            'domain_id'   => $id,
            'domain_name' => $name,
            'description' => $description,
            'created_at'  => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function ensureCategory(string $domainId, string $name, ?string $jci): string
    {
        $existing = DB::table('competency_categories')
            ->where('domain_id', $domainId)
            ->where('category_name', $name)
            ->value('category_id');

        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();

        DB::table('competency_categories')->insert([
            'category_id'       => $id,
            'domain_id'         => $domainId,
            'category_name'     => $name,
            'jci_standard_code' => $jci,
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $competency
     */
    private function ensureCompetency(string $categoryId, array $competency): void
    {
        if (DB::table('competencies')->where('competency_code', $competency['code'])->exists()) {
            return;
        }

        DB::table('competencies')->insert([
            'competency_id'        => (string) Str::uuid(),
            'category_id'          => $categoryId,
            'competency_name'      => $competency['name'],
            'competency_code'      => $competency['code'],
            'description'          => $competency['desc'],
            'required_proficiency' => $competency['required'],
            'is_mandatory'         => $competency['mandatory'],
            'created_at'           => now(), 'updated_at' => now(),
        ]);
    }

    /* ────────────────────── role competency requirements ────────────────── */

    /**
     * What each job role must demonstrate. This is what the gap analysis
     * measures an employee against.
     *
     * @param  array<string,string>  $competencies
     */
    private function seedRoleRequirements(array $competencies): void
    {
        $map = [
            'head_nurse' => [
                'CLN-ASMT-01' => [5, true],  'CLN-ASMT-02' => [4, false], 'CLN-ASMT-03' => [5, true],
                'CLN-MED-01'  => [5, true],  'CLN-MED-02'  => [5, true],  'CLN-EMR-01'  => [5, true],
                'CLN-EMR-02'  => [4, true],  'CLN-EMR-03'  => [5, true],
                'QPS-IPC-01'  => [5, true],  'QPS-RSK-01'  => [5, true],  'QPS-RSK-03' => [5, true],
                'COM-CLN-01'  => [5, true],  'COM-DOC-01'  => [4, false],
                'LDR-PPL-01'  => [5, true],  'LDR-PPL-02'  => [4, true],  'LDR-PPL-03' => [4, false],
                'LDR-OPS-01'  => [4, true],  'LDR-OPS-02'  => [3, false],
                'DIG-HIS-01'  => [4, false], 'DIG-HIS-03'  => [3, false],
            ],
            'staff_nurse' => [
                'CLN-ASMT-01' => [4, true],  'CLN-ASMT-02' => [4, true],  'CLN-ASMT-03' => [4, true],
                'CLN-MED-01'  => [5, true],  'CLN-MED-02'  => [4, true],  'CLN-MED-03'  => [4, false],
                'CLN-EMR-01'  => [5, true],
                'QPS-IPC-01'  => [5, true],  'QPS-IPC-02'  => [4, true],  'QPS-IPC-03' => [4, false],
                'QPS-RSK-01'  => [4, true],  'QPS-RSK-03'  => [5, true],
                'COM-CLN-01'  => [4, true],  'COM-CLN-02'  => [4, false], 'COM-DOC-01' => [4, true],
                'DIG-HIS-01'  => [4, true],  'DIG-HIS-02'  => [4, true],
            ],
            'em_physician' => [
                'CLN-ASMT-01' => [5, true],  'CLN-ASMT-03' => [5, true],
                'CLN-MED-01'  => [5, true],  'CLN-MED-02'  => [5, true],
                'CLN-EMR-01'  => [5, true],  'CLN-EMR-02'  => [5, true],  'CLN-EMR-03' => [5, true],
                'QPS-IPC-01'  => [5, true],  'QPS-RSK-01'  => [4, true],  'QPS-RSK-03' => [5, true],
                'COM-CLN-01'  => [5, true],  'COM-CLN-03'  => [4, false], 'COM-DOC-01' => [4, true],
                'DIG-HIS-01'  => [4, false],
            ],
            'pharmacist' => [
                'CLN-MED-01' => [5, true],  'CLN-MED-02' => [5, true],  'CLN-MED-03' => [4, false],
                'QPS-RSK-01' => [4, true],  'QPS-RSK-03' => [5, true],
                'COM-CLN-02' => [4, false], 'COM-DOC-01' => [4, true],  'COM-DOC-02' => [5, true],
                'DIG-HIS-01' => [4, true],  'DIG-HIS-02' => [4, true],
            ],
            'quality_analyst' => [
                'QPS-IPC-01' => [4, false], 'QPS-RSK-01' => [5, true], 'QPS-RSK-02' => [5, true],
                'QPS-RSK-03' => [4, false],
                'COM-DOC-01' => [4, true],  'COM-DOC-02' => [5, true],
                'DIG-HIS-01' => [4, true],  'DIG-HIS-03' => [5, true],
                'LDR-PPL-03' => [3, false],
            ],
            'hr_manager' => [
                'COM-CLN-03' => [4, true],  'COM-DOC-02' => [5, true],
                'LDR-PPL-01' => [5, true],  'LDR-PPL-02' => [5, true], 'LDR-PPL-03' => [4, true],
                'LDR-OPS-01' => [4, false], 'LDR-OPS-02' => [4, true],
                'DIG-HIS-02' => [4, true],  'DIG-HIS-03' => [4, false],
            ],
            'hr_officer' => [
                'COM-CLN-03' => [3, false], 'COM-DOC-01' => [4, true], 'COM-DOC-02' => [5, true],
                'LDR-PPL-02' => [3, false],
                'DIG-HIS-02' => [4, true],  'DIG-HIS-03' => [3, false],
            ],
            'system_admin' => [
                'COM-DOC-02' => [5, true],
                'DIG-HIS-01' => [5, true], 'DIG-HIS-02' => [5, true], 'DIG-HIS-03' => [4, true],
            ],
        ];

        $roles = DB::table('roles')->pluck('role_id', 'role_slug');

        foreach ($map as $slug => $requirements) {
            $roleId = $roles[$slug] ?? null;

            if (! $roleId) {
                continue;
            }

            foreach ($requirements as $code => [$minimum, $critical]) {
                $competencyId = $competencies[$code] ?? null;

                if (! $competencyId) {
                    continue;
                }

                $exists = DB::table('role_competency_requirements')
                    ->where('role_id', $roleId)
                    ->where('competency_id', $competencyId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('role_competency_requirements')->insert([
                    'id'                  => (string) Str::uuid(),
                    'role_id'             => $roleId,
                    'competency_id'       => $competencyId,
                    'minimum_proficiency' => $minimum,
                    'is_critical'         => $critical,
                ]);
            }
        }
    }

    /* ──────────────────────────── KPI library ──────────────────────────── */

    private function seedKpiLibrary(): void
    {
        $kpis = [
            ['Patient Satisfaction Score',        'quality',      'Average patient satisfaction rating for care delivered.',        4.50, 'rating', 1.00],
            ['Care Plan Documentation Timeliness','quality',      'Percentage of care plans documented within policy timeframe.',   95.00,'%',      0.90],
            ['Medication Error Rate',             'safety',       'Reported medication errors per 1,000 doses administered (lower is better).', 0.50, 'per 1k', 1.00],
            ['Hand Hygiene Audit Score',          'safety',       'Observed hand-hygiene compliance rate.',                          90.00,'%',      1.00],
            ['Incident Reporting Timeliness',     'safety',       'Incidents reported within 24 hours of occurrence.',               95.00,'%',      0.80],
            ['Mandatory Training Completion',     'development',  'Completion rate of assigned mandatory training.',                100.00,'%',      0.90],
            ['CPD Hours Attained',                'development',  'Continuing professional development hours in the review period.', 24.00,'hours',  0.70],
            ['Attendance & Punctuality',          'behaviour',    'Unplanned absence and lateness record.',                          98.00,'%',      0.60],
            ['Team Collaboration',                'behaviour',    'Peer-rated collaboration and support within the team.',            4.00,'rating', 0.80],
            ['Handover Quality (SBAR)',           'quality',      'Audited quality of clinical handover communication.',              4.00,'rating', 0.80],
            ['Supervision & Mentoring',           'leadership',   'Effectiveness in supervising and developing junior staff.',        4.00,'rating', 0.90],
            ['Roster & Skill-Mix Compliance',     'leadership',   'Shifts meeting required safe skill-mix.',                         95.00,'%',      0.80],
        ];

        foreach ($kpis as [$name, $category, $description, $target, $unit, $weight]) {
            if (DB::table('kpi_library')->where('kpi_name', $name)->exists()) {
                continue;
            }

            DB::table('kpi_library')->insert([
                'kpi_id'       => (string) Str::uuid(),
                'kpi_name'     => $name,
                'kpi_category' => $category,
                'description'  => $description,
                'target_value' => $target,
                'unit'         => $unit,
                'weight'       => $weight,
                'is_active'    => true,
                'created_at'   => now(), 'updated_at' => now(),
            ]);
        }
    }

    /* ──────────────────────────── assessments ──────────────────────────── */

    /**
     * Assess each employee against their own role requirements, leaving a
     * deliberate spread: most competencies met, some short, a few unassessed
     * so the gap analysis has something real to report.
     *
     * @param  array<string,string>  $competencies
     */
    private function seedAssessments(array $competencies): void
    {
        if (DB::table('competency_assessments')->count() > 0) {
            return;
        }

        $employees = DB::table('employees')
            ->select('employee_id', 'role_id', 'employee_code')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        // Deterministic per-employee shortfall pattern, so the seeded data
        // tells a consistent story rather than changing every run.
        $shortfallPattern = [
            'EMP-0001' => ['LDR-OPS-02' => -1, 'DIG-HIS-03' => -1],
            'EMP-0002' => ['CLN-MED-02' => -2, 'CLN-ASMT-03' => -1, 'DIG-HIS-02' => -1],
            'EMP-0003' => ['COM-CLN-03' => -1, 'QPS-RSK-01' => -1],
            'EMP-0004' => ['CLN-MED-03' => -2, 'DIG-HIS-01' => -1],
            'EMP-0005' => ['LDR-OPS-01' => -1],
            'EMP-0006' => ['COM-DOC-02' => -2, 'DIG-HIS-03' => -1],
            'EMP-0007' => ['QPS-RSK-02' => -1, 'DIG-HIS-03' => -2],
            'EMP-0008' => ['CLN-MED-01' => -2, 'CLN-EMR-01' => -1, 'QPS-IPC-02' => -1, 'COM-CLN-01' => -1],
        ];

        // Competencies intentionally left unassessed per employee.
        $skipPattern = [
            'EMP-0002' => ['CLN-MED-03'],
            'EMP-0008' => ['QPS-IPC-03', 'DIG-HIS-02'],
            'EMP-0003' => ['CLN-EMR-03'],
        ];

        $assessorByCode = [
            'EMP-0002' => 'EMP-0001',
            'EMP-0008' => 'EMP-0001',
            'EMP-0001' => 'EMP-0005',
            'EMP-0003' => 'EMP-0005',
            'EMP-0004' => 'EMP-0005',
            'EMP-0006' => 'EMP-0005',
            'EMP-0007' => 'EMP-0005',
            'EMP-0005' => 'EMP-0005',
        ];

        $employeeIdByCode = $employees->pluck('employee_id', 'employee_code');
        $codeByCompetencyId = array_flip($competencies);

        foreach ($employees as $employee) {
            $requirements = DB::table('role_competency_requirements as rcr')
                ->join('competencies as c', 'rcr.competency_id', '=', 'c.competency_id')
                ->where('rcr.role_id', $employee->role_id)
                ->select('rcr.competency_id', 'rcr.minimum_proficiency', 'c.required_proficiency', 'c.competency_code')
                ->get();

            $shortfalls = $shortfallPattern[$employee->employee_code] ?? [];
            $skips      = $skipPattern[$employee->employee_code] ?? [];

            $assessorCode = $assessorByCode[$employee->employee_code] ?? null;
            $assessedBy   = $employeeIdByCode[$assessorCode] ?? $employee->employee_id;

            $offset = 0;

            foreach ($requirements as $requirement) {
                $code = $requirement->competency_code;

                if (in_array($code, $skips, true)) {
                    continue;
                }

                $required = max((int) $requirement->minimum_proficiency, (int) $requirement->required_proficiency);
                $delta    = $shortfalls[$code] ?? 0;

                // Meets or slightly exceeds requirement unless deliberately short.
                $current = $delta !== 0
                    ? max(1, $required + $delta)
                    : min(5, $required + ($offset % 3 === 0 ? 1 : 0));

                DB::table('competency_assessments')->insert([
                    'assessment_id'       => (string) Str::uuid(),
                    'employee_id'         => $employee->employee_id,
                    'competency_id'       => $requirement->competency_id,
                    'assessed_by'         => $assessedBy,
                    'assessment_method'   => $offset % 4 === 0 ? 'observation' : ($offset % 3 === 0 ? 'exam' : 'supervisor'),
                    'current_proficiency' => $current,
                    // gap is computed by the trg_compute_gap_insert trigger.
                    'notes'               => $delta < 0
                        ? 'Below required level — development plan needed.'
                        : 'Meets required level for the role.',
                    'assessed_date'       => now()->subDays(30 + ($offset * 7) % 240)->toDateString(),
                    'next_assessment_due' => now()->addMonths(12)->toDateString(),
                    'created_at'          => now(), 'updated_at' => now(),
                ]);

                $offset++;
            }
        }
    }

    /* ──────────────────────────── credentials ──────────────────────────── */

    private function seedCredentials(): void
    {
        if (DB::table('employee_credentials')->count() > 0) {
            return;
        }

        $employees = DB::table('employees')->pluck('employee_id', 'employee_code');

        // [code, credential type, issuing body, days until expiry]
        // Negative and small values create the expired / expiring-soon alerts
        // the competency dashboard is built to surface.
        $credentials = [
            ['EMP-0001', 'PRC Nursing License',       'Professional Regulation Commission', 400],
            ['EMP-0001', 'BLS Certification',         'Philippine Heart Association',        20],
            ['EMP-0001', 'ACLS Certification',        'Philippine Heart Association',       180],
            ['EMP-0002', 'PRC Nursing License',       'Professional Regulation Commission', 520],
            ['EMP-0002', 'BLS Certification',         'Philippine Heart Association',       -15],
            ['EMP-0003', 'PRC Physician License',     'Professional Regulation Commission', 610],
            ['EMP-0003', 'ACLS Certification',        'Philippine Heart Association',        45],
            ['EMP-0004', 'PRC Pharmacist License',    'Professional Regulation Commission', 300],
            ['EMP-0004', 'IV Admixture Certification','Philippine Pharmacists Association',  12],
            ['EMP-0007', 'Quality Management Cert.',  'Philippine Society for Quality',     250],
            ['EMP-0008', 'PRC Nursing License',       'Professional Regulation Commission', 730],
            ['EMP-0008', 'BLS Certification',         'Philippine Heart Association',        -3],
        ];

        $verifier = $employees['EMP-0005'] ?? null;

        foreach ($credentials as $index => [$code, $type, $body, $daysToExpiry]) {
            $employeeId = $employees[$code] ?? null;

            if (! $employeeId) {
                continue;
            }

            $expiry = now()->addDays($daysToExpiry);

            DB::table('employee_credentials')->insert([
                'credential_id'     => (string) Str::uuid(),
                'employee_id'       => $employeeId,
                'credential_type'   => $type,
                'credential_number' => sprintf('%s-%06d', strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $type), 0, 3)), 100000 + $index * 371),
                'issuing_body'      => $body,
                'issue_date'        => $expiry->copy()->subYears(3)->toDateString(),
                'expiry_date'       => $expiry->toDateString(),
                // Expired ones are left unverified to look like a real backlog.
                'verified_by'       => $daysToExpiry > 0 ? $verifier : null,
                'verified_at'       => $daysToExpiry > 0 ? now()->subDays(60) : null,
                'created_at'        => now(), 'updated_at' => now(),
            ]);
        }
    }

    /* ──────────────────── KPI scores on the seeded review ───────────────── */

    /**
     * Give the seeded performance review real KPI scores so the review screen
     * and the gap analysis' "weak KPIs" signal have something to read.
     */
    private function seedReviewScores(): void
    {
        $review = DB::table('performance_reviews')->orderBy('created_at')->first();

        if (! $review || DB::table('review_kpi_scores')->where('review_id', $review->review_id)->exists()) {
            return;
        }

        $scores = [
            'Patient Satisfaction Score'         => [4.50, 4.25, 4.40],
            'Hand Hygiene Audit Score'           => [4.00, 4.50, 4.25],
            'Medication Error Rate'              => [4.75, 4.50, 4.60],
            'Mandatory Training Completion'      => [3.50, 3.25, 3.40],
            'Supervision & Mentoring'            => [4.25, 4.50, 4.30],
            'Roster & Skill-Mix Compliance'      => [3.25, 3.00, 3.10],
            'Handover Quality (SBAR)'            => [4.50, 4.25, 4.40],
        ];

        foreach ($scores as $kpiName => [$self, $supervisor, $peer]) {
            $kpiId = DB::table('kpi_library')->where('kpi_name', $kpiName)->value('kpi_id');

            if (! $kpiId) {
                continue;
            }

            DB::table('review_kpi_scores')->insert([
                'score_id'         => (string) Str::uuid(),
                'review_id'        => $review->review_id,
                'kpi_id'           => $kpiId,
                'self_score'       => $self,
                'supervisor_score' => $supervisor,
                'peer_score'       => $peer,
                // Same 50/30/20 weighting the controller applies.
                'weighted_score'   => round($supervisor * 0.5 + $self * 0.3 + $peer * 0.2, 2),
                'created_at'       => now(), 'updated_at' => now(),
            ]);
        }

        $weighted = DB::table('review_kpi_scores as rks')
            ->join('kpi_library as k', 'rks.kpi_id', '=', 'k.kpi_id')
            ->where('rks.review_id', $review->review_id)
            ->select('rks.self_score', 'rks.supervisor_score', 'rks.peer_score', 'rks.weighted_score', 'k.weight')
            ->get();

        $sum = 0.0;
        $totalWeight = 0.0;

        foreach ($weighted as $row) {
            $w = (float) ($row->weight ?: 1);
            $sum += (float) $row->weighted_score * $w;
            $totalWeight += $w;
        }

        DB::table('performance_reviews')->where('review_id', $review->review_id)->update([
            'self_rating'       => round((float) $weighted->avg('self_score'), 2),
            'supervisor_rating' => round((float) $weighted->avg('supervisor_score'), 2),
            'peer_rating'       => round((float) $weighted->avg('peer_score'), 2),
            'overall_score'     => $totalWeight > 0 ? round($sum / $totalWeight, 2) : null,
            'status'            => 'supervisor_review',
            'strengths_text'    => 'Consistently strong clinical judgement and calm leadership during high-acuity periods. Peers rate handover quality highly.',
            'improvements_text' => 'Mandatory training completion and roster skill-mix compliance are below target and should be the focus for the next quarter.',
            'updated_at'        => now(),
        ]);
    }
}
