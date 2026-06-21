<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AnnouncementService;

class ProcessScheduledAnnouncementsCommand extends Command
{
    protected $signature = 'communications:process-scheduled-announcements';
    protected $description = 'Process and send due scheduled announcements';

    public function handle()
    {
        $this->info('Processing due scheduled announcements...');
        $count = AnnouncementService::processScheduledAnnouncements();
        $this->info("Successfully processed {$count} announcements.");
        return 0;
    }
}
