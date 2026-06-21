<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReminderService;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'communications:process-reminders';
    protected $description = 'Process and send due scheduled reminders';

    public function handle()
    {
        $this->info('Processing due reminders...');
        $count = ReminderService::processDueReminders();
        $this->info("Successfully processed {$count} reminders.");
        return 0;
    }
}
