<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Jobs\SendAnnouncementJob;
use App\Services\AuditLogService;
use Exception;

class AnnouncementService
{
    public static function createAnnouncement(array $data, $creator)
    {
        if (isset($data['priority']) && $data['priority'] === 'urgent' && !$creator->hasPermissionTo('send_urgent_announcements')) {
            throw new Exception("You do not have permission to create urgent announcements.");
        }

        // Enforce parish scoping rules
        if (isset($data['church_id']) && $data['church_id']) {
            if (!ChurchAccessService::canAccessChurch($creator, $data['church_id'])) {
                throw new Exception("You do not have access to create announcements for this parish.");
            }
        } else {
            // Diocese level announcement requires send_diocese_announcements
            if (!$creator->hasPermissionTo('send_diocese_announcements')) {
                throw new Exception("You do not have permission to create diocese-wide announcements.");
            }
        }

        $announcement = Announcement::create([
            'diocese_id' => $data['diocese_id'] ?? 1,
            'church_id' => $data['church_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'announcement_type' => $data['announcement_type'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal',
            'visibility' => $data['visibility'] ?? 'members',
            'status' => 'draft',
            'created_by' => $creator->id,
        ]);

        if (isset($data['targets']) && is_array($data['targets'])) {
            self::addTargets($announcement, $data['targets']);
        }

        AuditLogService::log(
            'Communications',
            'Announcement Created',
            "Created announcement draft: {$announcement->title}",
            null,
            $announcement->toArray(),
            $announcement,
            $creator->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function updateAnnouncement(Announcement $announcement, array $data, $user)
    {
        if ($announcement->status !== 'draft' && $announcement->status !== 'scheduled') {
            throw new Exception("Only draft or scheduled announcements can be updated.");
        }

        if (isset($data['priority']) && $data['priority'] === 'urgent' && !$user->hasPermissionTo('send_urgent_announcements')) {
            throw new Exception("You do not have permission to set urgent priority on announcements.");
        }

        // Scoping checks
        if ($announcement->church_id && !ChurchAccessService::canAccessChurch($user, $announcement->church_id)) {
            throw new Exception("Forbidden");
        }

        $oldValues = $announcement->toArray();

        $announcement->update([
            'title' => $data['title'] ?? $announcement->title,
            'body' => $data['body'] ?? $announcement->body,
            'announcement_type' => $data['announcement_type'] ?? $announcement->announcement_type,
            'priority' => $data['priority'] ?? $announcement->priority,
            'visibility' => $data['visibility'] ?? $announcement->visibility,
        ]);

        if (isset($data['targets']) && is_array($data['targets'])) {
            // Re-populate targets
            $announcement->targets()->delete();
            self::addTargets($announcement, $data['targets']);
        }

        AuditLogService::log(
            'Communications',
            'Announcement Updated',
            "Updated announcement: {$announcement->title}",
            $oldValues,
            $announcement->toArray(),
            $announcement,
            $user->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function addTargets(Announcement $announcement, array $targets)
    {
        foreach ($targets as $t) {
            AnnouncementTarget::create([
                'announcement_id' => $announcement->id,
                'target_type' => $t['target_type'],
                'target_id' => $t['target_id'] ?? null,
                'filters' => $t['filters'] ?? null,
            ]);
        }
    }

    public static function sendAnnouncement(Announcement $announcement, $user)
    {
        if ($announcement->status !== 'draft' && $announcement->status !== 'scheduled') {
            throw new Exception("Only draft or scheduled announcements can be sent.");
        }

        if ($announcement->church_id && !ChurchAccessService::canAccessChurch($user, $announcement->church_id)) {
            throw new Exception("Forbidden");
        }

        // Trigger job to send asynchronously
        dispatch(new SendAnnouncementJob($announcement));

        $announcement->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);

        AuditLogService::log(
            'Communications',
            'Announcement Sent',
            "Sent announcement: {$announcement->title}",
            null,
            $announcement->toArray(),
            $announcement,
            $user->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function scheduleAnnouncement(Announcement $announcement, string $scheduledAt, $user)
    {
        if ($announcement->status !== 'draft') {
            throw new Exception("Only draft announcements can be scheduled.");
        }

        if ($announcement->church_id && !ChurchAccessService::canAccessChurch($user, $announcement->church_id)) {
            throw new Exception("Forbidden");
        }

        $oldValues = $announcement->toArray();
        $announcement->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt
        ]);

        AuditLogService::log(
            'Communications',
            'Announcement Scheduled',
            "Scheduled announcement: {$announcement->title} for {$scheduledAt}",
            $oldValues,
            $announcement->toArray(),
            $announcement,
            $user->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function cancelAnnouncement(Announcement $announcement, string $reason, $user)
    {
        if ($announcement->status !== 'scheduled') {
            throw new Exception("Only scheduled announcements can be cancelled.");
        }

        if ($announcement->church_id && !ChurchAccessService::canAccessChurch($user, $announcement->church_id)) {
            throw new Exception("Forbidden");
        }

        $oldValues = $announcement->toArray();
        $announcement->update([
            'status' => 'cancelled',
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);

        AuditLogService::log(
            'Communications',
            'Announcement Cancelled',
            "Cancelled scheduled announcement: {$announcement->title}. Reason: {$reason}",
            $oldValues,
            $announcement->toArray(),
            $announcement,
            $user->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function archiveAnnouncement(Announcement $announcement, $user)
    {
        $oldValues = $announcement->toArray();
        $announcement->update(['status' => 'archived']);

        AuditLogService::log(
            'Communications',
            'Announcement Archived',
            "Archived announcement: {$announcement->title}",
            $oldValues,
            $announcement->toArray(),
            $announcement,
            $user->id,
            $announcement->diocese_id
        );

        return $announcement;
    }

    public static function processScheduledAnnouncements()
    {
        $due = Announcement::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $processedCount = 0;
        foreach ($due as $announcement) {
            $announcement->update(['status' => 'sent', 'sent_at' => now()]);
            dispatch(new SendAnnouncementJob($announcement));
            $processedCount++;
        }

        return $processedCount;
    }
}
