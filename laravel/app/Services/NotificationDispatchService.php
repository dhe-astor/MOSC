<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Jobs\SendNotificationEmailJob;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationTemplateService;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;

class NotificationDispatchService
{
    public static function dispatchInApp(array $recipient, string $title, string $body, string $type, ?string $actionUrl = null, ?array $metadata = null, $createdBy = null, $announcementId = null)
    {
        if ($recipient['recipient_type'] === 'external') {
            return null;
        }

        $notifiableType = $recipient['recipient_type'] === 'user' ? \App\Models\User::class : \App\Models\Member::class;
        $notifiableId = $recipient['recipient_id'];

        $notification = Notification::create([
            'diocese_id' => $recipient['diocese_id'] ?? 1,
            'church_id' => $recipient['church_id'] ?? null,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'title' => $title,
            'body' => $body,
            'notification_type' => $type,
            'channel' => 'in_app',
            'status' => 'delivered',
            'action_url' => $actionUrl,
            'metadata' => $metadata,
            'created_by' => $createdBy ? $createdBy->id : null
        ]);

        NotificationDelivery::create([
            'notification_id' => $notification->id,
            'announcement_id' => $announcementId,
            'recipient_type' => $recipient['recipient_type'],
            'recipient_id' => $recipient['recipient_id'],
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_email' => $recipient['email'] ?? null,
            'recipient_phone' => $recipient['phone'] ?? null,
            'channel' => 'in_app',
            'delivery_status' => 'delivered',
            'attempt_count' => 1,
            'last_attempt_at' => now(),
            'sent_at' => now(),
            'delivered_at' => now()
        ]);

        return $notification;
    }

    public static function dispatchEmail(array $recipient, string $subject, string $body, string $type, ?string $actionUrl = null, ?array $metadata = null, $createdBy = null, $notificationId = null, $announcementId = null)
    {
        if (empty($recipient['email'])) {
            // Cannot send email without email address
            return null;
        }

        $delivery = NotificationDelivery::create([
            'notification_id' => $notificationId,
            'announcement_id' => $announcementId,
            'recipient_type' => $recipient['recipient_type'],
            'recipient_id' => $recipient['recipient_id'] ?? null,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_email' => $recipient['email'],
            'recipient_phone' => $recipient['phone'] ?? null,
            'channel' => 'email',
            'delivery_status' => 'queued',
            'attempt_count' => 0
        ]);

        // Dispatch job to the queue
        dispatch(new SendNotificationEmailJob($delivery, $subject, $body));

        return $delivery;
    }

