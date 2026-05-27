<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class ProcessScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:process-scheduled';

    protected $description = 'Start scheduled WhatsApp blast campaigns that are due';

    public function handle(CampaignService $campaignService): int
    {
        $count = $campaignService->processDueScheduledCampaigns();

        $this->info("Started {$count} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
