<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Announcement;
use App\Services\RecipientResolverService;
use App\Services\NotificationDispatchService;
use Exception;

class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function handle(): void
    {
        $announcement = $this->announcement->fresh();

        if ($announcement->status !== 'scheduled' && $announcement->status !== 'draft') {
            return;
        }

        $creator = $announcement->creator;

        try {
            // 1. Resolve recipients
            $recipients = RecipientResolverService::resolveAnnouncementRecipients($announcement, $creator);

            // 2. Dispatch notifications to all resolved recipients
            $templateKey = $announcement->church_id ? 'parish_announcement' : 'diocese_announcement';
            $data = [
                'title' => $announcement->title,
                'body_content' => $announcement->body
            ];

            // In-app and email channels
            $channels = ['in_app', 'email'];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                $templateKey,
                $data,
                $channels,
                'announcement',
                null,
                $creator,
                $announcement->id
            );

            // 3. Mark announcement as sent
            $announcement->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);
        } catch (Exception $e) {
            $announcement->update([
                'status' => 'draft', // Return to draft so admin can inspect or retry
            ]);
            throw $e;
        }
    }
}
