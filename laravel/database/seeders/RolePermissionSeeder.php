<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Phase 1 active permission groups
        $permissions = [
            'view_dashboard',
            'manage_churches',
            'view_churches',
            'manage_priests',
            'view_priests',
            'manage_priest_assignments',
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'manage_user_church_access',
            'view_audit_logs',
            'switch_active_church'
        ];

        // Future permissions (Phase 2+)
        $futurePermissions = [
            'manage_families',
            'manage_members',
            'approve_member_changes',
            'manage_certificates',
            'approve_certificates',
            'issue_certificates',
            'manage_sacraments',
            'manage_events',
            'manage_courses',
            'manage_sunday_school',
            'export_sunday_school_child_data',
            'manage_youth',
            'manage_marthamariyam',
            'manage_finance',
            'approve_finance',
            'view_reports',
            'manage_documents',
            'manage_website',
            'publish_content',
            'manage_matrimony',
            'export_data',
            'import_data',
            'import_and_auto_approve_members',
            'approve_diocese_transfers',
            // Phase 7 Finance Permissions
            'manage_finance_categories',
            'manage_donations',
            'approve_donations',
            'manage_income',
            'approve_income',
            'manage_expenses',
            'approve_expenses',
            'mark_expense_paid',
            'generate_receipts',
            'cancel_receipts',
            'view_finance_reports',
            'export_finance_reports',
            'view_all_church_finance',
            'view_finance_audit',
            // Phase 8 CMS Permissions
            'manage_website_pages',
            'submit_website_content',
            'approve_website_content',
            'publish_website_content',
            'manage_news',
            'publish_news',
            'manage_downloads',
            'publish_downloads',
            'manage_kalpana_circulars',
            'publish_kalpana_circulars',
            'manage_galleries',
            'publish_galleries',
            'manage_website_settings',
            'view_cms_reports',
            // Phase 9 Communication Permissions
            'manage_notification_templates',
            'manage_announcements',
            'send_diocese_announcements',
            'send_parish_announcements',
            'send_role_announcements',
            'send_course_event_notifications',
            'send_sunday_school_notifications',
            'send_ministry_notifications',
            'send_finance_notifications',
            'send_cms_notifications',
            'send_urgent_announcements',
            'view_notification_logs',
            'retry_failed_notifications',
            'manage_notification_preferences',
            'manage_scheduled_reminders',
            'view_communication_reports',
            'export_communication_reports',
            'view_unmasked_notification_recipients',
            // Phase 10 Portal Permissions
            'manage_member_portal_access',
            'review_profile_corrections',
            'review_portal_documents',
            'view_portal_activity_logs',
            'suspend_member_portal_access',
            'revoke_member_portal_access',
            // Phase 11 Reports Permissions
            'view_reports',
            'view_diocese_reports',
            'view_parish_reports',
            'view_member_reports',
            'export_member_reports',
            'view_child_reports',
            'export_child_reports',
            'view_finance_reports',
            'export_finance_reports',
            'view_audit_reports',
            'export_audit_reports',
            'view_gdpr_reports',
            'export_gdpr_reports',
            'manage_saved_reports',
            'manage_scheduled_reports',
            'download_report_exports',
            'manage_dashboard_widgets',
            'view_unmasked_report_contacts',
            'view_priest_directory',
            'manage_priest_profiles',
            'manage_priest_assignments',
            'manage_priest_transfers',
            'view_priest_assignment_history',
            'import_priest_website_data',
            'review_priest_website_imports',
            'manage_member_responsibilities',
            'view_member_responsibilities',
            'view_office_bearers',
            'manage_office_bearers',
            'view_own_priest_dashboard',
            'view_own_priest_finance'
        ];

        $allPermissions = array_merge($permissions, $futurePermissions);

        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Create Roles
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->givePermissionTo(Permission::all());

        $dioceseAdmin = Role::findOrCreate('Diocese Admin', 'web');
        $dioceseAdmin->givePermissionTo($allPermissions);

        $dioceseSecretary = Role::findOrCreate('Diocese Secretary', 'web');
        $dioceseSecretaryPermissions = array_diff($allPermissions, [
            'import_and_auto_approve_members',
            'approve_diocese_transfers'
        ]);
        $dioceseSecretary->givePermissionTo($dioceseSecretaryPermissions);

        $priestSecretary = Role::findOrCreate('Priest Secretary', 'web');
        $priestSecretary->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'manage_priests',
            'view_priests',
            'manage_priest_assignments',
            'view_audit_logs',
            'switch_active_church'
        ]);

        $priest = Role::findOrCreate('Priest / Vicar', 'web');
        $priest->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'view_priests',
            'switch_active_church',
            'manage_families',
            'manage_members',
            'approve_member_changes',
            'manage_documents',
            'export_data',
            'manage_certificates',
            'approve_certificates',
            'issue_certificates',
            'manage_sacraments',
            'manage_events',
            'manage_courses',
            'manage_sunday_school',
            // Phase 7 Finance
            'approve_expenses',
            'view_finance_reports',
            'generate_receipts',
            // Phase 8 CMS
            'submit_website_content',
            'manage_website_pages',
            'manage_news',
            'manage_downloads',
            'manage_galleries',
            'approve_website_content',
            // Phase 9
            'send_parish_announcements',
            'send_sunday_school_notifications',
            'send_ministry_notifications',
            'send_course_event_notifications',
            // Phase 10
            'manage_member_portal_access',
            'review_profile_corrections',
            'review_portal_documents',
            'suspend_member_portal_access',
            'revoke_member_portal_access',
            // Phase 11
            'view_reports',
            'view_parish_reports',
            'view_member_reports',
            'view_child_reports',
            'view_finance_reports',
            'manage_saved_reports',
            'download_report_exports',
            'view_own_priest_dashboard',
            'view_own_priest_finance'
        ]);

        $parishAdmin = Role::findOrCreate('Parish Admin', 'web');
        $parishAdmin->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'view_priests',
            'switch_active_church',
            'manage_families',
            'manage_members',
            'manage_documents',
            'import_data',
            'export_data',
            'manage_certificates',
            'manage_sacraments',
            'manage_events',
            'manage_courses',
            'manage_sunday_school',
            // Phase 7 Finance
            'view_finance_reports',
            // Phase 8 CMS
            'submit_website_content',
            'manage_website_pages',
            'manage_news',
            'manage_downloads',
            'manage_galleries',
            // Phase 9
            'send_parish_announcements',
            'send_sunday_school_notifications',
            'send_ministry_notifications',
            'send_course_event_notifications',
            // Phase 10
            'manage_member_portal_access',
            'review_profile_corrections',
            'review_portal_documents',
            'suspend_member_portal_access',
            'revoke_member_portal_access',
            // Phase 11
            'view_reports',
            'view_parish_reports',
            'view_member_reports',
            'view_child_reports',
            'view_finance_reports',
            'manage_saved_reports',
            'download_report_exports',
            'manage_dashboard_widgets'
        ]);

        $parishSecretary = Role::findOrCreate('Parish Secretary', 'web');
        $parishSecretary->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'view_priests',
            'switch_active_church',
            'manage_families',
            'manage_members',
            'manage_documents',
            'import_data',
            'export_data',
            'manage_certificates',
            'manage_sacraments',
            'manage_events',
            'manage_courses',
            'manage_sunday_school',
            // Phase 7 Finance
            'view_finance_reports',
            // Phase 8 CMS
            'submit_website_content',
            'manage_website_pages',
            'manage_news',
            'manage_downloads',
            'manage_galleries',
            // Phase 9
            'send_parish_announcements',
            'send_sunday_school_notifications',
            'send_ministry_notifications',
            'send_course_event_notifications',
            // Phase 11
            'view_reports',
            'view_parish_reports',
            'view_member_reports',
            'view_child_reports',
            'manage_saved_reports',
            'download_report_exports'
        ]);

        $parishTreasurer = Role::findOrCreate('Parish Treasurer', 'web');
        $parishTreasurer->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            // Phase 7 Finance
            'manage_donations',
            'manage_income',
            'manage_expenses',
            'generate_receipts',
            'view_finance_reports',
            // Phase 11
            'view_reports',
            'view_parish_reports',
            'view_finance_reports',
            'export_finance_reports',
            'manage_saved_reports',
            'download_report_exports'
        ]);

        $diocesePro = Role::findOrCreate('Diocese PRO', 'web');
        $diocesePro->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            // Phase 8 CMS
            'manage_website_pages',
            'submit_website_content',
            'approve_website_content',
            'publish_website_content',
            'manage_news',
            'publish_news',
            'manage_downloads',
            'publish_downloads',
            'manage_galleries',
            'publish_galleries',
            'manage_website_settings',
            'view_cms_reports',
            // Phase 9
            'send_cms_notifications',
            'manage_announcements',
            'send_role_announcements',
            'view_notification_logs'
        ]);

        $dioceseTreasurer = Role::findOrCreate('Diocese Treasurer', 'web');
        $dioceseTreasurer->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            // Phase 7 Finance
            'manage_finance_categories',
            'manage_donations',
            'approve_donations',
            'manage_income',
            'approve_income',
            'manage_expenses',
            'approve_expenses',
            'mark_expense_paid',
            'generate_receipts',
            'cancel_receipts',
            'view_finance_reports',
            'export_finance_reports',
            'view_all_church_finance',
            'view_finance_audit',
            // Phase 9
            'send_finance_notifications',
            'view_notification_logs',
            // Phase 11
            'view_reports',
            'view_diocese_reports',
            'view_parish_reports',
            'manage_saved_reports',
            'manage_scheduled_reports',
            'download_report_exports'
        ]);

        $dioceseAuditor = Role::findOrCreate('Diocese Auditor', 'web');
        $dioceseAuditor->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            // Phase 7 Finance
            'view_finance_reports',
            'export_finance_reports',
            'view_all_church_finance',
            'view_finance_audit',
            // Phase 11
            'view_reports',
            'view_diocese_reports',
            'view_parish_reports',
            'view_audit_reports',
            'download_report_exports'
        ]);

        $sundaySchoolAdmin = Role::findOrCreate('Sunday School Admin', 'web');
        $sundaySchoolAdmin->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            'manage_sunday_school',
            'export_sunday_school_child_data',
            // Phase 9
            'send_sunday_school_notifications'
        ]);

        $youthCoordinator = Role::findOrCreate('Youth Association Coordinator', 'web');
        $youthCoordinator->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            'manage_youth',
            // Phase 8 CMS
            'submit_website_content',
            'manage_news',
            'manage_galleries',
            // Phase 9
            'send_ministry_notifications',
            // Phase 11
            'view_reports'
        ]);

        $marthamariyamCoordinator = Role::findOrCreate('Marthamariyam Coordinator', 'web');
        $marthamariyamCoordinator->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            'manage_marthamariyam',
            // Phase 8 CMS
            'submit_website_content',
            'manage_news',
            'manage_galleries',
            // Phase 9
            'send_ministry_notifications',
            // Phase 11
            'view_reports'
        ]);

        $sundaySchoolTeacher = Role::findOrCreate('Sunday School Teacher', 'web');
        $sundaySchoolTeacher->givePermissionTo([
            'view_dashboard',
            'view_churches',
            'switch_active_church',
            'view_child_reports',
            'view_reports'
        ]);
    }
}
