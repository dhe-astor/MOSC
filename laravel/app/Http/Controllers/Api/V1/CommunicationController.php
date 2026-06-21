<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\ScheduledReminder;
use App\Services\ChurchAccessService;
use App\Services\NotificationTemplateService;
use App\Services\RecipientResolverService;
use App\Services\NotificationDispatchService;
use App\Services\AnnouncementService;
use App\Services\ReminderService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Exception;

class CommunicationController extends Controller
{
    // =========================================================================
    // Notification Templates
    // =========================================================================
    public function listTemplates(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_notification_templates')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $templates = NotificationTemplate::where('diocese_id', $request->user()->default_diocese_id ?? 1)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function storeTemplate(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_notification_templates')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'template_key' => 'required|string',
            'name' => 'required|string',
            'channel' => 'required|in:in_app,email,sms,whatsapp',
            'subject' => 'nullable|string',
            'body' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['diocese_id'] = $request->user()->default_diocese_id ?? 1;
            $template = NotificationTemplateService::createTemplate($data, $request->user());
            return response()->json(['success' => true, 'data' => $template], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function showTemplate(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_notification_templates')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $template = NotificationTemplate::findOrFail($id);
        return response()->json(['success' => true, 'data' => $template]);
    }

    public function updateTemplate(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_notification_templates')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $template = NotificationTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'subject' => 'nullable|string',
            'body' => 'required|string',
            'variables' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,archived'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $template = NotificationTemplateService::updateTemplate($template, $request->all(), $request->user());
            return response()->json(['success' => true, 'data' => $template]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function archiveTemplate(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_notification_templates')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $template = NotificationTemplate::findOrFail($id);

        try {
            $template = NotificationTemplateService::archiveTemplate($template, $request->user());
            return response()->json(['success' => true, 'data' => $template]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================================
    // Announcements
    // =========================================================================
    public function listAnnouncements(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = Announcement::with('targets');
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $announcements = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $announcements]);
    }

    public function storeAnnouncement(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'body' => 'required|string',
            'church_id' => 'nullable|integer',
            'announcement_type' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'visibility' => 'nullable|in:internal,members,public',
            'targets' => 'required|array',
            'targets.*.target_type' => 'required|string',
            'targets.*.target_id' => 'nullable|integer',
            'targets.*.filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['diocese_id'] = $request->user()->default_diocese_id ?? 1;
            $announcement = AnnouncementService::createAnnouncement($data, $request->user());
            return response()->json(['success' => true, 'data' => $announcement], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function showAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::with('targets')->findOrFail($id);
        if ($announcement->church_id && !ChurchAccessService::canAccessChurch($request->user(), $announcement->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $announcement]);
    }

    public function updateAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'body' => 'required|string',
            'announcement_type' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'visibility' => 'nullable|in:internal,members,public',
            'targets' => 'nullable|array',
            'targets.*.target_type' => 'required|string',
            'targets.*.target_id' => 'nullable|integer',
            'targets.*.filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $announcement = AnnouncementService::updateAnnouncement($announcement, $request->all(), $request->user());
            return response()->json(['success' => true, 'data' => $announcement]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function sendAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        try {
            $announcement = AnnouncementService::sendAnnouncement($announcement, $request->user());
            return response()->json(['success' => true, 'data' => $announcement]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function scheduleAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $announcement = AnnouncementService::scheduleAnnouncement($announcement, $request->input('scheduled_at'), $request->user());
            return response()->json(['success' => true, 'data' => $announcement]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $announcement = AnnouncementService::cancelAnnouncement($announcement, $request->input('cancellation_reason'), $request->user());
            return response()->json(['success' => true, 'data' => $announcement]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function archiveAnnouncement(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        try {
            $announcement = AnnouncementService::archiveAnnouncement($announcement, $request->user());
            return response()->json(['success' => true, 'data' => $announcement]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function previewTargets(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_announcements')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'targets' => 'required|array',
            'targets.*.target_type' => 'required|string',
            'targets.*.target_id' => 'nullable|integer',
            'targets.*.filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Mock announcement for resolution
        $announcement = new Announcement([
            'diocese_id' => $request->user()->default_diocese_id ?? 1,
            'church_id' => $request->input('church_id')
        ]);
        
        $targets = [];
        foreach ($request->input('targets') as $t) {
            $targets[] = new AnnouncementTarget([
                'target_type' => $t['target_type'],
                'target_id' => $t['target_id'] ?? null,
                'filters' => $t['filters'] ?? null
            ]);
        }
        $announcement->setRelation('targets', collect($targets));

        $recipients = RecipientResolverService::resolveAnnouncementRecipients($announcement, $request->user());

        $hasUnmaskedPermission = $request->user()->hasPermissionTo('view_unmasked_notification_recipients');

        if ($hasUnmaskedPermission) {
            return response()->json([
                'success' => true,
                'data' => [
                    'estimated_recipients' => count($recipients),
                    'channels' => ['in_app', 'email'],
                    'recipients' => $recipients
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'estimated_recipients' => count($recipients),
                'channels' => ['in_app', 'email']
            ]
        ]);
    }

    // =========================================================================
    // In-App Notifications (Inbox)
    // =========================================================================
    public function listNotifications(Request $request)
    {
        $notifiableId = $request->user()->id;
        $notifiableType = \App\Models\User::class;

        $query1 = Notification::where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId);

        // Also fetch member-related notifications if user is associated with a member profile
        if ($request->user()->member) {
            $query2 = Notification::where('notifiable_type', \App\Models\Member::class)
                ->where('notifiable_id', $request->user()->member->id);
            
            $query1->union($query2);
        }

        $notifications = $query1->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function showNotification(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        // Access check: must be owner of notification
        if ($notification->notifiable_id != $request->user()->id && !($notification->notifiable_type === \App\Models\Member::class && $request->user()->member && $notification->notifiable_id == $request->user()->member->id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $notification]);
    }

    public function markRead(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);
        try {
            $notification = NotificationDispatchService::markAsRead($notification, $request->user());
            return response()->json(['success' => true, 'data' => $notification]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function markAllRead(Request $request)
    {
        NotificationDispatchService::markAllAsRead($request->user());
        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    // =========================================================================
    // Delivery Logs
    // =========================================================================
    public function listDeliveries(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_notification_logs')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = NotificationDelivery::query();

        // Scoping: Parish Secretary / Parish Admin can see logs for their church members only
        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        if ($accessibleChurchIds !== null) {
            $query->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        if ($request->has('status')) {
            $query->where('delivery_status', $request->input('status'));
        }

        $deliveries = $query->orderBy('created_at', 'desc')->get();
        $hasUnmaskedPermission = $request->user()->hasPermissionTo('view_unmasked_notification_recipients');

        $mapped = $deliveries->map(fn($d) => $this->maskRecipient($d, $hasUnmaskedPermission, $request->user()));

        return response()->json(['success' => true, 'data' => $mapped]);
    }

    public function showDelivery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('view_notification_logs')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $delivery = NotificationDelivery::findOrFail($id);
        $hasUnmaskedPermission = $request->user()->hasPermissionTo('view_unmasked_notification_recipients');

        return response()->json(['success' => true, 'data' => $this->maskRecipient($delivery, $hasUnmaskedPermission, $request->user())]);
    }

    public function retryDelivery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('retry_failed_notifications')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $delivery = NotificationDelivery::findOrFail($id);

        try {
            $delivery = NotificationDispatchService::retryDelivery($delivery, $request->user());
            return response()->json(['success' => true, 'data' => $delivery]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================================
    // Preferences
    // =========================================================================
    public function getPreferences(Request $request)
    {
        $target = $request->user();
        if ($request->has('member_id') && $request->user()->member && $request->input('member_id') == $request->user()->member->id) {
            $target = $request->user()->member;
        }

        $prefs = NotificationPreferenceService::getPreferences($target);
        return response()->json(['success' => true, 'data' => $prefs]);
    }

    public function updatePreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:in_app,email,sms,whatsapp',
            'notification_type' => 'required|string',
            'is_enabled' => 'required|boolean',
            'member_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $target = $request->user();
        if ($request->has('member_id') && $request->user()->member && $request->input('member_id') == $request->user()->member->id) {
            $target = $request->user()->member;
        }

        $pref = NotificationPreferenceService::updatePreference(
            $target,
            $request->input('channel'),
            $request->input('notification_type'),
            $request->input('is_enabled'),
            $request->user()
        );

        return response()->json(['success' => true, 'data' => $pref]);
    }

    // =========================================================================
    // Scheduled Reminders
    // =========================================================================
    public function listReminders(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_scheduled_reminders')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = ScheduledReminder::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        $reminders = $query->orderBy('scheduled_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $reminders]);
    }

    public function storeReminder(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_scheduled_reminders')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reminder_type' => 'required|in:event,course,sunday_school_exam,certificate,finance_approval,cms_approval,ministry_activity,custom',
            'church_id' => 'nullable|integer',
            'title' => 'required|string',
            'body' => 'nullable|string',
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s',
            'channel' => 'nullable|in:in_app,email,sms,whatsapp',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['diocese_id'] = $request->user()->default_diocese_id ?? 1;
            $reminder = ReminderService::createReminder($data, $request->user());
            return response()->json(['success' => true, 'data' => $reminder], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function showReminder(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_scheduled_reminders')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $reminder = ScheduledReminder::findOrFail($id);
        if ($reminder->church_id && !ChurchAccessService::canAccessChurch($request->user(), $reminder->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $reminder]);
    }

    public function updateReminder(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_scheduled_reminders')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $reminder = ScheduledReminder::findOrFail($id);
        if ($reminder->church_id && !ChurchAccessService::canAccessChurch($request->user(), $reminder->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($reminder->status !== 'scheduled') {
            return response()->json(['success' => false, 'message' => 'Only scheduled reminders can be updated.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'body' => 'nullable|string',
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s',
            'channel' => 'nullable|in:in_app,email,sms,whatsapp'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $reminder->update($request->only(['title', 'body', 'scheduled_at', 'channel']));
        return response()->json(['success' => true, 'data' => $reminder]);
    }

    public function cancelReminder(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_scheduled_reminders')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $reminder = ScheduledReminder::findOrFail($id);

        try {
            $reminder = ReminderService::cancelReminder($reminder, $request->user());
            return response()->json(['success' => true, 'data' => $reminder]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================================
    // Communication Reports
    // =========================================================================
    public function reportsOverview(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_communication_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        
        $deliveryQuery = NotificationDelivery::query();
        $notificationQuery = Notification::query();
        $reminderQuery = ScheduledReminder::query();
        $announcementQuery = Announcement::query();

        if ($accessibleChurchIds !== null) {
            $notificationQuery->whereIn('church_id', $accessibleChurchIds);
            $reminderQuery->whereIn('church_id', $accessibleChurchIds);
            $announcementQuery->whereIn('church_id', $accessibleChurchIds);
            
            $deliveryQuery->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        $totalSent = (clone $deliveryQuery)->where('delivery_status', 'delivered')->count();
        $totalFailed = (clone $deliveryQuery)->where('delivery_status', 'failed')->count();
        $successRate = ($totalSent + $totalFailed) > 0 ? round(($totalSent / ($totalSent + $totalFailed)) * 100, 2) : 100;

        return response()->json([
            'success' => true,
            'data' => [
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
                'success_rate' => $successRate,
                'scheduled_reminders' => $reminderQuery->where('status', 'scheduled')->count(),
                'pending_announcements' => $announcementQuery->where('status', 'scheduled')->count(),
                'unread_notifications' => $notificationQuery->whereNull('read_at')->count(),
            ]
        ]);
    }

    public function reportsDeliveryStatus(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_communication_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = NotificationDelivery::query();
        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        if ($accessibleChurchIds !== null) {
            $query->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        $statusCounts = $query->groupBy('delivery_status')
            ->selectRaw('delivery_status, count(*) as count')
            ->pluck('count', 'delivery_status')
            ->toArray();

        return response()->json(['success' => true, 'data' => $statusCounts]);
    }

    public function reportsFailedDeliveries(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_communication_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = NotificationDelivery::where('delivery_status', 'failed');
        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        if ($accessibleChurchIds !== null) {
            $query->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        $failed = $query->orderBy('created_at', 'desc')->get();
        $hasUnmaskedPermission = $request->user()->hasPermissionTo('view_unmasked_notification_recipients');

        $mapped = $failed->map(fn($d) => $this->maskRecipient($d, $hasUnmaskedPermission, $request->user()));

        return response()->json(['success' => true, 'data' => $mapped]);
    }

    public function reportsByChannel(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_communication_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = NotificationDelivery::query();
        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        if ($accessibleChurchIds !== null) {
            $query->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        $channelCounts = $query->groupBy('channel')
            ->selectRaw('channel, count(*) as count')
            ->pluck('count', 'channel')
            ->toArray();

        return response()->json(['success' => true, 'data' => $channelCounts]);
    }

    public function reportsExport(Request $request)
    {
        if (!$request->user()->hasPermissionTo('export_communication_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = NotificationDelivery::query();
        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        if ($accessibleChurchIds !== null) {
            $query->where(function($q) use ($accessibleChurchIds) {
                $q->whereHas('notification', function($nq) use ($accessibleChurchIds) {
                    $nq->whereIn('church_id', $accessibleChurchIds);
                })->orWhereHas('announcement', function($aq) use ($accessibleChurchIds) {
                    $aq->whereIn('church_id', $accessibleChurchIds);
                });
            });
        }

        $deliveries = $query->get();
        $hasUnmaskedPermission = $request->user()->hasPermissionTo('view_unmasked_notification_recipients');

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=communications_report.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($deliveries, $hasUnmaskedPermission, $request) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Channel', 'Recipient Type', 'Recipient Name', 'Recipient Email', 'Recipient Phone', 'Delivery Status', 'Attempts', 'Error Message', 'Created At']);

            foreach ($deliveries as $d) {
                $masked = $this->maskRecipient($d, $hasUnmaskedPermission, $request->user());
                fputcsv($file, [
                    $masked['id'],
                    $masked['channel'],
                    $masked['recipient_type'],
                    $masked['recipient_name'],
                    $masked['recipient_email'],
                    $masked['recipient_phone'],
                    $masked['delivery_status'],
                    $masked['attempt_count'],
                    $masked['error_message'],
                    $masked['created_at']
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // Inner Helpers
    // =========================================================================
    protected function maskRecipient($delivery, $hasUnmaskedPermission, $user)
    {
        $data = $delivery->toArray();
        
        $canAccessFullDetails = $hasUnmaskedPermission;
        if ($canAccessFullDetails && $delivery->recipient_type === 'member' && $delivery->recipient_id) {
            $member = \App\Models\Member::find($delivery->recipient_id);
            if ($member && !ChurchAccessService::canAccessChurch($user, $member->church_id)) {
                $canAccessFullDetails = false;
            }
        }

        if (!$canAccessFullDetails) {
            if ($delivery->recipient_email) {
                $parts = explode('@', $delivery->recipient_email);
                if (count($parts) === 2) {
                    $name = $parts[0];
                    $domain = $parts[1];
                    $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1));
                    $data['recipient_email'] = $maskedName . '@' . $domain;
                }
            }
            if ($delivery->recipient_phone) {
                $phone = $delivery->recipient_phone;
                if (strlen($phone) > 6) {
                    $data['recipient_phone'] = substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 7) . substr($phone, -4);
                } else {
                    $data['recipient_phone'] = '***';
                }
            }
        }
        return $data;
    }
}
