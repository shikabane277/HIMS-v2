<?php

namespace App\Console\Commands;

use App\Services\HrIntegrationService;
use Illuminate\Console\Command;

class SyncHrIntegrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hims:sync-hr 
                            {--system=all : System to synchronize (all, hr1, hr2)} 
                            {--sample : Populate sample demonstration records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize employee credentials from HR1 and competency assessments from HR2 APIs into the database';

    /**
     * Execute the console command.
     */
    public function handle(HrIntegrationService $service): int
    {
        $system = strtolower((string) $this->option('system'));
        $useSample = (bool) $this->option('sample');

        $this->info("Starting external HR integration synchronization (Target: {$system})...");

        if ($useSample) {
            $this->warn('Sample demo mode activated: synthesizing test payloads.');
            $options = [
                'payload' => [
                    'competencies' => [
                        [
                            'domain_name' => 'Clinical Excellence',
                            'category_name' => 'Patient Safety',
                            'competency_code' => 'COMP-JCI-01',
                            'competency_name' => 'Patient Identification and Safety Goals',
                            'required_proficiency' => 4,
                            'description' => 'Strict adherence to Joint Commission International patient safety goals.',
                            'is_mandatory' => true,
                        ],
                        [
                            'domain_name' => 'Clinical Excellence',
                            'category_name' => 'Infection Prevention',
                            'competency_code' => 'COMP-JCI-02',
                            'competency_name' => 'Aseptic Technique & Hand Hygiene',
                            'required_proficiency' => 4,
                            'description' => 'Hospital-wide infection control and sterile procedure protocols.',
                            'is_mandatory' => true,
                        ],
                    ],
                    'assessments' => [],
                ],
            ];
            $result = $service->sync($system, $options);
        } else {
            $result = $service->sync($system);
        }

        if ($result['success']) {
            $this->info('✓ '.$result['message']);

            return self::SUCCESS;
        }

        $this->error('✗ '.$result['message']);

        return self::FAILURE;
    }
}
