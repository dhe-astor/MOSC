<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Services\NotificationDispatchService;
use App\Services\RecipientResolverService;
use Exception;
use Illuminate\Support\Facades\Log;

class NotificationTriggerService
{
    // =========================================================================
    // Certificate Triggers
    // =========================================================================
    public static function triggerCertificateIssued($certificate)
    {
        self::runNonBlocking(function() use ($certificate) {
            $member = $certificate->member;
            if (!$member) return;

            $recipient = self::formatMemberRecipient($member);
            $data = [
                'member_name' => $member->full_name,
                'certificate_number' => $certificate->certificate_number,
                'church_name' => $certificate->church?->name ?? 'Parish'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'certificate_issued',
                $data,
                ['in_app', 'email'],
                'certificate',
                null,
                $certificate->issuer
            );
        });
    }

    public static function triggerCertificateRequestSubmitted($request)
    {
        self::runNonBlocking(function() use ($request) {
            $member = $request->member;
            if (!$member) return;

            // Notify Priest / Vicar of the church
            $recipients = RecipientResolverService::resolveRoleUsers('Priest / Vicar');
            if ($request->church_id) {
                $recipients = array_filter($recipients, fn($r) => $r['church_id'] == $request->church_id);
            }

            $data = [
                'member_name' => $member->full_name,
                'church_name' => $request->church?->name ?? 'Parish'
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'certificate_request_submitted',
                $data,
                ['in_app', 'email'],
                'approval',
                null,
                $request->requester
            );
        });
    }

    public static function triggerCertificateRequestApproved($request)
    {
        self::runNonBlocking(function() use ($request) {
            $member = $request->member;
            if (!$member) return;

            $recipient = self::formatMemberRecipient($member);
            $data = [
                'member_name' => $member->full_name,
                'church_name' => $request->church?->name ?? 'Parish'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'certificate_request_approved',
                $data,
                ['in_app', 'email'],
                'certificate',
                null,
                $request->approver
            );
        });
    }

    public static function triggerCertificateRequestRejected($request, string $reason)
    {
        self::runNonBlocking(function() use ($request, $reason) {
            $member = $request->member;
            if (!$member) return;

            $recipient = self::formatMemberRecipient($member);
            $data = [
                'member_name' => $member->full_name,
                'rejection_reason' => $reason
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'certificate_request_rejected',
                $data,
                ['in_app', 'email'],
                'certificate',
                null,
                $request->approver
            );
        });
    }

    // =========================================================================
    // Course Triggers
    // =========================================================================
    public static function triggerCourseRegistrationConfirmed($registration)
    {
        self::runNonBlocking(function() use ($registration) {
            $recipient = $registration->member_id 
                ? self::formatMemberRecipient($registration->member)
                : self::formatExternalRecipient($registration);

            $data = [
                'member_name' => $registration->member_id ? $registration->member->full_name : $registration->external_name,
                'course_name' => $registration->batch?->course?->name ?? 'Course'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'course_registration_confirmed',
                $data,
                ['email'], // externals can only receive email
                'course'
            );
        });
    }

    public static function triggerCourseBatchOpened($batch)
    {
        self::runNonBlocking(function() use ($batch) {
            // Notify all diocese members about new course batch
            $recipients = RecipientResolverService::resolveAllMembers($batch->diocese_id);
            $data = [
                'course_name' => $batch->course?->name ?? 'Course'
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'course_batch_opened',
                $data,
                ['in_app', 'email'],
                'course',
                null,
                $batch->creator
            );
        });
    }

    public static function triggerCourseSessionReminder($session, $batch)
    {
        self::runNonBlocking(function() use ($session, $batch) {
            $recipients = RecipientResolverService::resolveCourseBatchParticipants($batch->id);
            $data = [
                'course_name' => $batch->course?->name ?? 'Course',
                'session_time' => $session->scheduled_at ?? now()->toTimeString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'course_session_reminder',
                $data,
                ['in_app', 'email'],
                'reminder'
            );
        });
    }

