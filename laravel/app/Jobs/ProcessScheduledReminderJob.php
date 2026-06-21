<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ScheduledReminder;
use App\Services\RecipientResolverService;
use App\Services\NotificationDispatchService;
use Exception;

class ProcessScheduledReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reminder;

    public function __construct(ScheduledReminder $reminder)
    {
        $this->reminder = $reminder;
    }

    public function handle(): void
    {
        $reminder = $this->reminder->fresh();

        if ($reminder->status !== 'scheduled' && $reminder->status !== 'processing') {
            return;
        }

        $reminder->update(['status' => 'processing']);

        try {
            $recipients = [];
            $templateKey = '';
            $type = 'reminder';

            // Custom variables depending on template
            $data = [
                'title' => $reminder->title,
                'event_title' => $reminder->title,
                'event_date' => $reminder->scheduled_at->toDateString(),
                'course_name' => $reminder->title,
                'session_time' => $reminder->scheduled_at->toTimeString(),
                'exam_title' => $reminder->title,
                'exam_date' => $reminder->scheduled_at->toDateString(),
                'activity_title' => $reminder->title,
                'activity_date' => $reminder->scheduled_at->toDateString(),
                'body_content' => $reminder->body ?? 'Reminder notice.',
                'amount' => '0.00',
                'currency' => 'EUR',
                'description' => $reminder->title,
                'approval_url' => '#'
            ];

            switch ($reminder->reminder_type) {
                case 'event':
                    $recipients = RecipientResolverService::resolveEventParticipants($reminder->related_id);
                    $templateKey = 'event_reminder';
                    break;
                case 'course':
                    $recipients = RecipientResolverService::resolveCourseBatchParticipants($reminder->related_id);
                    $templateKey = 'course_session_reminder';
                    break;
                case 'sunday_school_exam':
                    $recipients = RecipientResolverService::resolveSundaySchoolParents($reminder->related_id);
                    $templateKey = 'sunday_school_exam_published';
                    break;
                case 'ministry_activity':
                    $recipients = RecipientResolverService::resolveMinistryUnitMembers($reminder->related_id);
                    $templateKey = 'ministry_activity_reminder';
                    break;
                case 'finance_approval':
                    $recipients = RecipientResolverService::resolveRoleUsers('Priest / Vicar');
                    if ($reminder->church_id) {
                        $recipients = array_filter($recipients, fn($r) => $r['church_id'] == $reminder->church_id);
                    }
                    $templateKey = 'finance_expense_approval_requested';
                    $type = 'finance';
                    break;
                case 'cms_approval':
                    $recipients = RecipientResolverService::resolveRoleUsers('Diocese PRO');
                    $templateKey = 'cms_content_approval_requested';
                    $type = 'cms';
                    break;
                case 'certificate':
                    if ($reminder->related_type === \App\Models\Member::class) {
                        $recipients = RecipientResolverService::resolveSingleMember($reminder->related_id);
                    } elseif ($reminder->related_type === \App\Models\Family::class) {
                        $recipients = RecipientResolverService::resolveFamilyMembers($reminder->related_id);
                    }
                    $templateKey = 'certificate_issued';
                    $type = 'certificate';
                    break;
                default:
                    // custom
                    $templateKey = 'parish_announcement';
                    break;
            }

            // In-app and email channels
            $channels = ['in_app', 'email'];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                $templateKey,
                $data,
                $channels,
                $type,
                null,
                $reminder->creator,
                null
            );

            $reminder->update([
                'status' => 'sent',
                'processed_at' => now()
            ]);
        } catch (Exception $e) {
            $reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
