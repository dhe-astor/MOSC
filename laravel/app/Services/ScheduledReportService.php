<?php

namespace App\Services;

use App\Models\ScheduledReport;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\ReportExportService;
use App\Services\NotificationDispatchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduledReportService
{
    /**
     * Run all active scheduled reports that are currently due.
     */
    public static function processScheduledReports(): int
    {
        $now = Carbon::now();
        $dueReports = ScheduledReport::where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        $processedCount = 0;

        foreach ($dueReports as $scheduled) {
            try {
                $creator = User::find($scheduled->created_by);
                if (!$creator) {
                    Log::warning("Scheduled report {$scheduled->id} has no valid creator; skipping.");
                    continue;
                }

                // 1. Create a ReportRun
                $run = ReportRun::create([
                    'diocese_id' => $scheduled->diocese_id ?? $creator->default_diocese_id ?? 1,
                    'church_id' => $scheduled->church_id,
                    'report_definition_id' => $scheduled->report_definition_id,
                    'saved_report_id' => $scheduled->saved_report_id,
                    'report_key' => $scheduled->definition->report_key,
                    'filters' => $scheduled->savedReport?->filters ?? $scheduled->definition->default_filters ?? [],
                    'status' => 'processing',
                    'generated_by' => $creator->id,
                    'started_at' => Carbon::now(),
                ]);

                // 2. Generate private Export
                $export = ReportExportService::createExport($run, $scheduled->export_type, $creator);

                // 3. Mark completed
                $run->update([
                    'status' => 'completed',
                    'row_count' => $export->file_size ? 1 : 0, // Placeholder
                    'completed_at' => Carbon::now(),
                ]);

                // 4. Notify recipients
                self::notifyRecipients($scheduled, $creator);

                // 5. Update schedule times
                $scheduled->last_run_at = Carbon::now();
                $scheduled->next_run_at = self::calculateNextRun($scheduled->frequency);
                $scheduled->save();

                $processedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to run scheduled report {$scheduled->id}: " . $e->getMessage());
                if (isset($run)) {
                    $run->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => Carbon::now(),
                    ]);
                }
            }
        }

        return $processedCount;
    }

    /**
     * Notify report recipients that it is ready for download.
     */
    private static function notifyRecipients(ScheduledReport $scheduled, User $sender): void
    {
        $recipientIds = $scheduled->recipients ?? [];
        if (empty($recipientIds)) {
            return;
        }

        $users = User::whereIn('id', $recipientIds)->get();
        $recipients = $users->map(function ($u) {
            return [
                'recipient_type' => 'user',
                'recipient_id' => $u->id,
                'email' => $u->email,
                'name' => $u->name,
                'diocese_id' => $u->default_diocese_id ?? 1,
            ];
        })->toArray();

        NotificationDispatchService::dispatchToRecipients(
            $recipients,
            'scheduled_report_ready',
            [
                'user_name' => 'Recipient',
                'report_name' => $scheduled->name,
            ],
            ['in_app', 'email'],
            'system',
            null,
            $sender
        );
    }

    /**
     * Calculate the next execution time based on frequency.
     */
    private static function calculateNextRun(string $frequency): Carbon
    {
        $now = Carbon::now();
        switch ($frequency) {
            case 'daily':
                return $now->addDay();
            case 'weekly':
                return $now->addWeek();
            case 'monthly':
                return $now->addMonth();
            case 'quarterly':
                return $now->addMonths(3);
            case 'yearly':
                return $now->addYear();
            default:
                return $now->addDay();
        }
    }
}
