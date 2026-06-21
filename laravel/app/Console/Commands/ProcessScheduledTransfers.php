<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClergyTransferService;
use App\Models\User;

class ProcessScheduledTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clergy:process-scheduled-transfers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled priest transfers that are effective today or earlier';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled priest transfer processing...');

        // Find a system user or superadmin to complete the transfers
        $systemUser = User::where('email', 'superadmin@msoc-europe.org')->first();
        if (!$systemUser) {
            // Fallback to first user
            $systemUser = User::first();
        }

        if (!$systemUser) {
            $this->error('No users found in database to act as system actor.');
            return 1;
        }

        $count = ClergyTransferService::processScheduledTransfers($systemUser);

        $this->info("Successfully processed {$count} scheduled transfers.");
        return 0;
    }
}