    public static function triggerCourseCertificateIssued($registration)
    {
        self::runNonBlocking(function() use ($registration) {
            $recipient = $registration->member_id 
                ? self::formatMemberRecipient($registration->member)
                : self::formatExternalRecipient($registration);

            $data = [
                'member_name' => $registration->member_id ? $registration->member->full_name : $registration->external_name,
                'course_name' => $registration->batch?->course?->name ?? 'Course'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'course_certificate_issued',
                $data,
                ['in_app', 'email'],
                'certificate'
            );
        });
    }

    // =========================================================================
    // Event Triggers
    // =========================================================================
    public static function triggerEventRegistrationConfirmed($registration)
    {
        self::runNonBlocking(function() use ($registration) {
            $recipient = $registration->member_id 
                ? self::formatMemberRecipient($registration->member)
                : self::formatExternalRecipient($registration);

            $data = [
                'member_name' => $registration->member_id ? $registration->member->full_name : $registration->external_name,
                'event_title' => $registration->event?->title ?? 'Event',
                'event_date' => $registration->event?->start_datetime?->toDateString() ?? now()->toDateString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'event_registration_confirmed',
                $data,
                ['email'],
                'event'
            );
        });
    }

    public static function triggerEventReminder($event)
    {
        self::runNonBlocking(function() use ($event) {
            $recipients = RecipientResolverService::resolveEventParticipants($event->id);
            $data = [
                'event_title' => $event->title,
                'event_date' => $event->start_datetime?->toDateString() ?? now()->toDateString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'event_reminder',
                $data,
                ['in_app', 'email'],
                'reminder'
            );
        });
    }

    public static function triggerEventCancelled($event)
    {
        self::runNonBlocking(function() use ($event) {
            $recipients = RecipientResolverService::resolveEventParticipants($event->id);
            $data = [
                'event_title' => $event->title,
                'event_date' => $event->start_datetime?->toDateString() ?? now()->toDateString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'event_cancelled',
                $data,
                ['in_app', 'email'],
                'event'
            );
        });
    }

    // =========================================================================
    // Sunday School Triggers
    // =========================================================================
    public static function triggerSundaySchoolEnrollmentApproved($enrollment)
    {
        self::runNonBlocking(function() use ($enrollment) {
            $parent = $enrollment->parent_member_id ? \App\Models\Member::find($enrollment->parent_member_id) : null;
            if (!$parent) {
                // Fallback to head of family
                $parent = \App\Models\Member::where('family_id', $enrollment->family_id)
                    ->where('relationship_to_head', 'head')
                    ->first();
            }
            if (!$parent) return;

            $recipient = self::formatMemberRecipient($parent);
            $data = [
                'child_name' => $enrollment->member?->full_name ?? 'Child',
                'class_name' => $enrollment->class?->class_name ?? 'Sunday School'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'sunday_school_enrollment_approved',
                $data,
                ['in_app', 'email'],
                'sunday_school',
                null,
                $enrollment->approver
            );
        });
    }

    public static function triggerSundaySchoolExamPublished($exam)
    {
        self::runNonBlocking(function() use ($exam) {
            $recipients = RecipientResolverService::resolveSundaySchoolParents($exam->class_id);
            $data = [
                'exam_title' => $exam->exam_name ?? 'Exam',
                'class_name' => $exam->class?->class_name ?? 'Sunday School',
                'exam_date' => $exam->exam_date ?? now()->toDateString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'sunday_school_exam_published',
                $data,
                ['in_app', 'email'],
                'sunday_school'
            );
        });
    }

