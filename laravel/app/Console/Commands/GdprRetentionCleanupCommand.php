<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\ReportExport;
use App\Models\NotificationDelivery;
use App\Models\MemberPortalActivityLog;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Carbon\Carbon;

class GdprRetentionCleanupCommand extends Command
{
    protected $signature = 'gdpr:cleanup-retention';
    protected $description = 'Clean up expired data records and files according to GDPR data retention rules';

    public function handle()
    {
        $this->info('Starting GDPR data retention cleanup...');
        $now = Carbon::now();

        // 1. Report Exports (> 7 days)
        $expiredExports = ReportExport::where('status', 'generated')
            ->where('expires_at', '<=', $now)
            ->get();
        $exportsCount = 0;
        foreach ($expiredExports as $export) {
            if (Storage::exists($export->file_path)) {
                Storage::delete($export->file_path);
            }
            $export->update(['status' => 'expired']);
            $exportsCount++;
        }

        // 2. Temporary Uploads (> 30 days)
        $tempFilesDeleted = 0;
        $tempPath = 'private/tmp';
        if (Storage::exists($tempPath)) {
            $files = Storage::files($tempPath);
            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                if (Carbon::createFromTimestamp($lastModified)->diffInDays($now) > 30) {
                    Storage::delete($file);
                    $tempFilesDeleted++;
                }
            }
        }

        // 3. Failed Jobs (> 30 days)
        $failedJobsDeleted = DB::table('failed_jobs')
            ->where('failed_at', '<=', $now->copy()->subDays(30))
            ->delete();

        // 4. Notification Delivery Logs (> 2 years)
        $notificationsDeleted = NotificationDelivery::where('created_at', '<=', $now->copy()->subYears(2))
            ->delete();

        // 5. Portal Activity Logs (> 3 years)
        $portalLogsDeleted = MemberPortalActivityLog::where('created_at', '<=', $now->copy()->subYears(3))
            ->delete();

        // 6. Audit Logs (> 7 years)
        $auditLogsDeleted = AuditLog::where('created_at', '<=', $now->copy()->subYears(7))
            ->delete();

        $message = "GDPR Cleanup complete: Expired {$exportsCount} exports, deleted {$tempFilesDeleted} temp files, {$failedJobsDeleted} failed jobs, {$notificationsDeleted} notification logs, {$portalLogsDeleted} portal logs, and {$auditLogsDeleted} audit logs.";
        
        $this->info($message);

        // Record audit log for cleanup
        AuditLogService::log(
            'system',
            'gdpr_cleanup',
            $message
        );

        return 0;
    }
}