    public static function dispatchToRecipients(array $recipients, string $templateKey, array $data, array $channels, string $type, ?string $actionUrl = null, $createdBy = null, $announcementId = null)
    {
        $resolvedCount = 0;

        foreach ($recipients as $recipient) {
            foreach ($channels as $channel) {
                // Check preferences first
                if (!NotificationPreferenceService::canSend($recipient, $channel, $type)) {
                    // Log a skipped delivery
                    NotificationDelivery::create([
                        'announcement_id' => $announcementId,
                        'recipient_type' => $recipient['recipient_type'],
                        'recipient_id' => $recipient['recipient_id'] ?? null,
                        'recipient_name' => $recipient['name'] ?? null,
                        'recipient_email' => $recipient['email'] ?? null,
                        'recipient_phone' => $recipient['phone'] ?? null,
                        'channel' => $channel,
                        'delivery_status' => 'skipped',
                        'error_message' => 'Skipped due to user preference settings.',
                        'attempt_count' => 0
                    ]);
                    continue;
                }

                // Render template
                $template = NotificationTemplate::where('diocese_id', $recipient['diocese_id'] ?? 1)
                    ->where('template_key', $templateKey)
                    ->where('channel', $channel)
                    ->where('status', 'active')
                    ->first();

                $subject = '';
                $body = '';

                if ($template) {
                    $rendered = NotificationTemplateService::renderTemplate($template->body, $template->subject, $data);
                    $subject = $rendered['subject'];
                    $body = $rendered['body'];
                } else {
                    // fallback if template not found
                    $subject = str_replace('_', ' ', ucfirst($templateKey));
                    $body = "Notification for: " . json_encode($data);
                }

                if ($channel === 'in_app') {
                    self::dispatchInApp($recipient, $subject, $body, $type, $actionUrl, $data, $createdBy, $announcementId);
                } elseif ($channel === 'email') {
                    // Optional: Create an in_app notification record first for tracking email content in notifications table
                    $notif = null;
                    if ($recipient['recipient_type'] !== 'external') {
                        $notifiableType = $recipient['recipient_type'] === 'user' ? \App\Models\User::class : \App\Models\Member::class;
                        $notif = Notification::create([
                            'diocese_id' => $recipient['diocese_id'] ?? 1,
                            'church_id' => $recipient['church_id'] ?? null,
                            'notifiable_type' => $notifiableType,
                            'notifiable_id' => $recipient['recipient_id'],
                            'title' => $subject,
                            'body' => $body,
                            'notification_type' => $type,
                            'channel' => 'email',
                            'status' => 'queued',
                            'action_url' => $actionUrl,
                            'metadata' => $data,
                            'created_by' => $createdBy ? $createdBy->id : null
                        ]);
                    }

                    self::dispatchEmail(
                        $recipient, 
                        $subject, 
                        $body, 
                        $type, 
                        $actionUrl, 
                        $data, 
                        $createdBy, 
                        $notif ? $notif->id : null, 
                        $announcementId
                    );
                }
                $resolvedCount++;
            }
        }

        return $resolvedCount;
    }

    public static function retryDelivery(NotificationDelivery $delivery, $user = null)
    {
        if ($delivery->delivery_status !== 'failed') {
            throw new Exception("Only failed deliveries can be retried.");
        }

        if ($delivery->attempt_count >= 3) {
            throw new Exception("Maximum retry limit of 3 attempts exceeded.");
        }

        $delivery->update([
            'delivery_status' => 'queued',
            'error_message' => null,
            'last_attempt_at' => now()
        ]);

        // Fetch subject and body from the notification metadata/template or fallback
        $subject = 'Notification Update';
        $body = 'Please refer to the portal for details.';

        if ($delivery->notification_id) {
            $notification = $delivery->notification;
            if ($notification) {
                $subject = $notification->title;
                $body = $notification->body;
            }
        } elseif ($delivery->announcement_id) {
            $announcement = $delivery->announcement;
            if ($announcement) {
                $subject = $announcement->title;
                $body = $announcement->body;
            }
        }

        dispatch(new SendNotificationEmailJob($delivery, $subject, $body));

        AuditLogService::log(
            'Communications',
            'Retry Notification Delivery',
            "Triggered retry for delivery ID: {$delivery->id} (Channel: {$delivery->channel})",
            null,
            $delivery->toArray(),
            $delivery,
            $user ? $user->id : null,
            $delivery->notification?->diocese_id ?? 1
        );

        return $delivery;
    }

    public static function markAsRead(Notification $notification, $user)
    {
        if ($notification->notifiable_id != $user->id && !($notification->notifiable_type === \App\Models\Member::class && $user->member && $notification->notifiable_id == $user->member->id)) {
            throw new Exception("Unauthorized to mark this notification as read.");
        }

        $notification->update([
            'status' => 'read',
            'read_at' => now()
        ]);

        return $notification;
    }

    public static function markAllAsRead($user)
    {
        $notifiableId = $user->id;
        $notifiableType = \App\Models\User::class;

        Notification::where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->whereNull('read_at')
            ->update([
                'status' => 'read',
                'read_at' => now()
            ]);

        if ($user->member) {
            Notification::where('notifiable_type', \App\Models\Member::class)
                ->where('notifiable_id', $user->member->id)
                ->whereNull('read_at')
                ->update([
                    'status' => 'read',
                    'read_at' => now()
                ]);
        }
    }
}
