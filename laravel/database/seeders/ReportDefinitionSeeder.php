<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportDefinition;

class ReportDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $systemReports = [
            [
                'report_key' => 'diocese_overview',
                'name' => 'Diocese Overview Report',
                'description' => 'Diocese-wide consolidated stats of parishes, families, members, active priests, and certificates.',
                'report_category' => 'diocese',
                'required_permissions' => ['view_diocese_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Diocese Secretary'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'parish_overview',
                'name' => 'Parish Overview Report',
                'description' => 'Comprehensive snapshot of parish operational metrics, member demographics, and attendance summaries.',
                'report_category' => 'parish',
                'required_permissions' => ['view_parish_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Parish Secretary'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'members_families_list',
                'name' => 'Members & Families Directory Report',
                'description' => 'List of members and families sorted by status, age groups, and contacts.',
                'report_category' => 'members',
                'required_permissions' => ['view_member_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Parish Secretary'],
                'default_filters' => ['status' => 'approved'],
            ],
            [
                'report_key' => 'sacramental_records',
                'name' => 'Sacramental Records Summary',
                'description' => 'Overview of sacraments (Baptism, Holy Communion, Confirmation, Funeral) recorded in the parish/diocese.',
                'report_category' => 'sacraments',
                'required_permissions' => ['view_member_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Parish Secretary'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'certificates_issued',
                'name' => 'Certificates Issued Report',
                'description' => 'Historical log of official parish certificates requested and issued.',
                'report_category' => 'certificates',
                'required_permissions' => ['view_member_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Parish Secretary'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'courses_summary',
                'name' => 'Courses & Batches Report',
                'description' => 'Course registrations, batch completion rates, and feedback analysis.',
                'report_category' => 'courses_events',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'events_summary',
                'name' => 'Event Registrations & Attendance',
                'description' => 'Summarized participant metrics and check-in ratios for parish and diocese events.',
                'report_category' => 'courses_events',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'sunday_school_progress',
                'name' => 'Sunday School Progress & Attendance',
                'description' => 'Grades, attendance metrics, exam marks, and academic promo rates of Sunday School students.',
                'report_category' => 'sunday_school',
                'required_permissions' => ['view_child_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Sunday School Admin', 'Sunday School Teacher'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'ministries_overview',
                'name' => 'Ministries & Organizations Report',
                'description' => 'Membership registers, office bearer histories, and volunteer logs for Youth and Marthamariyam Samajam.',
                'report_category' => 'ministries',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar', 'Youth Association Coordinator', 'Marthamariyam Coordinator'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'finance_statement',
                'name' => 'Parish Income & Expense statement',
                'description' => 'Consolidated and categorical balance sheets, donations, and expense logs.',
                'report_category' => 'finance',
                'required_permissions' => ['view_finance_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Diocese Treasurer', 'Diocese Auditor', 'Parish Treasurer', 'Priest / Vicar'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'cms_publishing',
                'name' => 'Website CMS Publishing Report',
                'description' => 'Log of published pages, news posts, media galleries, downloads, and circulars.',
                'report_category' => 'cms',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Diocese PRO', 'Parish Admin'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'communications_delivery',
                'name' => 'Notification & Delivery Analytics',
                'description' => 'Email dispatch success rates, in-app notifications read history, and failed delivery queue reviews.',
                'report_category' => 'communications',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'portal_usage',
                'name' => 'Member Portal Activity Summary',
                'description' => 'Analysis of portal access invitations, active login tokens, profile updates, and document uploads.',
                'report_category' => 'portal',
                'required_permissions' => ['view_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'gdpr_privacy_audit',
                'name' => 'GDPR & Privacy Compliance Report',
                'description' => 'Lists of members missing general GDPR consent, photo consent blocks, and sensitive downloads log.',
                'report_category' => 'gdpr',
                'required_permissions' => ['view_gdpr_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin'],
                'default_filters' => [],
            ],
            [
                'report_key' => 'audit_logs',
                'name' => 'System Audit Logs Report',
                'description' => 'Auditing trail of administrative operations, financial modifications, and role assignments.',
                'report_category' => 'audit',
                'required_permissions' => ['view_audit_reports'],
                'allowed_roles' => ['Super Admin', 'Diocese Admin', 'Diocese Auditor'],
                'default_filters' => [],
            ],
        ];

        foreach ($systemReports as $report) {
            ReportDefinition::updateOrCreate(
                ['report_key' => $report['report_key']],
                [
                    'name' => $report['name'],
                    'description' => $report['description'],
                    'report_category' => $report['report_category'],
                    'required_permissions' => $report['required_permissions'],
                    'allowed_roles' => $report['allowed_roles'],
                    'default_filters' => $report['default_filters'],
                    'is_system' => true,
                    'status' => 'active',
                ]
            );
        }
    }
}