    public static function triggerSundaySchoolMarksPublished($student, $exam)
    {
        self::runNonBlocking(function() use ($student, $exam) {
            // Find parent
            $parent = $student->parent_member_id ? \App\Models\Member::find($student->parent_member_id) : null;
            if (!$parent) {
                $parent = \App\Models\Member::where('family_id', $student->family_id)
                    ->where('relationship_to_head', 'head')
                    ->first();
            }
            if (!$parent) return;

            $recipient = self::formatMemberRecipient($parent);
            $data = [
                'child_name' => $student->member?->full_name ?? 'Child',
                'exam_title' => $exam->exam_name ?? 'Exam'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'sunday_school_marks_published',
                $data,
                ['in_app', 'email'],
                'sunday_school'
            );
        });
    }

    public static function triggerSundaySchoolProgressReportReady($student)
    {
        self::runNonBlocking(function() use ($student) {
            $parent = $student->parent_member_id ? \App\Models\Member::find($student->parent_member_id) : null;
            if (!$parent) {
                $parent = \App\Models\Member::where('family_id', $student->family_id)
                    ->where('relationship_to_head', 'head')
                    ->first();
            }
            if (!$parent) return;

            $recipient = self::formatMemberRecipient($parent);
            $data = [
                'child_name' => $student->member?->full_name ?? 'Child'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'sunday_school_progress_report_ready',
                $data,
                ['in_app', 'email'],
                'sunday_school'
            );
        });
    }

    // =========================================================================
    // Ministry Triggers
    // =========================================================================
    public static function triggerMinistryActivityReminder($activity)
    {
        self::runNonBlocking(function() use ($activity) {
            $recipients = RecipientResolverService::resolveMinistryUnitMembers($activity->ministry_unit_id);
            $data = [
                'activity_title' => $activity->title,
                'activity_date' => $activity->activity_date ?? now()->toDateString()
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'ministry_activity_reminder',
                $data,
                ['in_app', 'email'],
                'reminder'
            );
        });
    }

    // =========================================================================
    // Finance Triggers
    // =========================================================================
    public static function triggerFinanceExpenseApprovalRequested($expense)
    {
        self::runNonBlocking(function() use ($expense) {
            // Notify Priest / Vicar scoped to the church
            $recipients = RecipientResolverService::resolveRoleUsers('Priest / Vicar');
            if ($expense->church_id) {
                $recipients = array_filter($recipients, fn($r) => $r['church_id'] == $expense->church_id);
            }

            $data = [
                'amount' => number_format($expense->amount, 2),
                'currency' => $expense->currency ?? 'EUR',
                'description' => $expense->description,
                'approval_url' => '#'
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'finance_expense_approval_requested',
                $data,
                ['in_app', 'email'],
                'finance'
            );
        });
    }

    public static function triggerFinanceExpenseApproved($expense)
    {
        self::runNonBlocking(function() use ($expense) {
            // Notify record creator
            $creator = $expense->creator;
            if (!$creator) return;

            $recipient = self::formatUserRecipient($creator);
            $data = [
                'amount' => number_format($expense->amount, 2),
                'currency' => $expense->currency ?? 'EUR',
                'description' => $expense->description
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'finance_expense_approved',
                $data,
                ['in_app', 'email'],
                'finance'
            );
        });
    }

    public static function triggerFinanceExpenseRejected($expense, string $reason)
    {
        self::runNonBlocking(function() use ($expense, $reason) {
            // Notify record creator
            $creator = $expense->creator;
            if (!$creator) return;

            $recipient = self::formatUserRecipient($creator);
            $data = [
                'amount' => number_format($expense->amount, 2),
                'currency' => $expense->currency ?? 'EUR',
                'description' => $expense->description,
                'rejection_reason' => $reason
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'finance_expense_rejected',
                $data,
                ['in_app', 'email'],
                'finance'
            );
        });
    }

