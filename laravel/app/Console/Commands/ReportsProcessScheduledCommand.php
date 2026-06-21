<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScheduledReportService;

class ReportsProcessScheduledCommand extends Command
{
    protected $signature = 'reports:process-scheduled';
    protected $description = 'Process and execute due scheduled reports and notify recipients';

    public function handle()
    {
        $this->info('Processing scheduled reports...');
        $count = ScheduledReportService::processScheduledReports();
        $this->info("Successfully processed {$count} scheduled reports.");
        return 0;
    }
}
