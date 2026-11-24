<?php

namespace App\Console\Commands;

use App\Services\AwardService;
use Illuminate\Console\Command;

/**
 * Issues automatic awards (service milestones, CPD achievement) with their
 * certificates and badges. Safe to run repeatedly: each award is given once.
 */
class RunAutomaticAwards extends Command
{
    protected $signature = 'hims:auto-awards';

    protected $description = 'Issue automatic recognition awards (service milestones, CPD achievement)';

    public function handle(AwardService $awards): int
    {
        $issued = $awards->runAutomaticAwards();

        if (! $issued) {
            $this->info('No new automatic awards due.');

            return self::SUCCESS;
        }

        foreach ($issued as $name => $count) {
            $this->line("{$name}: {$count} issued");
        }

        return self::SUCCESS;
    }
}