    public static function triggerReceiptGenerated($receipt)
    {
        self::runNonBlocking(function() use ($receipt) {
            $recipient = null;
            $name = 'Beneficiary';
            
            // Resolve recipient depending on linked model
            if ($receipt->recipient_type === 'member' && $receipt->recipient_id) {
                $member = \App\Models\Member::find($receipt->recipient_id);
                if ($member) {
                    $recipient = self::formatMemberRecipient($member);
                    $name = $member->full_name;
                }
            } elseif ($receipt->payer_name && $receipt->payer_email) {
                // External payer
                $recipient = [
                    'recipient_type' => 'external',
                    'recipient_id' => null,
                    'name' => $receipt->payer_name,
                    'email' => $receipt->payer_email,
                    'phone' => $receipt->payer_phone,
                    'church_id' => $receipt->church_id,
                    'user_id' => null
                ];
                $name = $receipt->payer_name;
            }

            if (!$recipient) return;

            $data = [
                'member_name' => $name,
                'receipt_number' => $receipt->receipt_number,
                'amount' => number_format($receipt->amount, 2),
                'currency' => $receipt->currency ?? 'EUR'
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'receipt_generated',
                $data,
                ['email'],
                'finance'
            );
        });
    }

    // =========================================================================
    // CMS Triggers
    // =========================================================================
    public static function triggerCmsContentApprovalRequested($content, $author)
    {
        self::runNonBlocking(function() use ($content, $author) {
            // Notify PROs / Admins
            $recipients = RecipientResolverService::resolveRoleUsers('Diocese PRO');
            
            $data = [
                'title' => $content->title,
                'author_name' => $author->name,
                'approval_url' => '#'
            ];

            NotificationDispatchService::dispatchToRecipients(
                $recipients,
                'cms_content_approval_requested',
                $data,
                ['in_app', 'email'],
                'approval'
            );
        });
    }

    public static function triggerCmsContentApproved($content)
    {
        self::runNonBlocking(function() use ($content) {
            $creator = $content->creator;
            if (!$creator) return;

            $recipient = self::formatUserRecipient($creator);
            $data = [
                'title' => $content->title
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'cms_content_approved',
                $data,
                ['in_app', 'email'],
                'cms'
            );
        });
    }

    public static function triggerCmsContentRejected($content, string $reason)
    {
        self::runNonBlocking(function() use ($content, $reason) {
            $creator = $content->creator;
            if (!$creator) return;

            $recipient = self::formatUserRecipient($creator);
            $data = [
                'title' => $content->title,
                'rejection_reason' => $reason
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'cms_content_rejected',
                $data,
                ['in_app', 'email'],
                'cms'
            );
        });
    }

    public static function triggerCmsContentPublished($content)
    {
        self::runNonBlocking(function() use ($content) {
            $creator = $content->creator;
            if (!$creator) return;

            $recipient = self::formatUserRecipient($creator);
            $data = [
                'title' => $content->title
            ];

            NotificationDispatchService::dispatchToRecipients(
                [$recipient],
                'cms_content_published',
                $data,
                ['in_app', 'email'],
                'cms'
            );
        });
    }

    // =========================================================================
    // Safe Non-Blocking Runner
    // =========================================================================
    protected static function runNonBlocking(callable $callback)
    {
        try {
            $callback();
        } catch (Exception $e) {
            // Keep main action alive and log the warning
            Log::warning("Notification dispatch failed but bypassed: " . $e->getMessage());
        }
    }

    // =========================================================================
    // Format helpers
    // =========================================================================
    private static function formatMemberRecipient(\App\Models\Member $m): array
    {
        return [
            'recipient_type' => 'member',
            'recipient_id' => $m->id,
            'name' => $m->full_name,
            'email' => $m->email,
            'phone' => $m->phone,
            'church_id' => $m->church_id,
            'user_id' => $m->user_id
        ];
    }

    private static function formatUserRecipient(\App\Models\User $u): array
    {
        return [
            'recipient_type' => 'user',
            'recipient_id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => null,
            'church_id' => $u->default_church_id,
            'user_id' => $u->id
        ];
    }

    private static function formatExternalRecipient($reg): array
    {
        return [
            'recipient_type' => 'external',
            'recipient_id' => null,
            'name' => $reg->external_name,
            'email' => $reg->external_email,
            'phone' => $reg->external_phone,
            'church_id' => $reg->church_id,
            'user_id' => null
        ];
    }
}
