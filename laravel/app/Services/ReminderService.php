<?php

namespace App\Services;

use App\Models\ScheduledReminder;
use App\Jobs\ProcessScheduledReminderJob;
use App\Services\AuditLogService;
use Exception;

class ReminderService
{
    public static function createReminder(array $data, $creator)
    {
        $reminder = ScheduledReminder::create([
            'diocese_id' => $data['diocese_id'] ?? 1,
            'church_id' => $data['church_id'] ?? null,
            'reminder_type' => $data['reminder_type'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'channel' => $data['channel'] ?? 'email',
            'status' => 'scheduled',
            'created_by' => $creator->id
        ]);

        AuditLogService::log(
            'Communications',
            'Reminder Created',
            "Scheduled a reminder: {$reminder->title} for {$reminder->scheduled_at}",
            null,
            $reminder->toArray(),
            $reminder,
            $creator->id,
            $reminder->diocese_id
        );

        return $reminder;
    }

    public static function processDueReminders()
    {
        $due = ScheduledReminder::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $processedCount = 0;
        foreach ($due as $reminder) {
            $reminder->update(['status' => 'processing']);
            dispatch(new ProcessScheduledReminderJob($reminder));
            $processedCount++;
        }

        return $processedCount;
    }

    public static function cancelReminder(ScheduledReminder $reminder, $user)
    {
        if ($reminder->status !== 'scheduled') {
            throw new Exception("Only scheduled reminders can be cancelled.");
        }

        $oldValues = $reminder->toArray();
        $reminder->update(['status' => 'cancelled']);

        AuditLogService::log(
            'Communications',
            'Reminder Cancelled',
            "Cancelled scheduled reminder: {$reminder->title}",
            $oldValues,
            $reminder->toArray(),
            $reminder,
            $user->id,
            $reminder->diocese_id
        );

        return $reminder;
    }
}
