<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReportsCleanupExpiredExportsCommand extends Command
{
    protected $signature = 'reports:cleanup-expired-exports';
    protected $description = 'Cleanup and delete physical files for expired report exports';

    public function handle()
    {
        $this->info('Cleaning up expired report exports...');
        $now = Carbon::now();
        
        $expiredExports = ReportExport::where('status', 'generated')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expiredExports as $export) {
            if (Storage::exists($export->file_path)) {
                Storage::delete($export->file_path);
            }
            $export->update(['status' => 'expired']);
            $count++;
        }

        $this->info("Successfully expired and cleaned up {$count} exports.");
        return 0;
    }
}
