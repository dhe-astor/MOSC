<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;
use App\Models\Diocese;
use App\Models\User;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first() ?? Diocese::create([
            'name' => 'MSOC Europe Diocese',
            'code' => 'MSOC-EU',
            'established_date' => '2026-01-01',
            'status' => 'active'
        ]);

        $admin = User::whereHas('roles', function($q) {
            $q->where('name', 'Super Admin');
        })->first() ?? User::first();

        $adminId = $admin ? $admin->id : 1;

        $templates = [
            'certificate_issued' => [
                'name' => 'Certificate Issued',
                'subject' => 'Official Certificate Issued',
                'body' => 'Dear {{member_name}}, your certificate (Number: {{certificate_number}}) has been successfully issued by {{church_name}}.',
                'variables' => ['member_name', 'certificate_number', 'church_name']
            ],
            'certificate_request_submitted' => [
                'name' => 'Certificate Request Submitted',
                'subject' => 'New Certificate Request Submitted',
                'body' => 'A new certificate request has been submitted by {{member_name}} for {{church_name}}.',
                'variables' => ['member_name', 'church_name']
            ],
            'certificate_request_approved' => [
                'name' => 'Certificate Request Approved',
                'subject' => 'Certificate Request Approved',
                'body' => 'Dear {{member_name}}, your request for a certificate has been approved by the Priest of {{church_name}}.',
                'variables' => ['member_name', 'church_name']
            ],
            'certificate_request_rejected' => [
                'name' => 'Certificate Request Rejected',
                'subject' => 'Certificate Request Rejected',
                'body' => 'Dear {{member_name}}, your request for a certificate has been rejected. Reason: {{rejection_reason}}.',
                'variables' => ['member_name', 'rejection_reason']
            ],
            'event_registration_confirmed' => [
                'name' => 'Event Registration Confirmed',
                'subject' => 'Registration Confirmed: {{event_title}}',
                'body' => 'Dear {{member_name}}, your registration for the event "{{event_title}}" on {{event_date}} is confirmed.',
                'variables' => ['member_name', 'event_title', 'event_date']
            ],
            'event_reminder' => [
                'name' => 'Event Reminder',
                'subject' => 'Upcoming Event Reminder: {{event_title}}',
                'body' => 'Dear {{member_name}}, this is a reminder that the event "{{event_title}}" will start on {{event_date}}.',
                'variables' => ['member_name', 'event_title', 'event_date']
            ],
            'event_cancelled' => [
                'name' => 'Event Cancelled',
                'subject' => 'Event Cancelled: {{event_title}}',
                'body' => 'We regret to inform you that the event "{{event_title}}" scheduled for {{event_date}} has been cancelled.',
                'variables' => ['event_title', 'event_date']
            ],
            'course_registration_confirmed' => [
                'name' => 'Course Registration Confirmed',
                'subject' => 'Course Enrollment Confirmed: {{course_name}}',
                'body' => 'Dear {{member_name}}, your registration for the course "{{course_name}}" has been confirmed.',
                'variables' => ['member_name', 'course_name']
            ],
            'course_batch_opened' => [
                'name' => 'Course Batch Opened',
                'subject' => 'New Course Batch Opened: {{course_name}}',
                'body' => 'A new batch for the course "{{course_name}}" is now open for registration.',
                'variables' => ['course_name']
            ],
            'course_session_reminder' => [
                'name' => 'Course Session Reminder',
                'subject' => 'Upcoming Class Session: {{course_name}}',
                'body' => 'Dear {{member_name}}, your upcoming session for "{{course_name}}" is scheduled for {{session_time}}.',
                'variables' => ['member_name', 'course_name', 'session_time']
            ],
            'course_certificate_issued' => [
                'name' => 'Course Certificate Issued',
                'subject' => 'Course Certificate Issued: {{course_name}}',
                'body' => 'Dear {{member_name}}, congratulations on completing "{{course_name}}". Your certificate is now ready.',
                'variables' => ['member_name', 'course_name']
            ],
            'sunday_school_enrollment_approved' => [
                'name' => 'Sunday School Enrollment Approved',
                'subject' => 'Sunday School Enrollment Approved',
                'body' => 'Dear Parent, your child {{child_name}} has been enrolled into Sunday School Class {{class_name}}.',
                'variables' => ['child_name', 'class_name']
            ],
            'sunday_school_exam_published' => [
                'name' => 'Sunday School Exam Published',
                'subject' => 'New Sunday School Exam Published',
                'body' => 'A new exam "{{exam_title}}" has been scheduled for class {{class_name}} on {{exam_date}}.',
                'variables' => ['exam_title', 'class_name', 'exam_date']
            ],
            'sunday_school_marks_published' => [
                'name' => 'Sunday School Marks Published',
                'subject' => 'Sunday School Exam Marks Published',
                'body' => 'Dear Parent, exam marks for {{child_name}} for the exam "{{exam_title}}" have been published.',
                'variables' => ['child_name', 'exam_title']
            ],
            'sunday_school_progress_report_ready' => [
                'name' => 'Sunday School Progress Report Ready',
                'subject' => 'Sunday School Progress Report Ready',
                'body' => 'Dear Parent, the Sunday School progress report for {{child_name}} is ready for viewing.',
                'variables' => ['child_name']
            ],
            'finance_expense_approval_requested' => [
                'name' => 'Expense Approval Requested',
                'subject' => 'New Expense Claim Pending Approval',
                'body' => 'A new expense claim of {{amount}} {{currency}} for "{{description}}" requires your approval.',
                'variables' => ['amount', 'currency', 'description', 'approval_url']
            ],
            'finance_expense_approved' => [
                'name' => 'Expense Approved',
                'subject' => 'Expense Claim Approved',
                'body' => 'Your expense claim of {{amount}} {{currency}} for "{{description}}" has been approved.',
                'variables' => ['amount', 'currency', 'description']
            ],
            'finance_expense_rejected' => [
                'name' => 'Expense Rejected',
                'subject' => 'Expense Claim Rejected',
                'body' => 'Your expense claim of {{amount}} {{currency}} for "{{description}}" has been rejected. Reason: {{rejection_reason}}.',
                'variables' => ['amount', 'currency', 'description', 'rejection_reason']
            ],
            'receipt_generated' => [
                'name' => 'Receipt Generated',
                'subject' => 'Payment Receipt Generated',
                'body' => 'Dear {{member_name}}, a receipt (Number: {{receipt_number}}) has been generated for your payment of {{amount}} {{currency}}.',
                'variables' => ['member_name', 'receipt_number', 'amount', 'currency']
            ],
            'cms_content_approval_requested' => [
                'name' => 'CMS Content Approval Requested',
                'subject' => 'CMS Content Pending Approval',
                'body' => 'A new CMS entry "{{title}}" has been submitted for approval by {{author_name}}.',
                'variables' => ['title', 'author_name', 'approval_url']
            ],
            'cms_content_approved' => [
                'name' => 'CMS Content Approved',
                'subject' => 'CMS Content Approved',
                'body' => 'Your CMS submission "{{title}}" has been approved.',
                'variables' => ['title']
            ],
            'cms_content_rejected' => [
                'name' => 'CMS Content Rejected',
                'subject' => 'CMS Content Rejected',
                'body' => 'Your CMS submission "{{title}}" has been rejected. Reason: {{rejection_reason}}.',
                'variables' => ['title', 'rejection_reason']
            ],
            'cms_content_published' => [
                'name' => 'CMS Content Published',
                'subject' => 'CMS Content Published',
                'body' => 'The CMS submission "{{title}}" has been published and is now live on the public portal.',
                'variables' => ['title']
            ],
            'parish_announcement' => [
                'name' => 'Parish Announcement',
                'subject' => 'Parish Announcement: {{title}}',
                'body' => '{{body_content}}',
                'variables' => ['title', 'body_content']
            ],
            'diocese_announcement' => [
                'name' => 'Diocese Announcement',
                'subject' => 'Diocese Announcement: {{title}}',
                'body' => '{{body_content}}',
                'variables' => ['title', 'body_content']
            ],
            'ministry_activity_reminder' => [
                'name' => 'Ministry Activity Reminder',
                'subject' => 'Upcoming Activity Reminder: {{activity_title}}',
                'body' => 'Dear {{member_name}}, this is a reminder that the ministry activity "{{activity_title}}" is scheduled for {{activity_date}}.',
                'variables' => ['member_name', 'activity_title', 'activity_date']
            ],
            'scheduled_report_ready' => [
                'name' => 'Scheduled Report Ready',
                'subject' => 'Your Scheduled Report is Ready: {{report_name}}',
                'body' => 'Dear {{user_name}}, your scheduled report "{{report_name}}" has been generated successfully and is ready for secure download. Please log in to your account and download it. Note: This file will expire in 7 days.',
                'variables' => ['user_name', 'report_name']
            ]
        ];

        foreach ($templates as $key => $t) {
            // Seed In-App version
            NotificationTemplate::updateOrCreate(
                [
                    'diocese_id' => $diocese->id,
                    'template_key' => $key,
                    'channel' => 'in_app'
                ],
                [
                    'name' => $t['name'] . ' (In-App)',
                    'subject' => $t['subject'],
                    'body' => $t['body'],
                    'variables' => $t['variables'],
                    'status' => 'active',
                    'is_system' => true,
                    'created_by' => $adminId
                ]
            );

            // Seed Email version
            NotificationTemplate::updateOrCreate(
                [
                    'diocese_id' => $diocese->id,
                    'template_key' => $key,
                    'channel' => 'email'
                ],
                [
                    'name' => $t['name'] . ' (Email)',
                    'subject' => $t['subject'],
                    'body' => $t['body'],
                    'variables' => $t['variables'],
                    'status' => 'active',
                    'is_system' => true,
                    'created_by' => $adminId
                ]
            );
        }
    }
}
