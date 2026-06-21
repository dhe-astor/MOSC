<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Diocese;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\PriestTransferRequest;
use App\Models\MemberResponsibilityAssignment;
use App\Models\UserChurchAccess;
use App\Models\WebsiteImportSource;
use App\Models\Family;
use App\Models\Member;
use App\Models\Sacrament;
use App\Models\CertificateRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Models\CourseRegistration;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SundaySchoolAcademicYear;
use App\Models\SundaySchoolLevel;
use App\Models\SundaySchoolTeacher;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolClassTeacherAssignment;
use App\Models\SundaySchoolExam;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolProgressReport;
use App\Models\SundaySchoolCertificate;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use App\Models\MinistryOfficeBearer;
use App\Models\MinistryActivity;
use App\Models\MinistryServiceLog;
use App\Models\FinanceCategory;
use App\Models\Donation;
use App\Models\IncomeRecord;
use App\Models\ExpenseRecord;
use App\Models\Receipt;
use App\Models\FinanceApproval;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\WebsiteDownload;
use App\Models\KalpanaCircular;
use App\Models\MediaGallery;
use App\Models\MediaItem;
use App\Models\ContentApproval;
use App\Models\NotificationTemplate;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\ScheduledReminder;
use App\Models\MemberPortalAccess;
use App\Models\ProfileCorrectionRequest;
use App\Models\MemberPortalDocument;
use App\Models\MemberPortalActivityLog;
use App\Models\ReportRun;
use App\Models\ReportExport;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\DashboardWidget;
use App\Models\FinanceChartAccount;
use App\Models\FinanceIncomeHead;
use App\Models\FinanceExpenseHead;
use App\Models\FinanceFundClass;
use App\Models\FinanceProgrammeAccount;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\FinanceReceipt;
use App\Models\FinanceReceiptLine;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceExpenseLine;
use App\Models\FinancePriestPayment;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceJournalBatch;
use App\Models\FinanceTransfer;
use App\Models\FinanceBankStatementImport;
use App\Models\FinanceBankStatementLine;
use App\Models\FinanceBankMatch;
use App\Models\FinanceCashBatch;
use App\Services\ReceiptNumberService;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo:seed {--fresh : Refresh the database and re-seed the baseline configurations first}';
    protected $description = 'Seed realistic demo data for local/staging testing across all 12 modules';

    public function handle()
    {
        // 1. Check environment
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error('Demo seeding is blocked in production.');
            return self::FAILURE;
        }

        if (!app()->environment(['local', 'staging', 'testing'])) {
            $this->error('Demo seeding can only run in local, staging, or testing.');
            return self::FAILURE;
        }

        // 2. Fresh option
        if ($this->option('fresh')) {
            $this->info('Refreshing database and running default seeders...');
            $this->call('migrate:fresh', ['--seed' => true]);
        }

        $this->info('Starting MSOC Europe Diocese Administration Portal Demo Seeding...');

        // Verify baseline seeder data exists
        $diocese = Diocese::first();
        if (!$diocese) {
            $this->error('No Diocese found. Please run baseline seeders first (or use --fresh).');
            return self::FAILURE;
        }

        // 3. Ensure Idempotency
        $this->info('Cleaning up previous demo records to ensure idempotency...');
        $demoEmails = [
            'superadmin@demo.msoc.test',
            'dioceseadmin@demo.msoc.test',
            'diocesepro@demo.msoc.test',
            'diocesetreasurer@demo.msoc.test',
            'dioceseauditor@demo.msoc.test',
            'priest.vienna@demo.msoc.test',
            'priest.berlin@demo.msoc.test',
            'assistantpriest.vienna@demo.msoc.test',
            'priest.multichurch@demo.msoc.test',
            'parishadmin.vienna@demo.msoc.test',
            'parishadmin.berlin@demo.msoc.test',
            'parishtreasurer.vienna@demo.msoc.test',
            'teacher.vienna@demo.msoc.test',
            'youthcoordinator.vienna@demo.msoc.test',
            'samajamcoordinator.vienna@demo.msoc.test',
            'member.familyhead@demo.msoc.test',
            'parent.vienna@demo.msoc.test',
            'member.single@demo.msoc.test'
        ];

        // Clean dependent tables first
        DB::table('member_portal_activity_logs')->delete();
        DB::table('member_portal_documents')->delete();
        DB::table('profile_correction_requests')->delete();
        DB::table('gdpr_requests')->delete();
        DB::table('member_portal_access')->delete();
        DB::table('report_exports')->delete();
        DB::table('report_runs')->delete();
        DB::table('notification_deliveries')->delete();
        DB::table('notifications')->delete();
        DB::table('announcements')->delete();
        DB::table('notification_templates')->delete();
        DB::table('media_item_member')->delete();
        DB::table('media_items')->delete();
        DB::table('media_galleries')->delete();
        DB::table('content_approvals')->delete();
        DB::table('news_posts')->delete();
        DB::table('website_pages')->delete();
        DB::table('legacy_receipts')->delete();
        DB::table('finance_bank_matches')->delete();
        DB::table('finance_bank_statement_lines')->delete();
        DB::table('finance_bank_statement_imports')->delete();
        DB::table('finance_transfers')->delete();
        DB::table('finance_ledger_entries')->delete();
        DB::table('finance_journal_batches')->delete();
        DB::table('finance_priest_payments')->delete();
        DB::table('finance_receipt_lines')->delete();
        DB::table('finance_receipts')->delete();
        DB::table('finance_income_lines')->delete();
        DB::table('finance_income_headers')->delete();
        DB::table('finance_expense_lines')->delete();
        DB::table('finance_expense_headers')->delete();
        DB::table('finance_cash_batches')->delete();
        DB::table('finance_money_accounts')->delete();
        DB::table('finance_programme_accounts')->delete();
        DB::table('finance_fund_classes')->delete();
        DB::table('finance_expense_heads')->delete();
        DB::table('finance_income_heads')->delete();
        DB::table('finance_chart_accounts')->delete();
        DB::table('legacy_expense_records')->delete();
        DB::table('legacy_income_records')->delete();
        DB::table('legacy_donations')->delete();
        DB::table('sunday_school_marks')->delete();
        DB::table('sunday_school_exams')->delete();
        DB::table('sunday_school_students')->delete();
        DB::table('sunday_school_class_teacher_assignments')->delete();
        DB::table('sunday_school_classes')->delete();
        DB::table('sunday_school_teachers')->delete();
        DB::table('sunday_school_levels')->delete();
        DB::table('sunday_school_academic_years')->delete();
        DB::table('ministry_service_logs')->delete();
        DB::table('ministry_office_bearers')->delete();
        DB::table('ministry_memberships')->delete();
        DB::table('ministry_units')->delete();
        DB::table('event_registrations')->delete();
        DB::table('events')->delete();
        DB::table('course_registrations')->delete();
        DB::table('course_sessions')->delete();
        DB::table('course_batches')->delete();
        DB::table('courses')->delete();
        DB::table('certificates')->delete();
        DB::table('certificate_requests')->delete();
        DB::table('sacraments')->delete();
        DB::table('member_responsibility_assignments')->delete();
        DB::table('priest_transfer_requests')->delete();
        DB::table('priest_church_assignments')->delete();
        DB::table('priest_profiles')->delete();
        DB::table('website_import_records')->delete();
        DB::table('website_import_runs')->delete();
        DB::table('website_import_sources')->delete();
        DB::table('members')->delete();
        DB::table('families')->delete();

        // Delete users
        User::whereIn('email', $demoEmails)->delete();

        // 4. Churches Setup
        $this->info('Setting up churches...');
        $vienna = Church::where('short_name', 'Vienna')->first();
        $berlin = Church::where('short_name', 'Berlin')->first();
        $stuttgart = Church::where('short_name', 'Stuttgart')->first();
        $zurich = Church::where('short_name', 'Zürich')->first() ?: Church::where('short_name', 'Switzerland')->first();
        
        $ireland = \App\Models\Country::where('iso2', 'IE')->first() ?: \App\Models\Country::where('name', 'like', '%Ireland%')->first();
        if ($ireland && !Church::where('short_name', 'Dublin')->exists()) {
            $dublin = Church::create([
                'diocese_id' => $diocese->id,
                'country_id' => $ireland->id,
                'slug' => 'dublin',
                'public_page_slug' => 'dublin',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Dublin',
                'short_name' => 'Dublin',
                'church_type' => 'church',
                'patron_saint' => 'St. Mary',
                'city' => 'Dublin',
                'country' => $ireland->name,
                'canonical_status' => 'active',
                'membership_code_prefix' => 'DUB',
                'show_on_website' => true,
            ]);
        } else {
            $dublin = Church::where('short_name', 'Dublin')->first();
        }

        // 5. Create Demo Users & Roles
        $this->info('Creating login accounts...');
        $password = Hash::make('Password@123');

        $users = [];
        $accountsData = [
            'superadmin@demo.msoc.test' => ['Super Admin', 'Super Admin', null, 'diocese_all'],
            'dioceseadmin@demo.msoc.test' => ['Diocese Admin', 'Diocese Admin', null, 'diocese_all'],
            'diocesepro@demo.msoc.test' => ['Diocese PRO', 'Diocese PRO', null, 'diocese_all'],
            'diocesetreasurer@demo.msoc.test' => ['Diocese Treasurer', 'Diocese Treasurer', null, 'diocese_all'],
            'dioceseauditor@demo.msoc.test' => ['Diocese Auditor', 'Diocese Auditor', null, 'diocese_all'],
            'priest.vienna@demo.msoc.test' => ['Rev. Fr. Thomas Kochupurackal', 'Priest / Vicar', $vienna->id, 'church_specific'],
            'priest.berlin@demo.msoc.test' => ['Rev. Fr. Jacob Mathew', 'Priest / Vicar', $berlin->id, 'church_specific'],
            'assistantpriest.vienna@demo.msoc.test' => ['Vienna Assistant Vicar', 'Priest / Vicar', $vienna->id, 'church_specific'],
            'priest.multichurch@demo.msoc.test' => ['Multi-Church Priest', 'Priest / Vicar', $vienna->id, 'church_specific'],
            'parishadmin.vienna@demo.msoc.test' => ['Vienna Parish Admin', 'Parish Admin', $vienna->id, 'church_specific'],
            'parishadmin.berlin@demo.msoc.test' => ['Berlin Parish Admin', 'Parish Admin', $berlin->id, 'church_specific'],
            'parishtreasurer.vienna@demo.msoc.test' => ['Vienna Treasurer', 'Parish Treasurer', $vienna->id, 'church_specific'],
            'teacher.vienna@demo.msoc.test' => ['Vienna Teacher', 'Sunday School Teacher', $vienna->id, 'church_specific'],
            'youthcoordinator.vienna@demo.msoc.test' => ['Vienna Youth Coordinator', 'Youth Association Coordinator', $vienna->id, 'church_specific'],
            'samajamcoordinator.vienna@demo.msoc.test' => ['Vienna Samajam Coordinator', 'Marthamariyam Coordinator', $vienna->id, 'church_specific'],
            'member.familyhead@demo.msoc.test' => ['John Familyhead', 'Regular Member', $vienna->id, 'church_specific'],
            'parent.vienna@demo.msoc.test' => ['George Parent', 'Regular Member', $vienna->id, 'church_specific'],
            'member.single@demo.msoc.test' => ['Anna Single', 'Regular Member', $vienna->id, 'church_specific'],
        ];

        foreach ($accountsData as $email => $data) {
            $user = User::create([
                'name' => $data[0],
                'email' => $email,
                'password' => $password,
                'default_diocese_id' => $diocese->id,
                'default_church_id' => $data[2],
                'active_church_id' => $data[2],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            if ($data[1] !== 'Regular Member') {
                $user->assignRole($data[1]);
            }
            $users[$email] = $user;

            UserChurchAccess::create([
                'user_id' => $user->id,
                'diocese_id' => $diocese->id,
                'church_id' => $data[2],
                'access_scope' => $data[3],
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        $adminUserId = $users['superadmin@demo.msoc.test']->id;

        // Helper function to create a member & profile for a priest
        $createPriestHelper = function ($email, $firstName, $lastName, $rank, $title, $church, $userAccount = null) use ($diocese, $adminUserId) {
            $member = Member::create([
                'diocese_id' => $diocese->id,
                'church_id' => $church->id,
                'family_id' => null,
                'user_id' => $userAccount?->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => "$firstName $lastName",
                'gender' => 'male',
                'date_of_birth' => Carbon::parse('1985-08-25'),
                'relationship_to_head' => 'other',
                'phone' => '+43 664 5556667',
                'whatsapp_phone' => '+43 664 5556667',
                'membership_status' => 'active',
                'created_by' => $adminUserId,
            ]);

            return PriestProfile::create([
                'diocese_id' => $diocese->id,
                'member_id' => $member->id,
                'user_id' => $userAccount?->id,
                'display_name' => "$title $firstName $lastName",
                'ordination_name' => "Fr. $firstName $lastName",
                'canonical_title' => $title,
                'clergy_type' => $rank,
                'email_public' => $email,
                'status' => 'active',
            ]);
        };

        // Create the 8 priests
        $priestVienna = $createPriestHelper('priest.vienna@demo.msoc.test', 'Thomas', 'Kochupurackal', 'priest', 'Rev. Fr.', $vienna, $users['priest.vienna@demo.msoc.test']);
        $priestBerlin = $createPriestHelper('priest.berlin@demo.msoc.test', 'Jacob', 'Mathew', 'priest', 'Rev. Fr.', $berlin, $users['priest.berlin@demo.msoc.test']);
        $priestAsstVienna = $createPriestHelper('assistantpriest.vienna@demo.msoc.test', 'John', 'Assistant', 'priest', 'Rev. Fr.', $vienna, $users['assistantpriest.vienna@demo.msoc.test']);
        $priestMulti = $createPriestHelper('priest.multichurch@demo.msoc.test', 'Mathew', 'Multi', 'priest', 'Rev. Fr.', $vienna, $users['priest.multichurch@demo.msoc.test']);
        
        // Restore baseline priest user profile if it exists
        $baselinePriestUser = User::where('email', 'priest@msoc-europe.org')->first();
        if ($baselinePriestUser) {
            $priestBaseline = $createPriestHelper('priest@msoc-europe.org', 'Jacob', 'Mathew', 'priest', 'Rev. Fr.', $vienna, $baselinePriestUser);
        }
        
        // Stuttgart church
        $stuttgart = Church::where('short_name', 'Stuttgart')->first();
        if (!$stuttgart) {
            $germany = \App\Models\Country::where('iso2', 'DE')->first();
            $stuttgart = Church::create([
                'diocese_id' => $diocese->id,
                'country_id' => $germany?->id,
                'slug' => 'stuttgart',
                'public_page_slug' => 'stuttgart',
                'name' => 'St. Gregorios Orthodox Congregation Stuttgart',
                'short_name' => 'Stuttgart',
                'church_type' => 'congregation',
                'city' => 'Stuttgart',
                'country' => 'Germany',
                'address_line_1' => 'Stuttgart, Germany',
                'canonical_status' => 'upcoming',
            ]);
        }
        $priestStuttgart = $createPriestHelper('priest.stuttgart@demo.msoc.test', 'Zachariah', 'George', 'priest', 'Rev. Fr.', $stuttgart);
        $priestVisiting = $createPriestHelper('priest.visiting@demo.msoc.test', 'Thomas', 'Kurian', 'priest', 'Rev. Fr.', $vienna);
        $priestSupply = $createPriestHelper('priest.supply@demo.msoc.test', 'George', 'Supply', 'priest', 'Rev. Fr.', $vienna);
        $priestRetired = $createPriestHelper('priest.retired@demo.msoc.test', 'Geevarughese', 'Retired', 'corepiscopa', 'Very Rev.', $vienna);
        $priestRetired->update(['status' => 'retired']);

        // 12 assignments (active / historical)
        // Vienna Vicar (active primary)
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestVienna->id,
            'member_id' => $priestVienna->member_id,
            'user_id' => $priestVienna->user_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'vicar',
            'start_date' => '2018-01-01',
            'is_primary' => true,
            'status' => 'active',
        ]);

        if (isset($priestBaseline)) {
            PriestChurchAssignment::create([
                'diocese_id' => $diocese->id,
                'priest_profile_id' => $priestBaseline->id,
                'member_id' => $priestBaseline->member_id,
                'user_id' => $priestBaseline->user_id,
                'church_id' => $vienna->id,
                'assignment_role' => 'vicar',
                'start_date' => '2020-01-01',
                'is_primary' => false,
                'status' => 'active',
            ]);
        }

        // Vienna Assistant Vicar (active assistant)
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestAsstVienna->id,
            'member_id' => $priestAsstVienna->member_id,
            'user_id' => $priestAsstVienna->user_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2022-01-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Berlin Vicar (active primary)
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestBerlin->id,
            'member_id' => $priestBerlin->member_id,
            'user_id' => $priestBerlin->user_id,
            'church_id' => $berlin->id,
            'assignment_role' => 'vicar',
            'start_date' => '2020-01-01',
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Stuttgart Priest-in-charge (active primary)
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestStuttgart->id,
            'member_id' => $priestStuttgart->member_id,
            'user_id' => $priestStuttgart->user_id,
            'church_id' => $stuttgart->id,
            'assignment_role' => 'priest_in_charge',
            'start_date' => '2021-01-01',
            'is_primary' => true,
            'status' => 'active',
        ]);

        // 2 Priests assigned to multiple churches:
        // Multi-church Priest is Vicar of Vienna, and Assistant Vicar of Berlin!
        // Munich church
        $munich = Church::where('short_name', 'Munich')->first();
        if (!$munich) {
            $germany = \App\Models\Country::where('iso2', 'DE')->first();
            $munich = Church::create([
                'diocese_id' => $diocese->id,
                'country_id' => $germany?->id,
                'slug' => 'munich',
                'public_page_slug' => 'munich',
                'name' => 'St. Marys Orthodox Parish Munich',
                'short_name' => 'Munich',
                'church_type' => 'parish',
                'city' => 'Munich',
                'country' => 'Germany',
                'address_line_1' => 'Munich, Germany',
                'canonical_status' => 'active',
            ]);
        }
        $dublin = Church::where('short_name', 'Dublin')->first();
        if (!$dublin) {
            $ireland = \App\Models\Country::where('iso2', 'IE')->first();
            $dublin = Church::create([
                'diocese_id' => $diocese->id,
                'country_id' => $ireland?->id,
                'slug' => 'dublin-parish',
                'public_page_slug' => 'dublin-parish',
                'name' => 'St. Marys Orthodox Church Dublin',
                'short_name' => 'Dublin',
                'church_type' => 'parish',
                'city' => 'Dublin',
                'country' => 'Ireland',
                'address_line_1' => 'Dublin, Ireland',
                'canonical_status' => 'active',
            ]);
        }

        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestMulti->id,
            'member_id' => $priestMulti->member_id,
            'user_id' => $priestMulti->user_id,
            'church_id' => $dublin->id,
            'assignment_role' => 'vicar',
            'start_date' => '2021-01-01',
            'is_primary' => true,
            'status' => 'active',
        ]);
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestMulti->id,
            'member_id' => $priestMulti->member_id,
            'user_id' => $priestMulti->user_id,
            'church_id' => $munich->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2021-06-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Thomas (Vienna Vicar) is also Assistant Vicar in Graz
        $graz = Church::where('short_name', 'Graz')->first();
        if (!$graz) {
            $austria = \App\Models\Country::where('iso2', 'AT')->first();
            $graz = Church::create([
                'diocese_id' => $diocese->id,
                'country_id' => $austria?->id,
                'slug' => 'graz',
                'public_page_slug' => 'graz',
                'name' => 'St. Marys Orthodox Parish Graz',
                'short_name' => 'Graz',
                'church_type' => 'parish',
                'city' => 'Graz',
                'country' => 'Austria',
                'address_line_1' => 'Graz, Austria',
                'canonical_status' => 'active',
            ]);
        }
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestVienna->id,
            'member_id' => $priestVienna->member_id,
            'user_id' => $priestVienna->user_id,
            'church_id' => $graz->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2019-01-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Historical assignments (ended)
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestVienna->id,
            'member_id' => $priestVienna->member_id,
            'church_id' => $munich->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2015-05-15',
            'end_date' => '2017-12-31',
            'status' => 'ended',
        ]);

        // Visiting priest example assignment
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestVisiting->id,
            'member_id' => $priestVisiting->member_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2022-01-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Supply priest assignment
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestSupply->id,
            'member_id' => $priestSupply->member_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'supply_priest',
            'start_date' => '2022-01-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Other assignments to make up 12
        PriestChurchAssignment::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestBerlin->id,
            'member_id' => $priestBerlin->member_id,
            'church_id' => $munich->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2022-01-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        // 2 scheduled transfers
        // Transfer Fr. Jacob from Berlin to Vienna, scheduled for future
        PriestTransferRequest::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestBerlin->id,
            'from_church_id' => $berlin->id,
            'to_church_id' => $vienna->id,
            'new_assignment_role' => 'vicar',
            'effective_date' => Carbon::now()->addMonths(1)->toDateString(),
            'transfer_type' => 'transfer',
            'status' => 'approved',
            'requested_by' => $adminUserId,
        ]);

        // Transfer Multi-church priest from Munich, scheduled for today/yesterday (to be processed)
        PriestTransferRequest::create([
            'diocese_id' => $diocese->id,
            'priest_profile_id' => $priestMulti->id,
            'from_church_id' => $munich->id,
            'to_church_id' => $vienna->id,
            'new_assignment_role' => 'assistant_vicar',
            'effective_date' => Carbon::now()->subDays(1)->toDateString(),
            'transfer_type' => 'transfer',
            'status' => 'approved',
            'requested_by' => $adminUserId,
        ]);

        // 6. Seed Families and Members
        $this->info('Seeding families and members...');
        $adminUserId = $users['superadmin@demo.msoc.test']->id;

        // Create family list helper
        $churchesList = [
            [$vienna, 15],
            [$berlin, 10],
            [$stuttgart, 2],
            [$zurich, 2],
            [$dublin, 1]
        ];

        $totalMembersCreated = 0;
        $totalFamiliesCreated = 0;

        // Seed explicit demo families first
        // Family 1 (Vienna): Head is member.familyhead@demo.msoc.test
        $f1 = Family::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_name' => 'Familyhead Family',
            'primary_phone' => '+436640001001',
            'address_line_1' => 'Vienna St. 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $m1_head = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'user_id' => $users['member.familyhead@demo.msoc.test']->id,
            'first_name' => 'John',
            'last_name' => 'Familyhead',
            'full_name' => 'John Familyhead',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1975-04-10',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'show_in_directory' => true,
            'photo_publication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $f1->update(['head_member_id' => $m1_head->id]);

        $m1_spouse = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'first_name' => 'Mary',
            'last_name' => 'Familyhead',
            'full_name' => 'Mary Familyhead',
            'relationship_to_head' => 'spouse',
            'gender' => 'female',
            'date_of_birth' => '1980-05-12',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'photo_publication_consent' => true,
            'created_by' => $adminUserId
        ]);

        $m1_child = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'first_name' => 'Kuriakose',
            'last_name' => 'Familyhead',
            'full_name' => 'Kuriakose Familyhead',
            'relationship_to_head' => 'son',
            'gender' => 'male',
            'date_of_birth' => '2010-09-20',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'photo_publication_consent' => false, // Child without photo consent
            'created_by' => $adminUserId
        ]);
        $totalMembersCreated += 3;
        $totalFamiliesCreated++;

        // Family 2 (Vienna): Head is parent.vienna@demo.msoc.test
        $f2 = Family::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_name' => 'George Parent Family',
            'primary_phone' => '+436640001002',
            'address_line_1' => 'Vienna St. 2',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $m2_head = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f2->id,
            'user_id' => $users['parent.vienna@demo.msoc.test']->id,
            'first_name' => 'George',
            'last_name' => 'Parent',
            'full_name' => 'George Parent',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1978-08-15',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'show_in_directory' => true,
            'photo_publication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $f2->update(['head_member_id' => $m2_head->id]);

        $m2_spouse = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f2->id,
            'first_name' => 'Sara',
            'last_name' => 'Parent',
            'full_name' => 'Sara Parent',
            'relationship_to_head' => 'spouse',
            'gender' => 'female',
            'date_of_birth' => '1982-12-10',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'photo_publication_consent' => true,
            'created_by' => $adminUserId
        ]);

        $m2_child1 = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f2->id,
            'first_name' => 'Job',
            'last_name' => 'Parent',
            'full_name' => 'Job Parent',
            'relationship_to_head' => 'son',
            'gender' => 'male',
            'date_of_birth' => '2014-06-05',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'photo_publication_consent' => true, // Enrolled student 1
            'created_by' => $adminUserId
        ]);

        $m2_child2 = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f2->id,
            'first_name' => 'Anna',
            'last_name' => 'Parent',
            'full_name' => 'Anna Parent',
            'relationship_to_head' => 'daughter',
            'gender' => 'female',
            'date_of_birth' => '2016-03-24',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'photo_publication_consent' => false, // Enrolled student 2 (child without photo consent)
            'created_by' => $adminUserId
        ]);
        $totalMembersCreated += 4;
        $totalFamiliesCreated++;

        // Family 3 (Vienna): Single member member.single@demo.msoc.test
        $f3 = Family::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_name' => 'Anna Single Family',
            'primary_phone' => '+436640001003',
            'address_line_1' => 'Vienna St. 3',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $m3_head = Member::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f3->id,
            'user_id' => $users['member.single@demo.msoc.test']->id,
            'first_name' => 'Anna',
            'last_name' => 'Single',
            'full_name' => 'Anna Single',
            'relationship_to_head' => 'head',
            'gender' => 'female',
            'date_of_birth' => '2000-02-14',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'show_in_directory' => true,
            'photo_publication_consent' => true,
            'created_by' => $adminUserId
        ]);
        $f3->update(['head_member_id' => $m3_head->id]);
        $totalMembersCreated += 1;
        $totalFamiliesCreated++;

        // Vienna Sunday School students list
        $sundaySchoolStudents = [$m2_child1, $m2_child2];

        // Seed remaining families programmatically
        $fakeLastNames = ['Varghese', 'Paul', 'Mathew', 'Kurian', 'Cherian', 'John', 'Thomas', 'Joseph', 'George', 'Alexander', 'Daniel', 'Philip', 'Koshy', 'Jacob', 'Abraham'];
        $fakeFirstNamesMale = ['Mathews', 'Philip', 'Thomas', 'Kora', 'Zachariah', 'Basil', 'Geevarghese', 'Aby', 'Jibin', 'Albin', 'Gino', 'Rohan', 'Kevin', 'Justin', 'Alex'];
        $fakeFirstNamesFemale = ['Sherly', 'Elizabeth', 'Susan', 'Sneha', 'Liza', 'Diya', 'Riya', 'Jiya', 'Anupa', 'Merlin', 'Meera', 'Rani', 'Anila', 'Tessy', 'Jessy'];

        // Keep track of youth eligible members to register in Youth Unit
        $youthMembers = [$m3_head];

        foreach ($churchesList as $item) {
            $church = $item[0];
            $count = $item[1];

            // For Vienna, we already created 3 families.
            $startingIndex = ($church->short_name === 'Vienna') ? 3 : 0;

            for ($i = $startingIndex; $i < $count; $i++) {
                $lastName = $fakeLastNames[array_rand($fakeLastNames)];
                $familyName = "{$lastName} Family " . Str::random(3);
                
                $status = 'active';
                if ($church->short_name === 'Stuttgart' && $i === 1) {
                    $status = 'transferred'; // Seeded transferred case
                } elseif ($church->short_name === 'Zurich' && $i === 0) {
                    $status = 'pending'; // Seeded pending case
                }

                $gdpr = ($i !== 4); // Family missing GDPR consent

                $fam = Family::create([
                    'diocese_id' => $diocese->id,
                    'church_id' => $church->id,
                    'family_name' => $familyName,
                    'primary_phone' => '+4366400000' . rand(10, 99),
                    'address_line_1' => $church->city . ' Str. ' . rand(1, 100),
                    'city' => $church->city,
                    'membership_status' => $status,
                    'gdpr_consent' => $gdpr,
                    'communication_consent' => $gdpr,
                    'created_by' => $adminUserId
                ]);

                // Create Head
                $headName = $fakeFirstNamesMale[array_rand($fakeFirstNamesMale)];
                $headMember = Member::create([
                    'diocese_id' => $diocese->id,
                    'church_id' => $church->id,
                    'family_id' => $fam->id,
                    'first_name' => $headName,
                    'last_name' => $lastName,
                    'full_name' => "{$headName} {$lastName}",
                    'relationship_to_head' => 'head',
                    'gender' => 'male',
                    'date_of_birth' => Carbon::now()->subYears(rand(38, 70))->toDateString(),
                    'membership_status' => $status,
                    'gdpr_consent' => $gdpr,
                    'communication_consent' => $gdpr,
                    'show_in_directory' => $gdpr,
                    'photo_publication_consent' => $gdpr,
                    'created_by' => $adminUserId
                ]);
                $fam->update(['head_member_id' => $headMember->id]);
                $totalMembersCreated++;

                // Create Spouse
                $spouseName = $fakeFirstNamesFemale[array_rand($fakeFirstNamesFemale)];
                $spouseMember = Member::create([
                    'diocese_id' => $diocese->id,
                    'church_id' => $church->id,
                    'family_id' => $fam->id,
                    'first_name' => $spouseName,
                    'last_name' => $lastName,
                    'full_name' => "{$spouseName} {$lastName}",
                    'relationship_to_head' => 'spouse',
                    'gender' => 'female',
                    'date_of_birth' => Carbon::now()->subYears(rand(35, 65))->toDateString(),
                    'membership_status' => $status,
                    'gdpr_consent' => $gdpr,
                    'communication_consent' => $gdpr,
                    'photo_publication_consent' => $gdpr,
                    'created_by' => $adminUserId
                ]);
                $totalMembersCreated++;

                // Create 1-2 Children
                $kidsCount = rand(1, 2);
                for ($k = 0; $k < $kidsCount; $k++) {
                    $gender = (rand(0, 1) === 0) ? 'male' : 'female';
                    $kidFirstName = ($gender === 'male') ? $fakeFirstNamesMale[array_rand($fakeFirstNamesMale)] : $fakeFirstNamesFemale[array_rand($fakeFirstNamesFemale)];
                    $age = rand(5, 25);
                    $birthDate = Carbon::now()->subYears($age)->toDateString();

                    $childRelation = ($gender === 'male') ? 'son' : 'daughter';

                    $childMember = Member::create([
                        'diocese_id' => $diocese->id,
                        'church_id' => $church->id,
                        'family_id' => $fam->id,
                        'first_name' => $kidFirstName,
                        'last_name' => $lastName,
                        'full_name' => "{$kidFirstName} {$lastName}",
                        'relationship_to_head' => $childRelation,
                        'gender' => $gender,
                        'date_of_birth' => $birthDate,
                        'membership_status' => $status,
                        'gdpr_consent' => $gdpr,
                        'communication_consent' => $gdpr,
                        'photo_publication_consent' => ($age % 2 === 0), // Mix consent flags
                        'created_by' => $adminUserId
                    ]);
                    $totalMembersCreated++;

                    if ($church->short_name === 'Vienna') {
                        if ($age >= 6 && $age <= 16 && count($sundaySchoolStudents) < 40) {
                            $sundaySchoolStudents[] = $childMember;
                        }
                        if ($age >= 15 && $age <= 35) {
                            $youthMembers[] = $childMember;
                        }
                    }
                }

                // Elderly members occasionally
                if (rand(0, 4) === 0) {
                    $elderGender = (rand(0, 1) === 0) ? 'male' : 'female';
                    $elderFirstName = ($elderGender === 'male') ? $fakeFirstNamesMale[array_rand($fakeFirstNamesMale)] : $fakeFirstNamesFemale[array_rand($fakeFirstNamesFemale)];
                    $relation = ($elderGender === 'male') ? 'father' : 'mother';

                    $elderMember = Member::create([
                        'diocese_id' => $diocese->id,
                        'church_id' => $church->id,
                        'family_id' => $fam->id,
                        'first_name' => $elderFirstName,
                        'last_name' => $lastName,
                        'full_name' => "{$elderFirstName} {$lastName}",
                        'relationship_to_head' => $relation,
                        'gender' => $elderGender,
                        'date_of_birth' => Carbon::now()->subYears(rand(70, 85))->toDateString(),
                        'membership_status' => $status,
                        'gdpr_consent' => $gdpr,
                        'communication_consent' => $gdpr,
                        'photo_publication_consent' => $gdpr,
                        'created_by' => $adminUserId
                    ]);
                    $totalMembersCreated++;
                }

                // Inactive member example
                if ($church->short_name === 'Vienna' && $totalMembersCreated === 20) {
                    $headMember->update(['membership_status' => 'inactive']);
                }

                $totalFamiliesCreated++;
            }
        }
        $this->info("Created {$totalFamiliesCreated} families and {$totalMembersCreated} members.");

        // 7. Sacraments and Certificates
        $this->info('Seeding sacraments and certificates...');
        $baptismRequest = CertificateRequest::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_child->id,
            'certificate_type' => 'baptism',
            'purpose' => 'School Enrollment',
            'status' => 'issued',
            'requested_by' => $users['member.familyhead@demo.msoc.test']->id,
            'created_by' => $adminUserId
        ]);

        Storage::put('private/certificates/baptism_cert_1.pdf', 'Mock PDF Data: Baptism Certificate');
        Certificate::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_child->id,
            'certificate_request_id' => $baptismRequest->id,
            'certificate_template_id' => 1,
            'certificate_number' => 'CERT-BAP-' . rand(1000, 9999),
            'certificate_type' => 'baptism',
            'issued_date' => now()->toDateString(),
            'pdf_path' => 'private/certificates/baptism_cert_1.pdf',
            'verification_code' => Str::random(16),
            'status' => 'active',
            'issued_by' => $users['priest.vienna@demo.msoc.test']->id,
        ]);

        // Create remaining requests for demo workflows (pending, approved, rejected, reissued)
        CertificateRequest::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_child1->id,
            'certificate_type' => 'membership',
            'purpose' => 'Sunday School Record',
            'status' => 'submitted',
            'requested_by' => $users['parent.vienna@demo.msoc.test']->id,
            'created_by' => $users['parent.vienna@demo.msoc.test']->id
        ]);
        CertificateRequest::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_child2->id,
            'certificate_type' => 'recommendation',
            'purpose' => 'Relocation Verification',
            'status' => 'approved',
            'requested_by' => $users['parent.vienna@demo.msoc.test']->id,
            'created_by' => $users['parent.vienna@demo.msoc.test']->id
        ]);
        CertificateRequest::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m3_head->id,
            'certificate_type' => 'marriage',
            'purpose' => 'Marriage Preparation',
            'status' => 'rejected',
            'rejection_reason' => 'Incomplete baptism records',
            'requested_by' => $users['member.single@demo.msoc.test']->id,
            'created_by' => $users['member.single@demo.msoc.test']->id
        ]);

        // Sacrament records
        Sacrament::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_child->id,
            'sacrament_type' => 'baptism',
            'sacrament_date' => '2010-10-15',
            'place' => 'Vienna',
            'officiated_by_priest_id' => $priestVienna->id,
            'status' => 'approved',
            'created_by' => $adminUserId
        ]);
        Sacrament::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_child1->id,
            'sacrament_type' => 'holy_communion',
            'sacrament_date' => '2024-05-18',
            'place' => 'Vienna',
            'officiated_by_priest_id' => $priestVienna->id,
            'status' => 'submitted',
            'created_by' => $adminUserId
        ]);

        // Generate 5 dummy certificates in private storage
        for ($i = 2; $i <= 5; $i++) {
            Storage::put("private/certificates/cert_dummy_{$i}.pdf", "Mock PDF Data for Certificate #{$i}");
        }

        // 8. Courses and Events
        $this->info('Seeding courses and events...');
        $course1 = Course::create([
            'diocese_id' => $diocese->id,
            'name' => 'Christian Doctrine & Theology Masterclass',
            'slug' => 'christian-doctrine-theology-masterclass',
            'course_type' => 'bible_course',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $course2 = Course::create([
            'diocese_id' => $diocese->id,
            'name' => 'Introduction to Syriac Heritage & Liturgy',
            'slug' => 'introduction-to-syriac-heritage-liturgy',
            'course_type' => 'liturgy_training',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $course3 = Course::create([
            'diocese_id' => $diocese->id,
            'name' => 'Orthodox Family Counselling & Pastoral Care',
            'slug' => 'orthodox-family-counselling-pastoral-care',
            'course_type' => 'other',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $batch1 = CourseBatch::create([
            'course_id' => $course1->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'batch_name' => 'Spring 2026 Batch',
            'start_datetime' => now()->subMonths(1),
            'end_datetime' => now()->addMonths(2),
            'mode' => 'hybrid',
            'status' => 'ongoing',
            'created_by' => $adminUserId
        ]);

        $batch2 = CourseBatch::create([
            'course_id' => $course2->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'batch_name' => 'Vienna Summer 2026 Batch',
            'start_datetime' => now()->addMonths(1),
            'end_datetime' => now()->addMonths(3),
            'mode' => 'offline',
            'status' => 'upcoming',
            'created_by' => $adminUserId
        ]);

        $batch3 = CourseBatch::create([
            'course_id' => $course3->id,
            'diocese_id' => $diocese->id,
            'church_id' => null, // Diocesan level
            'batch_name' => 'Diocesan Autumn 2026 Batch',
            'start_datetime' => now()->addMonths(3),
            'end_datetime' => now()->addMonths(5),
            'mode' => 'online',
            'status' => 'registration_open',
            'created_by' => $adminUserId
        ]);

        CourseSession::create([
            'course_batch_id' => $batch1->id,
            'title' => 'Intro to Syriac Heritage',
            'session_date' => now()->addDays(2)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'session_order' => 1,
            'status' => 'scheduled',
            'created_by' => $adminUserId
        ]);

        CourseSession::create([
            'course_batch_id' => $batch2->id,
            'title' => 'Introduction to Liturgical Chant',
            'session_date' => now()->addMonths(1)->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'session_order' => 1,
            'status' => 'scheduled',
            'created_by' => $adminUserId
        ]);

        CourseSession::create([
            'course_batch_id' => $batch3->id,
            'title' => 'Family Values in the Orthodox Tradition',
            'session_date' => now()->addMonths(3)->addDays(5)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '20:30:00',
            'session_order' => 1,
            'status' => 'scheduled',
            'created_by' => $adminUserId
        ]);

        // Enroll members in course
        CourseRegistration::create([
            'course_batch_id' => $batch1->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'registration_status' => 'confirmed',
            'registered_by' => $adminUserId
        ]);
        CourseRegistration::create([
            'course_batch_id' => $batch1->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_head->id,
            'registration_status' => 'completed',
            'registered_by' => $adminUserId
        ]);
        CourseRegistration::create([
            'course_batch_id' => $batch2->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'registration_status' => 'confirmed',
            'registered_by' => $adminUserId
        ]);
        CourseRegistration::create([
            'course_batch_id' => $batch2->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_head->id,
            'registration_status' => 'confirmed',
            'registered_by' => $adminUserId
        ]);
        CourseRegistration::create([
            'course_batch_id' => $batch3->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'registration_status' => 'confirmed',
            'registered_by' => $adminUserId
        ]);

        // Events
        $event1 = Event::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'title' => 'Vienna Parish Feast 2026',
            'slug' => 'vienna-parish-feast-2026',
            'event_type' => 'feast',
            'description' => 'Annual feast celebrations of St. Mary\'s Church Vienna.',
            'start_datetime' => now()->addDays(5)->toDateTimeString(),
            'end_datetime' => now()->addDays(7)->toDateTimeString(),
            'status' => 'registration_open',
            'created_by' => $adminUserId
        ]);

        $event2 = Event::create([
            'diocese_id' => $diocese->id,
            'church_id' => null, // Diocesan level
            'title' => 'Diocesan Youth Retreat 2026',
            'slug' => 'diocesan-youth-retreat-2026',
            'event_type' => 'retreat',
            'description' => 'Annual spiritual retreat for youth across the European diocese.',
            'start_datetime' => now()->addDays(15)->toDateTimeString(),
            'end_datetime' => now()->addDays(18)->toDateTimeString(),
            'status' => 'registration_open',
            'created_by' => $adminUserId
        ]);

        $event3 = Event::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'title' => 'Parish Family Conference 2026',
            'slug' => 'parish-family-conference-2026',
            'event_type' => 'family_conference',
            'description' => 'Family gathering and conference for St. Mary\'s Vienna members.',
            'start_datetime' => now()->addDays(30)->toDateTimeString(),
            'end_datetime' => now()->addDays(31)->toDateTimeString(),
            'status' => 'draft',
            'created_by' => $adminUserId
        ]);

        EventRegistration::create([
            'event_id' => $event1->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'registration_status' => 'confirmed',
            'qr_code' => Str::random(16),
            'registered_by' => $adminUserId
        ]);

        EventRegistration::create([
            'event_id' => $event2->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'registration_status' => 'confirmed',
            'qr_code' => Str::random(16),
            'registered_by' => $adminUserId
        ]);
        EventRegistration::create([
            'event_id' => $event2->id,
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_head->id,
            'registration_status' => 'confirmed',
            'qr_code' => Str::random(16),
            'registered_by' => $adminUserId
        ]);

        // 9. Sunday School
        $this->info('Seeding Sunday School (MJSSA)...');
        $ssYear = SundaySchoolAcademicYear::create([
            'diocese_id' => $diocese->id,
            'name' => 'Academic Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $adminUserId
        ]);

        $levels = [];
        for ($l = 1; $l <= 5; $l++) {
            $levels[$l] = SundaySchoolLevel::create([
                'diocese_id' => $diocese->id,
                'level_name' => "Level {$l}",
                'level_code' => "L{$l}",
                'sort_order' => $l
            ]);
        }

        $teacherViennaProfile = SundaySchoolTeacher::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'user_id' => $users['teacher.vienna@demo.msoc.test']->id,
            'full_name' => 'Vienna Teacher',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $classL1 = SundaySchoolClass::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'academic_year_id' => $ssYear->id,
            'level_id' => $levels[1]->id,
            'class_name' => 'Grade 1 Class',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        // Assign teacher to Vienna class specifically
        SundaySchoolClassTeacherAssignment::create([
            'class_id' => $classL1->id,
            'teacher_id' => $teacherViennaProfile->id,
            'role' => 'primary',
            'assigned_from' => now()->subMonths(5)->toDateString(),
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        // Enroll students
        $studentRecords = [];
        foreach ($sundaySchoolStudents as $idx => $studentMember) {
            $studentRecords[] = SundaySchoolStudent::create([
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'academic_year_id' => $ssYear->id,
                'class_id' => $classL1->id,
                'member_id' => $studentMember->id,
                'enrollment_date' => now()->subMonths(5)->toDateString(),
                'enrollment_status' => 'active',
                'created_by' => $adminUserId
            ]);
        }

        // Exams and marks
        $exam1 = SundaySchoolExam::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'academic_year_id' => $ssYear->id,
            'class_id' => $classL1->id,
            'exam_name' => 'First Term Examination',
            'exam_type' => 'midterm',
            'exam_date' => now()->subMonths(1)->toDateString(),
            'max_marks' => 100,
            'pass_marks' => 45,
            'status' => 'completed',
            'created_by' => $adminUserId
        ]);

        foreach ($studentRecords as $idx => $record) {
            SundaySchoolMark::create([
                'exam_id' => $exam1->id,
                'student_id' => $record->id,
                'marks_obtained' => 85 - ($idx * 15),
                'grade' => ($idx === 0) ? 'A' : 'B',
                'result_status' => 'pass',
                'entered_by' => $users['teacher.vienna@demo.msoc.test']->id,
                'verified_by' => $users['priest.vienna@demo.msoc.test']->id,
                'verified_at' => now()
            ]);
        }

        // Progress report PDF dummy in private storage
        Storage::put('private/sunday_school/progress_report_1.pdf', 'Progress Report Mock PDF Data');
        SundaySchoolProgressReport::create([
            'student_id' => $studentRecords[0]->id,
            'academic_year_id' => $ssYear->id,
            'class_id' => $classL1->id,
            'attendance_percentage' => 95.00,
            'total_marks' => 85.00,
            'grade' => 'A',
            'pdf_path' => 'private/sunday_school/progress_report_1.pdf',
            'generated_by' => $users['teacher.vienna@demo.msoc.test']->id,
            'generated_at' => now()
        ]);

        // 10. Youth Association and Marthamariyam Samajam
        $this->info('Seeding Youth Association & Samajam...');
        $youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();
        $samajamOrg = MinistryOrganization::where('slug', 'msoc-europe-marthamariyam-samajam')->first();

        if ($youthOrg) {
            // Vienna Unit
            $youthVienna = MinistryUnit::create([
                'ministry_organization_id' => $youthOrg->id,
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'unit_name' => 'MSOC Vienna Youth Association',
                'unit_level' => 'parish',
                'status' => 'active',
                'created_by' => $adminUserId
            ]);

            // Register Youth Members
            foreach ($youthMembers as $youthM) {
                MinistryMembership::create([
                    'ministry_unit_id' => $youthVienna->id,
                    'diocese_id' => $diocese->id,
                    'church_id' => $vienna->id,
                    'member_id' => $youthM->id,
                    'joined_date' => now()->subYears(1),
                    'status' => 'active',
                    'created_by' => $adminUserId
                ]);
            }

            // Coordinator Bearer
            MinistryOfficeBearer::create([
                'ministry_unit_id' => $youthVienna->id,
                'member_id' => $m3_head->id, // Anna Single
                'role_title' => 'Parish Youth Secretary',
                'role_category' => 'secretary',
                'start_date' => now()->subMonths(6)->toDateString(),
                'status' => 'active',
                'created_by' => $adminUserId
            ]);

            // Seed an ineligible youth member example (age 46)
            $oldYouthMember = Member::create([
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'family_id' => $f1->id,
                'first_name' => 'Old',
                'last_name' => 'Member',
                'full_name' => 'Old Member',
                'relationship_to_head' => 'relative',
                'gender' => 'male',
                'date_of_birth' => '1980-01-01', // Age 46 (>35)
                'membership_status' => 'active',
                'gdpr_consent' => true,
                'created_by' => $adminUserId
            ]);

            MinistryMembership::create([
                'ministry_unit_id' => $youthVienna->id,
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'member_id' => $oldYouthMember->id,
                'joined_date' => now()->subMonths(1),
                'status' => 'pending', // Pending override/rejection
                'remarks' => 'Ineligible by age rule, requires override.',
                'created_by' => $adminUserId
            ]);
        }

        if ($samajamOrg) {
            $samajamVienna = MinistryUnit::create([
                'ministry_organization_id' => $samajamOrg->id,
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'unit_name' => 'MSOC Vienna Marthamariyam Samajam',
                'unit_level' => 'parish',
                'status' => 'active',
                'created_by' => $adminUserId
            ]);

            // Register spouse
            MinistryMembership::create([
                'ministry_unit_id' => $samajamVienna->id,
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'member_id' => $m1_spouse->id,
                'joined_date' => now()->subYears(2),
                'status' => 'active',
                'created_by' => $adminUserId
            ]);
        }

        // 11. Finance
        $this->info('Seeding finance records...');
        $donationCat = FinanceCategory::where('category_type', 'income')->first() ?: FinanceCategory::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'name' => 'Donations & Tithes',
            'slug' => 'donations-tithes',
            'category_type' => 'income',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $expenseCat = FinanceCategory::where('category_type', 'expense')->first() ?: FinanceCategory::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'name' => 'Rent & Charity Expenses',
            'slug' => 'rent-charity-expenses',
            'category_type' => 'expense',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);

        $donation = Donation::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'finance_category_id' => $donationCat->id,
            'donor_name' => $m1_head->full_name,
            'donation_type' => 'general',
            'amount' => 500.00,
            'payment_method' => 'bank_transfer',
            'status' => 'received',
            'received_date' => now()->toDateString(),
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        // Generate 5 private receipts PDFs
        Storage::put('private/receipts/receipt_donation_1.pdf', 'Donation Receipt #1 PDF Mock Content');
        Receipt::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'receipt_number' => 'REC-2026-0001',
            'receipt_type' => 'donation',
            'receiptable_type' => Donation::class,
            'receiptable_id' => $donation->id,
            'payer_name' => $m1_head->full_name,
            'amount' => 500.00,
            'payment_method' => 'bank_transfer',
            'receipt_date' => now()->toDateString(),
            'pdf_path' => 'private/receipts/receipt_donation_1.pdf',
            'status' => 'issued',
            'issued_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        for ($r = 2; $r <= 5; $r++) {
            Storage::put("private/receipts/receipt_dummy_{$r}.pdf", "Mock Receipt PDF Data #{$r}");
        }

        // Expenses (pending, approved, paid, rejected)
        ExpenseRecord::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'finance_category_id' => $expenseCat->id,
            'title' => 'Parish Hall Rent',
            'description' => 'Parish Hall Rent for May',
            'amount' => 1200.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => 'submitted', // Needs priest/admin approval
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        ExpenseRecord::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'finance_category_id' => $expenseCat->id,
            'title' => 'Altar Supplies',
            'description' => 'Altar supplies purchase',
            'amount' => 300.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'paid',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        // 11b. Double-Entry Accounting Seeding (Phase 13)
        $this->info('Seeding double-entry accounting records...');

        // 1. Chart of Accounts
        $coaAsset = FinanceChartAccount::firstOrCreate(
            ['code' => '1000'],
            ['name' => 'Assets & Cash/Bank Accounts', 'type' => 'asset', 'description' => 'Current and fixed assets, including cash, bank accounts.', 'is_active' => true]
        );
        $coaLiability = FinanceChartAccount::firstOrCreate(
            ['code' => '2000'],
            ['name' => 'Liabilities & Creditors', 'type' => 'liability', 'description' => 'Current and long-term liabilities.', 'is_active' => true]
        );
        $coaEquity = FinanceChartAccount::firstOrCreate(
            ['code' => '3000'],
            ['name' => 'Equity, Reserves & Funds', 'type' => 'equity', 'description' => 'Parish and Diocesan accumulated reserves.', 'is_active' => true]
        );
        $coaRevenue = FinanceChartAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Operating Revenues / Income', 'type' => 'revenue', 'description' => 'All sources of incoming funds.', 'is_active' => true]
        );
        $coaExpense = FinanceChartAccount::firstOrCreate(
            ['code' => '5000'],
            ['name' => 'Operating Expenses', 'type' => 'expense', 'description' => 'All categories of expenses.', 'is_active' => true]
        );

        // 2. Fund Classes
        $fundGen = FinanceFundClass::firstOrCreate(
            ['code' => 'GEN'],
            ['name' => 'General Fund', 'description' => 'Unrestricted general operating fund.', 'is_active' => true]
        );
        $fundBld = FinanceFundClass::firstOrCreate(
            ['code' => 'BLD'],
            ['name' => 'Building Fund', 'description' => 'Restricted fund for building purchase/maintenance.', 'is_active' => true]
        );
        $fundCharity = FinanceFundClass::firstOrCreate(
            ['code' => 'CHA'],
            ['name' => 'Charity Fund', 'description' => 'Restricted fund for benevolence and charity.', 'is_active' => true]
        );
        $fundSun = FinanceFundClass::firstOrCreate(
            ['code' => 'SUN'],
            ['name' => 'Sunday School Fund', 'description' => 'Restricted fund for Sunday School.', 'is_active' => true]
        );
        $fundYouth = FinanceFundClass::firstOrCreate(
            ['code' => 'YOU'],
            ['name' => 'Youth Association Fund', 'description' => 'Restricted fund for youth activities.', 'is_active' => true]
        );
        $fundMarth = FinanceFundClass::firstOrCreate(
            ['code' => 'MAR'],
            ['name' => 'Marthamariyam Samajam Fund', 'description' => 'Restricted fund for women\'s fellowship.', 'is_active' => true]
        );
        $fundMission = FinanceFundClass::firstOrCreate(
            ['code' => 'MIS'],
            ['name' => 'Mission Fund', 'description' => 'Restricted fund for mission work.', 'is_active' => true]
        );
        $fundAltar = FinanceFundClass::firstOrCreate(
            ['code' => 'ALT'],
            ['name' => 'Altar and Liturgical Fund', 'description' => 'Restricted fund for altar supplies.', 'is_active' => true]
        );

        // 3. Programme Accounts (Cost Centres)
        $progPerunnal = FinanceProgrammeAccount::firstOrCreate(
            ['code' => 'PERUNNAL-2026', 'church_id' => $vienna->id],
            ['name' => 'Parish Perunnal Feast 2026', 'description' => 'Income and expenses for the annual parish feast.', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'is_active' => true]
        );
        $progParishDay = FinanceProgrammeAccount::firstOrCreate(
            ['code' => 'PARISHDAY-2026', 'church_id' => $vienna->id],
            ['name' => 'Parish Day 2026', 'description' => 'Income and expenses for Parish Day celebrations.', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'is_active' => true]
        );
        $progDiocMission = FinanceProgrammeAccount::firstOrCreate(
            ['code' => 'PROG-DIOC-MISSION-2026', 'church_id' => null],
            ['name' => 'Diocesan Mission & Charity Fund', 'description' => 'Missionary operations and charity initiatives across the diocese.', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]
        );
        $progDiocYouth = FinanceProgrammeAccount::firstOrCreate(
            ['code' => 'PROG-DIOC-YOUTH-CAMP', 'church_id' => null],
            ['name' => 'Annual Diocesan Youth Conference', 'description' => 'Consolidated conference and spiritual camp for youths.', 'start_date' => '2026-07-01', 'end_date' => '2026-07-15', 'is_active' => true]
        );

        // 4. Money Accounts
        $cashAcc = FinanceMoneyAccount::firstOrCreate(
            ['code' => "CASH-CHURCH-{$vienna->id}"],
            ['church_id' => $vienna->id, 'name' => 'Vienna Cash Safe', 'type' => 'cash', 'currency' => 'EUR', 'is_active' => true]
        );
        $bankAcc = FinanceMoneyAccount::firstOrCreate(
            ['code' => "BANK-CHURCH-{$vienna->id}"],
            [
                'church_id' => $vienna->id,
                'name' => 'Vienna Sparkasse Bank',
                'type' => 'bank',
                'bank_name' => 'Erste Bank Sparkasse',
                'account_number' => 'AT12345678901234',
                'iban' => 'AT12345678901234',
                'currency' => 'EUR',
                'is_active' => true
            ]
        );

        // 5. Income Heads
        $incMemb = FinanceIncomeHead::firstOrCreate(
            ['code' => 'INC-MEMB', 'chart_account_id' => $coaRevenue->id],
            ['name' => 'Membership Contributions', 'description' => 'Monthly contributions from registered families.', 'is_active' => true]
        );
        $incNercha = FinanceIncomeHead::firstOrCreate(
            ['code' => 'INC-NERCHA', 'chart_account_id' => $coaRevenue->id],
            ['name' => 'Nercha / Qurbana Offerings', 'description' => 'Collections received during Holy Qurbana.', 'is_active' => true]
        );
        $incSpecial = FinanceIncomeHead::firstOrCreate(
            ['code' => 'INC-SPECIAL', 'chart_account_id' => $coaRevenue->id],
            ['name' => 'Special Feast collections', 'description' => 'Collections specifically for feasts/programmes.', 'is_active' => true]
        );

        // 6. Expense Heads
        $expRent = FinanceExpenseHead::firstOrCreate(
            ['code' => 'EXP-RENT', 'chart_account_id' => $coaExpense->id],
            ['name' => 'Parish Hall Rent', 'description' => 'Monthly rent for liturgical hall.', 'is_active' => true]
        );
        $expStipend = FinanceExpenseHead::firstOrCreate(
            ['code' => 'EXP-STIP', 'chart_account_id' => $coaExpense->id],
            ['name' => 'Priest Stipends', 'description' => 'Monthly basic stipends for clergy.', 'is_active' => true]
        );
        $expTravel = FinanceExpenseHead::firstOrCreate(
            ['code' => 'EXP-TRAVEL', 'chart_account_id' => $coaExpense->id],
            ['name' => 'Priest Travel & Mileage', 'description' => 'Mileage and travel allowance for clergy.', 'is_active' => true]
        );

        // 7. Cash Batch (Open)
        $cashBatchOpen = FinanceCashBatch::create([
            'church_id' => $vienna->id,
            'money_account_id' => $cashAcc->id,
            'opened_at' => now()->subDays(1),
            'opened_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id,
            'status' => 'open',
            'declared_amount' => 0.00,
            'system_amount' => 0.00,
            'difference' => 0.00
        ]);

        // 8. Cash Batch (Closed with count details)
        $cashBatchClosed = FinanceCashBatch::create([
            'church_id' => $vienna->id,
            'money_account_id' => $cashAcc->id,
            'opened_at' => now()->subDays(5),
            'closed_at' => now()->subDays(4),
            'opened_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id,
            'closed_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id,
            'status' => 'closed',
            'counting_details' => [
                'bills' => [
                    '50' => 5,
                    '20' => 10,
                    '10' => 15,
                    '5' => 20
                ],
                'coins' => [
                    '2' => 25,
                    '1' => 50,
                    '0.5' => 100
                ]
            ],
            'declared_amount' => 850.00,
            'system_amount' => 850.00,
            'difference' => 0.00
        ]);

        // 9. Income Entry (Posted)
        $incomeHeader = FinanceIncomeHeader::create([
            'church_id' => $vienna->id,
            'income_date' => now()->subDays(3)->toDateString(),
            'money_account_id' => $bankAcc->id,
            'reference_no' => 'BANK-TR-109283',
            'remarks' => 'Sunday collection and member monthly tithe',
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        $incomeLine1 = FinanceIncomeLine::create([
            'income_header_id' => $incomeHeader->id,
            'income_head_id' => $incMemb->id,
            'fund_class_id' => $fundGen->id,
            'programme_account_id' => null,
            'member_id' => $m1_head->id,
            'donor_name' => $m1_head->full_name,
            'amount' => 150.00,
            'remarks' => 'Monthly membership contribution Yohannan'
        ]);

        $incomeLine2 = FinanceIncomeLine::create([
            'income_header_id' => $incomeHeader->id,
            'income_head_id' => $incNercha->id,
            'fund_class_id' => $fundGen->id,
            'programme_account_id' => $progPerunnal->id,
            'member_id' => null,
            'donor_name' => 'General Congregation',
            'amount' => 350.00,
            'remarks' => 'Nercha collection during Perunnal feast'
        ]);

        // Generate Receipt for the Yohannan contribution
        $receipt = FinanceReceipt::create([
            'income_header_id' => $incomeHeader->id,
            'receipt_number' => 'VIE-2026-000001',
            'receipt_date' => now()->subDays(3)->toDateString(),
            'received_from' => $m1_head->full_name,
            'member_id' => $m1_head->id,
            'payment_method' => 'bank_transfer',
            'total_amount' => 500.00,
            'status' => 'active'
        ]);

        FinanceReceiptLine::create([
            'receipt_id' => $receipt->id,
            'income_line_id' => $incomeLine1->id,
            'income_head_id' => $incMemb->id,
            'amount' => 150.00,
            'description' => 'Membership Contribution'
        ]);

        FinanceReceiptLine::create([
            'receipt_id' => $receipt->id,
            'income_line_id' => $incomeLine2->id,
            'income_head_id' => $incNercha->id,
            'amount' => 350.00,
            'description' => 'Nercha / Offering (Perunnal)'
        ]);

        // Save Mock PDF
        Storage::put('private/receipts/VIE-2026-000001.pdf', 'Mock Double Entry PDF receipt contents');

        // Create Journal Batch and Ledger Entries for Income (BALANCED)
        $journalIncome = FinanceJournalBatch::create([
            'church_id' => $vienna->id,
            'batch_date' => now()->subDays(3)->toDateString(),
            'reference' => 'INC-BATCH-' . $incomeHeader->id,
            'source' => 'income',
            'source_id' => $incomeHeader->id,
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        // Debit Asset (Bank account)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalIncome->id,
            'chart_account_id' => $coaAsset->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(3)->toDateString(),
            'debit' => 500.00,
            'credit' => 0.00,
            'description' => 'Deposit to Sparkasse Bank'
        ]);

        // Credit Revenue (Membership)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalIncome->id,
            'chart_account_id' => $coaRevenue->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(3)->toDateString(),
            'debit' => 0.00,
            'credit' => 150.00,
            'description' => 'Membership contribution John Familyhead'
        ]);

        // Credit Revenue (Nercha)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalIncome->id,
            'chart_account_id' => $coaRevenue->id,
            'fund_class_id' => $fundGen->id,
            'programme_account_id' => $progPerunnal->id,
            'entry_date' => now()->subDays(3)->toDateString(),
            'debit' => 0.00,
            'credit' => 350.00,
            'description' => 'Nercha collection Perunnal'
        ]);

        // 10. Expense Entry (Posted)
        $expenseHeader = FinanceExpenseHeader::create([
            'church_id' => $vienna->id,
            'expense_date' => now()->subDays(2)->toDateString(),
            'money_account_id' => $bankAcc->id,
            'voucher_number' => 'VIE-EXP-2026-000001',
            'reference_no' => 'REF-RENT-MAY',
            'payee_name' => 'Diocesan Center Vienna',
            'remarks' => 'Hall booking & rental monthly charge',
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        FinanceExpenseLine::create([
            'expense_header_id' => $expenseHeader->id,
            'expense_head_id' => $expRent->id,
            'fund_class_id' => $fundGen->id,
            'programme_account_id' => null,
            'amount' => 1200.00,
            'remarks' => 'Monthly Rent for May'
        ]);

        // Create Journal Batch and Ledger Entries for Expense (BALANCED)
        $journalExpense = FinanceJournalBatch::create([
            'church_id' => $vienna->id,
            'batch_date' => now()->subDays(2)->toDateString(),
            'reference' => 'EXP-BATCH-' . $expenseHeader->id,
            'source' => 'expense',
            'source_id' => $expenseHeader->id,
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        // Debit Expense
        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalExpense->id,
            'chart_account_id' => $coaExpense->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(2)->toDateString(),
            'debit' => 1200.00,
            'credit' => 0.00,
            'description' => 'Hall Rent payment'
        ]);

        // Credit Asset (Bank account)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalExpense->id,
            'chart_account_id' => $coaAsset->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(2)->toDateString(),
            'debit' => 0.00,
            'credit' => 1200.00,
            'description' => 'Hall Rent payment from bank account'
        ]);

        // 11. Priest Payment (Stipend & Travel)
        $priestPayment = FinancePriestPayment::create([
            'church_id' => $vienna->id,
            'priest_profile_id' => $priestVienna->id,
            'payment_date' => now()->subDays(1)->toDateString(),
            'type' => 'stipend',
            'amount' => 1500.00,
            'description' => 'Monthly Stipend for Fr. Thomas - May 2026',
            'status' => 'paid'
        ]);

        $priestTravel = FinancePriestPayment::create([
            'church_id' => $vienna->id,
            'priest_profile_id' => $priestVienna->id,
            'payment_date' => now()->subDays(1)->toDateString(),
            'type' => 'travel',
            'amount' => 63.00,
            'travel_distance_km' => 150.00,
            'travel_rate_per_km' => 0.4200,
            'description' => 'Travel reimbursement Vienna-Stuttgart-Vienna',
            'status' => 'paid'
        ]);

        // Link priest payment to an expense header/line
        $priestExpenseHeader = FinanceExpenseHeader::create([
            'church_id' => $vienna->id,
            'expense_date' => now()->subDays(1)->toDateString(),
            'money_account_id' => $bankAcc->id,
            'voucher_number' => 'VIE-EXP-2026-000002',
            'payee_name' => 'Rev. Fr. Thomas Kochupurackal',
            'remarks' => 'Clergy payment stipend & travel mileage',
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        FinanceExpenseLine::create([
            'expense_header_id' => $priestExpenseHeader->id,
            'expense_head_id' => $expStipend->id,
            'fund_class_id' => $fundGen->id,
            'amount' => 1500.00,
            'remarks' => 'Stipend Fr. Thomas'
        ]);

        FinanceExpenseLine::create([
            'expense_header_id' => $priestExpenseHeader->id,
            'expense_head_id' => $expTravel->id,
            'fund_class_id' => $fundGen->id,
            'amount' => 63.00,
            'remarks' => 'Travel allowance 150km @ 0.42 EUR/km'
        ]);

        $priestPayment->update(['expense_header_id' => $priestExpenseHeader->id]);
        $priestTravel->update(['expense_header_id' => $priestExpenseHeader->id]);

        // Balanced Ledger entries for priest payment
        $journalPriest = FinanceJournalBatch::create([
            'church_id' => $vienna->id,
            'batch_date' => now()->subDays(1)->toDateString(),
            'reference' => 'PRIEST-PAY-' . $priestVienna->id,
            'source' => 'expense',
            'source_id' => $priestExpenseHeader->id,
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalPriest->id,
            'chart_account_id' => $coaExpense->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(1)->toDateString(),
            'debit' => 1500.00,
            'credit' => 0.00,
            'description' => 'Clergy stipend'
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalPriest->id,
            'chart_account_id' => $coaExpense->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(1)->toDateString(),
            'debit' => 63.00,
            'credit' => 0.00,
            'description' => 'Clergy mileage reimbursement'
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalPriest->id,
            'chart_account_id' => $coaAsset->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(1)->toDateString(),
            'debit' => 0.00,
            'credit' => 1563.00,
            'description' => 'Payment to Fr. Thomas from Bank Account'
        ]);

        // 12. Transfers (Cash Deposit)
        $transfer = FinanceTransfer::create([
            'church_id' => $vienna->id,
            'transfer_date' => now()->subDays(4)->toDateString(),
            'from_account_id' => $cashAcc->id,
            'to_account_id' => $bankAcc->id,
            'amount' => 400.00,
            'reference' => 'CASH-DEP-001',
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        $journalTransfer = FinanceJournalBatch::create([
            'church_id' => $vienna->id,
            'batch_date' => now()->subDays(4)->toDateString(),
            'reference' => 'TR-BATCH-' . $transfer->id,
            'source' => 'transfer',
            'source_id' => $transfer->id,
            'status' => 'posted',
            'created_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalTransfer->id,
            'chart_account_id' => $coaAsset->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(4)->toDateString(),
            'debit' => 400.00,
            'credit' => 0.00,
            'description' => 'Transfer deposit to Sparkasse'
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $journalTransfer->id,
            'chart_account_id' => $coaAsset->id,
            'fund_class_id' => $fundGen->id,
            'entry_date' => now()->subDays(4)->toDateString(),
            'debit' => 0.00,
            'credit' => 400.00,
            'description' => 'Transfer withdrawal from cash safe'
        ]);

        // 13. Bank Statement & Reconciliation
        $bankImport = FinanceBankStatementImport::create([
            'money_account_id' => $bankAcc->id,
            'import_date' => now()->toDateString(),
            'file_name' => 'bank_statement_vienna_june.csv',
            'imported_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        $bsLine1 = FinanceBankStatementLine::create([
            'bank_statement_import_id' => $bankImport->id,
            'booking_date' => now()->subDays(3)->toDateString(),
            'value_date' => now()->subDays(3)->toDateString(),
            'partner_name' => 'John Familyhead',
            'description' => 'BANK-TR-109283 John Yohannan donation',
            'amount' => 500.00,
            'is_matched' => true
        ]);

        $bsLine2 = FinanceBankStatementLine::create([
            'bank_statement_import_id' => $bankImport->id,
            'booking_date' => now()->subDays(2)->toDateString(),
            'value_date' => now()->subDays(2)->toDateString(),
            'partner_name' => 'Diocesan Center Vienna',
            'description' => 'REF-RENT-MAY hall rent payment',
            'amount' => -1200.00,
            'is_matched' => true
        ]);

        $bsLine3 = FinanceBankStatementLine::create([
            'bank_statement_import_id' => $bankImport->id,
            'booking_date' => now()->subDays(1)->toDateString(),
            'value_date' => now()->subDays(1)->toDateString(),
            'partner_name' => 'Rev. Fr. Thomas Kochupurackal',
            'description' => 'Clergy stipend pay May 2026',
            'amount' => -1563.00,
            'is_matched' => false
        ]);

        FinanceBankMatch::create([
            'bank_statement_line_id' => $bsLine1->id,
            'matchable_type' => FinanceIncomeHeader::class,
            'matchable_id' => $incomeHeader->id,
            'matched_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        FinanceBankMatch::create([
            'bank_statement_line_id' => $bsLine2->id,
            'matchable_type' => FinanceExpenseHeader::class,
            'matchable_id' => $expenseHeader->id,
            'matched_by' => $users['parishtreasurer.vienna@demo.msoc.test']->id
        ]);

        // Seed finance and double-entry accounts records for all other churches
        // Seed finance and double-entry accounts records for all other churches
        $otherChurches = Church::where('id', '!=', $vienna->id)->get();
        $incomeHeads = FinanceIncomeHead::all();
        $expenseHeads = FinanceExpenseHead::all();
        $fundClasses = FinanceFundClass::all();

        foreach ($otherChurches as $church) {
            // 1. Categories (legacy fallback)
            $churchDonationCat = FinanceCategory::create([
                'diocese_id' => $diocese->id,
                'church_id' => $church->id,
                'name' => 'Donations & Tithes',
                'slug' => 'donations-tithes-' . $church->id,
                'category_type' => 'income',
                'status' => 'active',
                'created_by' => $adminUserId
            ]);

            $churchExpenseCat = FinanceCategory::create([
                'diocese_id' => $diocese->id,
                'church_id' => $church->id,
                'name' => 'Rent & Charity Expenses',
                'slug' => 'rent-charity-expenses-' . $church->id,
                'category_type' => 'expense',
                'status' => 'active',
                'created_by' => $adminUserId
            ]);

            // 2. Money Accounts (2 per church - Cash and Bank)
            $churchCashAcc = FinanceMoneyAccount::create([
                'church_id' => $church->id,
                'code' => "CASH-CHURCH-{$church->id}",
                'name' => "{$church->short_name} Cash Safe",
                'type' => 'cash',
                'currency' => 'EUR',
                'is_active' => true
            ]);

            $churchBankAcc = FinanceMoneyAccount::create([
                'church_id' => $church->id,
                'code' => "BANK-CHURCH-{$church->id}",
                'name' => "{$church->short_name} Bank Account",
                'type' => 'bank',
                'bank_name' => 'Local Sparkasse',
                'account_number' => 'AT' . rand(1000000000, 9999999999),
                'iban' => 'AT' . rand(1000000000, 9999999999),
                'currency' => 'EUR',
                'is_active' => true
            ]);

            // 3. Programme Accounts (Cost Centres) - 5 per church
            $churchProg1 = FinanceProgrammeAccount::create([
                'code' => "PROG-{$church->id}-FEAST",
                'church_id' => $church->id,
                'name' => "Parish Feast {$church->short_name}",
                'description' => 'Feast operations',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'is_active' => true
            ]);

            $churchProg2 = FinanceProgrammeAccount::create([
                'code' => "PROG-{$church->id}-CAMP",
                'church_id' => $church->id,
                'name' => "Youth Camp {$church->short_name}",
                'description' => 'Youth fellowship camp',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-15',
                'is_active' => true
            ]);

            $churchProg3 = FinanceProgrammeAccount::create([
                'code' => "PROG-SUNDAY-SCHOOL-{$church->id}",
                'church_id' => $church->id,
                'name' => "Sunday School Project {$church->short_name}",
                'description' => 'Local Sunday school activities, materials, and kids festival.',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_active' => true
            ]);

            $churchProg4 = FinanceProgrammeAccount::create([
                'code' => "PROG-BUILDING-RENOV-{$church->id}",
                'church_id' => $church->id,
                'name' => "Parish Renovation Fund {$church->short_name}",
                'description' => 'Renovation, styling, and upgrade of local church structures.',
                'start_date' => '2026-03-01',
                'end_date' => '2027-02-28',
                'is_active' => true
            ]);

            $churchProg5 = FinanceProgrammeAccount::create([
                'code' => "PROG-CHARITY-LOCAL-{$church->id}",
                'church_id' => $church->id,
                'name' => "Parish Charity Drive {$church->short_name}",
                'description' => 'Local community outreach, food donation, and support for families in need.',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_active' => true
            ]);

            // Get members for this church
            $churchMembers = Member::where('church_id', $church->id)->get();
            $donorMember = $churchMembers->first();
            $donorMemberId = $donorMember ? $donorMember->id : null;
            $donorName = $donorMember ? $donorMember->full_name : 'Parishioner';

            // 4. Seed 40 Income Entries & 40 Receipts per church
            for ($i = 1; $i <= 40; $i++) {
                $randHead = $incomeHeads->random();
                $randFund = $fundClasses->random();
                $randProg = [$churchProg1, $churchProg2, $churchProg3, $churchProg4, $churchProg5][rand(0, 4)];
                $amount = rand(25, 450);
                $date = now()->subDays(rand(5, 120))->toDateString();

                $incHeader = FinanceIncomeHeader::create([
                    'church_id' => $church->id,
                    'income_date' => $date,
                    'money_account_id' => $churchBankAcc->id,
                    'reference_no' => "TR-{$church->id}-INC-{$i}",
                    'remarks' => "Weekly offering count {$i}",
                    'status' => 'posted',
                    'created_by' => $adminUserId
                ]);

                $incLine = FinanceIncomeLine::create([
                    'income_header_id' => $incHeader->id,
                    'income_head_id' => $randHead->id,
                    'fund_class_id' => $randFund->id,
                    'programme_account_id' => $randProg->id,
                    'member_id' => $donorMemberId,
                    'donor_name' => $donorName,
                    'amount' => $amount,
                    'remarks' => "Line offering item {$i}"
                ]);

                $recNumber = ReceiptNumberService::generateNextNumber($diocese->id, $church->id, 2026);
                $receipt = FinanceReceipt::create([
                    'income_header_id' => $incHeader->id,
                    'receipt_number' => $recNumber,
                    'receipt_date' => $date,
                    'received_from' => $donorName,
                    'member_id' => $donorMemberId,
                    'payment_method' => 'bank_transfer',
                    'total_amount' => $amount,
                    'status' => 'active'
                ]);

                FinanceReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'income_line_id' => $incLine->id,
                    'income_head_id' => $randHead->id,
                    'amount' => $amount,
                    'description' => "Contribution offering {$i}"
                ]);

                Storage::put("private/receipts/{$recNumber}.pdf", "Mock Receipt PDF content for {$recNumber}");

                // Balanced Journal & Ledger entries
                $journalIncome = FinanceJournalBatch::create([
                    'diocese_id' => $diocese->id,
                    'church_id' => $church->id,
                    'batch_date' => $date,
                    'reference' => 'INC-BATCH-' . $incHeader->id,
                    'source' => 'income',
                    'source_id' => $incHeader->id,
                    'status' => 'posted',
                    'created_by' => $adminUserId
                ]);

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $journalIncome->id,
                    'chart_account_id' => $coaAsset->id,
                    'fund_class_id' => $randFund->id,
                    'entry_date' => $date,
                    'debit' => $amount,
                    'credit' => 0.00,
                    'description' => 'Deposit to Sparkasse Bank'
                ]);

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $journalIncome->id,
                    'chart_account_id' => $coaRevenue->id,
                    'fund_class_id' => $randFund->id,
                    'programme_account_id' => $randProg->id,
                    'entry_date' => $date,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => 'Revenue Credit'
                ]);
            }

            // 5. Seed 16 Expense Entries per church
            for ($e = 1; $e <= 16; $e++) {
                $randHead = $expenseHeads->random();
                $randFund = $fundClasses->random();
                $amount = rand(50, 600);
                $date = now()->subDays(rand(5, 120))->toDateString();

                $expHeader = FinanceExpenseHeader::create([
                    'church_id' => $church->id,
                    'expense_date' => $date,
                    'money_account_id' => $churchBankAcc->id,
                    'voucher_number' => "EXP-{$church->id}-VOUCH-{$e}",
                    'reference_no' => "REF-EXP-{$church->id}-{$e}",
                    'payee_name' => "Vendor {$church->short_name} {$e}",
                    'remarks' => "Operational expense voucher {$e}",
                    'status' => 'posted',
                    'created_by' => $adminUserId
                ]);

                FinanceExpenseLine::create([
                    'expense_header_id' => $expHeader->id,
                    'expense_head_id' => $randHead->id,
                    'fund_class_id' => $randFund->id,
                    'amount' => $amount,
                    'remarks' => "Expense item description {$e}"
                ]);

                $journalExpense = FinanceJournalBatch::create([
                    'diocese_id' => $diocese->id,
                    'church_id' => $church->id,
                    'batch_date' => $date,
                    'reference' => 'EXP-BATCH-' . $expHeader->id,
                    'source' => 'expense',
                    'source_id' => $expHeader->id,
                    'status' => 'posted',
                    'created_by' => $adminUserId
                ]);

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $journalExpense->id,
                    'chart_account_id' => $coaExpense->id,
                    'fund_class_id' => $randFund->id,
                    'entry_date' => $date,
                    'debit' => $amount,
                    'credit' => 0.00,
                    'description' => 'Expense Debit'
                ]);

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $journalExpense->id,
                    'chart_account_id' => $coaAsset->id,
                    'fund_class_id' => $randFund->id,
                    'entry_date' => $date,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => 'Payment from Sparkasse Bank'
                ]);
            }

            // 6. Seed 4 Cash Batches per church (1 open, 3 closed)
            FinanceCashBatch::create([
                'church_id' => $church->id,
                'money_account_id' => $churchCashAcc->id,
                'opened_at' => now()->subDays(1),
                'opened_by' => $adminUserId,
                'status' => 'open',
                'declared_amount' => 0.00,
                'system_amount' => 0.00,
                'difference' => 0.00
            ]);

            for ($cb = 1; $cb <= 3; $cb++) {
                FinanceCashBatch::create([
                    'church_id' => $church->id,
                    'money_account_id' => $churchCashAcc->id,
                    'opened_at' => now()->subDays(10 + $cb),
                    'closed_at' => now()->subDays(10 + $cb)->addHours(3),
                    'opened_by' => $adminUserId,
                    'closed_by' => $adminUserId,
                    'status' => 'closed',
                    'declared_amount' => 300.00,
                    'system_amount' => 300.00,
                    'difference' => 0.00,
                    'counting_details' => ['bills' => ['50' => 6]]
                ]);
            }

            // 7. Seed 4 Bank Statement Lines (2 matched, 2 unmatched)
            $bankImport = FinanceBankStatementImport::create([
                'money_account_id' => $churchBankAcc->id,
                'import_date' => now()->toDateString(),
                'file_name' => "bank_statement_{$church->id}.csv",
                'imported_by' => $adminUserId
            ]);

            // Matched line 1
            $bsLine1 = FinanceBankStatementLine::create([
                'bank_statement_import_id' => $bankImport->id,
                'booking_date' => now()->subDays(40)->toDateString(),
                'value_date' => now()->subDays(40)->toDateString(),
                'partner_name' => $donorName,
                'description' => "TR-{$church->id}-INC-1 Weekly offering count 1",
                'amount' => 100.00,
                'is_matched' => true
            ]);

            // Matched line 2
            $bsLine2 = FinanceBankStatementLine::create([
                'bank_statement_import_id' => $bankImport->id,
                'booking_date' => now()->subDays(39)->toDateString(),
                'value_date' => now()->subDays(39)->toDateString(),
                'partner_name' => "Vendor {$church->short_name} 1",
                'description' => "REF-EXP-{$church->id}-1 Operational expense 1",
                'amount' => -200.00,
                'is_matched' => true
            ]);

            // Unmatched lines
            FinanceBankStatementLine::create([
                'bank_statement_import_id' => $bankImport->id,
                'booking_date' => now()->subDays(5)->toDateString(),
                'value_date' => now()->subDays(5)->toDateString(),
                'partner_name' => 'General Donor',
                'description' => 'Unidentified donation Sparkasse',
                'amount' => 150.00,
                'is_matched' => false
            ]);

            FinanceBankStatementLine::create([
                'bank_statement_import_id' => $bankImport->id,
                'booking_date' => now()->subDays(4)->toDateString(),
                'value_date' => now()->subDays(4)->toDateString(),
                'partner_name' => 'Reimbursement Claim',
                'description' => 'Unmatched travel cost payout',
                'amount' => -75.00,
                'is_matched' => false
            ]);

            // Match records
            $churchIncomeSample = FinanceIncomeHeader::where('church_id', $church->id)->first();
            if ($churchIncomeSample) {
                FinanceBankMatch::create([
                    'bank_statement_line_id' => $bsLine1->id,
                    'matchable_type' => FinanceIncomeHeader::class,
                    'matchable_id' => $churchIncomeSample->id,
                    'matched_by' => $adminUserId
                ]);
            }

            $churchExpenseSample = FinanceExpenseHeader::where('church_id', $church->id)->first();
            if ($churchExpenseSample) {
                FinanceBankMatch::create([
                    'bank_statement_line_id' => $bsLine2->id,
                    'matchable_type' => FinanceExpenseHeader::class,
                    'matchable_id' => $churchExpenseSample->id,
                    'matched_by' => $adminUserId
                ]);
            }

            // 8. Seed 1 Internal Transfer per church
            $transfer = FinanceTransfer::create([
                'church_id' => $church->id,
                'transfer_date' => now()->subDays(8)->toDateString(),
                'from_account_id' => $churchCashAcc->id,
                'to_account_id' => $churchBankAcc->id,
                'amount' => 250.00,
                'reference' => "TR-CASH-DEP-{$church->id}",
                'status' => 'posted',
                'created_by' => $adminUserId
            ]);

            $journalTransfer = FinanceJournalBatch::create([
                'diocese_id' => $diocese->id,
                'church_id' => $church->id,
                'batch_date' => now()->subDays(8)->toDateString(),
                'reference' => 'TR-BATCH-' . $transfer->id,
                'source' => 'transfer',
                'source_id' => $transfer->id,
                'status' => 'posted',
                'created_by' => $adminUserId
            ]);

            FinanceLedgerEntry::create([
                'journal_batch_id' => $journalTransfer->id,
                'chart_account_id' => $coaAsset->id,
                'fund_class_id' => $fundGen->id,
                'entry_date' => now()->subDays(8)->toDateString(),
                'debit' => 250.00,
                'credit' => 0.00,
                'description' => 'Transfer deposit to Bank account'
            ]);

            FinanceLedgerEntry::create([
                'journal_batch_id' => $journalTransfer->id,
                'chart_account_id' => $coaAsset->id,
                'fund_class_id' => $fundGen->id,
                'entry_date' => now()->subDays(8)->toDateString(),
                'debit' => 0.00,
                'credit' => 250.00,
                'description' => 'Transfer withdrawal from cash safe'
            ]);
        }

        // Seed 20+ Priest Payments across all Priest Profiles
        $this->info('Seeding priest payments claims...');
        $priestsList = \App\Models\PriestProfile::all();
        foreach ($priestsList as $priestProfile) {
            $churchId = $priestProfile->assignments()->first()?->church_id ?? $vienna->id;
            
            // 1. Stipend (Confirmed)
            FinancePriestPayment::create([
                'church_id' => $churchId,
                'priest_profile_id' => $priestProfile->id,
                'payment_date' => now()->subMonths(1)->toDateString(),
                'type' => 'stipend',
                'amount' => 1500.00,
                'description' => "Monthly Stipend for Fr. " . $priestProfile->display_name . " - May 2026",
                'status' => 'confirmed'
            ]);

            // 2. Travel Claim (Confirmed)
            FinancePriestPayment::create([
                'church_id' => $churchId,
                'priest_profile_id' => $priestProfile->id,
                'payment_date' => now()->subDays(15)->toDateString(),
                'type' => 'travel',
                'amount' => 84.00,
                'travel_distance_km' => 200.00,
                'travel_rate_per_km' => 0.4200,
                'description' => "Travel claim 200km @ 0.42 EUR/km",
                'status' => 'confirmed'
            ]);

            // 3. Allowance (Confirmed)
            FinancePriestPayment::create([
                'church_id' => $churchId,
                'priest_profile_id' => $priestProfile->id,
                'payment_date' => now()->subDays(10)->toDateString(),
                'type' => 'allowance',
                'amount' => 200.00,
                'description' => "Allowance for communications",
                'status' => 'confirmed'
            ]);

            // 4. Stipend (Draft)
            FinancePriestPayment::create([
                'church_id' => $churchId,
                'priest_profile_id' => $priestProfile->id,
                'payment_date' => now()->toDateString(),
                'type' => 'stipend',
                'amount' => 1500.00,
                'description' => "Monthly Stipend Fr. " . $priestProfile->display_name . " - June 2026",
                'status' => 'draft'
            ]);
        }

        // 12. CMS and Public Website
        $this->info('Seeding CMS content...');
        WebsitePage::create([
            'diocese_id' => $diocese->id,
            'title' => 'Diocese History',
            'slug' => 'diocese-history',
            'content' => 'History of Malankara Syriac Orthodox Church in Europe.',
            'status' => 'published',
            'created_by' => $adminUserId
        ]);

        // News post pending approval
        $newsPending = NewsPost::create([
            'diocese_id' => $diocese->id,
            'title' => 'Feast Celebrations announcement',
            'slug' => 'feast-celebrations-announcement',
            'excerpt' => 'Feast announcement summary',
            'content' => 'Full content detailing feast schedules.',
            'status' => 'submitted',
            'created_by' => $users['parishadmin.vienna@demo.msoc.test']->id
        ]);

        ContentApproval::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'approvable_type' => NewsPost::class,
            'approvable_id' => $newsPending->id,
            'approval_type' => 'news_publish',
            'requested_by' => $users['parishadmin.vienna@demo.msoc.test']->id,
            'requested_at' => now(),
            'status' => 'pending'
        ]);

        // Galleries and Media (including photo privacy cases)
        $gallery = MediaGallery::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'title' => 'Sunday School Activities 2026',
            'slug' => 'sunday-school-activities-2026',
            'status' => 'published',
            'created_by' => $adminUserId
        ]);

        // Image with child having photo consent
        $mediaItemConsent = MediaItem::create([
            'media_gallery_id' => $gallery->id,
            'media_path' => 'public/gallery/ss_consent.jpg',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);
        $mediaItemConsent->taggedMembers()->attach($m2_child1->id); // Job Parent (consent = true)

        // Image with child WITHOUT photo consent
        $mediaItemNoConsent = MediaItem::create([
            'media_gallery_id' => $gallery->id,
            'media_path' => 'public/gallery/ss_no_consent.jpg',
            'status' => 'active',
            'created_by' => $adminUserId
        ]);
        $mediaItemNoConsent->taggedMembers()->attach($m2_child2->id); // Anna Parent (consent = false)

        // 13. Communications and Notifications
        $this->info('Seeding communications...');
        NotificationTemplate::create([
            'diocese_id' => $diocese->id,
            'template_key' => 'monthly_circular',
            'name' => 'Monthly Circular Template',
            'channel' => 'email',
            'subject' => 'MSOC Europe Monthly Circular',
            'body' => 'Dearly beloved, please find attached the monthly circular from the Diocese.',
            'created_by' => $adminUserId
        ]);

        $announcement = Announcement::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'title' => 'Holy Week Liturgy Timings',
            'body' => 'Liturgy timings for Vienna parish are now updated on the portal.',
            'announcement_type' => 'parish',
            'status' => 'sent',
            'created_by' => $users['priest.vienna@demo.msoc.test']->id
        ]);

        // Send inbox notification
        Notification::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $users['member.familyhead@demo.msoc.test']->id,
            'title' => 'Liturgy Update',
            'body' => 'Vienna Parish timings are updated. Please review them.',
            'notification_type' => 'announcement',
            'channel' => 'in_app',
            'status' => 'sent',
            'read_at' => null
        ]);

        // Failed notification delivery log
        NotificationDelivery::create([
            'announcement_id' => $announcement->id,
            'recipient_type' => 'user',
            'recipient_id' => $users['member.familyhead@demo.msoc.test']->id,
            'channel' => 'email',
            'delivery_status' => 'failed',
            'error_message' => 'Mail server timeout: Connection refused.'
        ]);

        // 14. Member Portal Actions
        $this->info('Seeding member portal interactions...');
        $portalAccessHead = MemberPortalAccess::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'user_id' => $users['member.familyhead@demo.msoc.test']->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        // Profile correction request
        ProfileCorrectionRequest::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'member_id' => $m1_head->id,
            'requested_by' => $users['member.familyhead@demo.msoc.test']->id,
            'request_type' => 'member_profile',
            'current_data' => ['baptism_name' => 'John'],
            'requested_data' => ['baptism_name' => 'Yohannan'],
            'reason' => 'Spelling correction',
            'status' => 'submitted'
        ]);

        // Document upload
        MemberPortalDocument::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'family_id' => $f1->id,
            'member_id' => $m1_head->id,
            'uploaded_by' => $users['member.familyhead@demo.msoc.test']->id,
            'document_type' => 'baptism_certificate',
            'file_path' => 'private/portal_documents/baptism_registry_f1.pdf',
            'original_file_name' => 'baptism_registry_f1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => 'uploaded'
        ]);
        Storage::put('private/portal_documents/baptism_registry_f1.pdf', 'Mock scan of baptism register');

        // Logs
        MemberPortalActivityLog::create([
            'diocese_id' => $diocese->id,
            'user_id' => $users['member.familyhead@demo.msoc.test']->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15)'
        ]);

        // 15. Reports and Saved Runs
        $this->info('Seeding reports...');
        $run = ReportRun::create([
            'diocese_id' => $diocese->id,
            'report_key' => 'diocese_overview',
            'status' => 'completed',
            'row_count' => 150,
            'generated_by' => $users['superadmin@demo.msoc.test']->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        Storage::put('private/report_exports/diocese_overview_export.csv', 'church_name,members_count\nVienna,35\nBerlin,25');
        ReportExport::create([
            'diocese_id' => $diocese->id,
            'report_run_id' => $run->id,
            'file_name' => 'diocese_overview_export.csv',
            'file_path' => 'private/report_exports/diocese_overview_export.csv',
            'export_type' => 'csv',
            'status' => 'generated',
            'generated_by' => $users['superadmin@demo.msoc.test']->id,
            'expires_at' => now()->addDays(7)
        ]);

        // Seed expired report run to test cleanup retention scheduler
        $oldRun = ReportRun::create([
            'diocese_id' => $diocese->id,
            'report_key' => 'members_list',
            'status' => 'completed',
            'row_count' => 10,
            'generated_by' => $users['superadmin@demo.msoc.test']->id,
            'started_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
        ]);

        Storage::put('private/report_exports/old_members_list.csv', 'name,email\nJohn,john@example.com');
        ReportExport::create([
            'diocese_id' => $diocese->id,
            'report_run_id' => $oldRun->id,
            'file_name' => 'old_members_list.csv',
            'file_path' => 'private/report_exports/old_members_list.csv',
            'export_type' => 'csv',
            'status' => 'generated',
            'generated_by' => $users['superadmin@demo.msoc.test']->id,
            'expires_at' => now()->subDays(2) // Expired export
        ]);

        // 10 Member Responsibility Assignments
        $this->info('Seeding member responsibilities (office bearers)...');
        
        // 1. Parish Secretary of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_spouse->id,
            'user_id' => $m1_spouse->user_id,
            'responsibility_type' => 'parish_office',
            'designation' => 'secretary',
            'start_date' => '2022-01-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 2. Parish Treasurer of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_head->id,
            'user_id' => $users['member.familyhead@demo.msoc.test']->id,
            'responsibility_type' => 'finance',
            'designation' => 'treasurer',
            'start_date' => '2022-01-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 3. Sunday School Headmaster of Vienna
        $teacherUser = $users['teacher.vienna@demo.msoc.test'];
        $teacherMember = Member::where('email', 'teacher.vienna@demo.msoc.test')->first();
        if (!$teacherMember) {
            $teacherMember = Member::create([
                'diocese_id' => $diocese->id,
                'church_id' => $vienna->id,
                'family_id' => $m1_head->family_id,
                'user_id' => $teacherUser->id,
                'first_name' => 'Vienna',
                'last_name' => 'Teacher',
                'full_name' => 'Vienna Teacher',
                'gender' => 'female',
                'email' => 'teacher.vienna@demo.msoc.test',
                'relationship_to_head' => 'other',
                'membership_status' => 'active',
                'created_by' => $adminUserId,
            ]);
        }
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $teacherMember->id,
            'user_id' => $teacherUser->id,
            'responsibility_type' => 'sunday_school',
            'designation' => 'headmaster',
            'start_date' => '2021-09-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 4. Youth Association President of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_child1->id,
            'responsibility_type' => 'organization',
            'designation' => 'president',
            'organization_type' => 'youth',
            'start_date' => '2022-01-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 5. Marthamariyam Samajam Treasurer of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_spouse->id,
            'responsibility_type' => 'organization',
            'designation' => 'treasurer',
            'organization_type' => 'marthamariyam',
            'start_date' => '2022-01-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 6. Perunnal Convenor (Vienna event convenor)
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_head->id,
            'responsibility_type' => 'event',
            'designation' => 'convenor',
            'start_date' => '2022-03-01',
            'end_date' => '2022-06-30',
            'status' => 'ended',
            'assigned_by' => $adminUserId,
        ]);

        // 7. Sunday School Teacher of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m2_child2->id,
            'responsibility_type' => 'sunday_school',
            'designation' => 'teacher',
            'start_date' => '2022-09-01',
            'status' => 'active',
            'assigned_by' => $adminUserId,
        ]);

        // 8. Youth Association Secretary of Vienna
        MemberResponsibilityAssignment::create([
            'diocese_id' => $diocese->id,
            'church_id' => $vienna->id,
            'member_id' => $m1_child->id,
            'responsibility_type' => 'organization',
            'designation' => 'secretary',
            'organization_type' => 'youth',
            'start_date' => '2022-01-01',
            'status' => 'active',
            'is_primary' => true,
            'assigned_by' => $adminUserId,
        ]);

        // 9. Committee Member of Berlin
        $berlinMember = Member::where('church_id', $berlin->id)->first();
        if ($berlinMember) {
            MemberResponsibilityAssignment::create([
                'diocese_id' => $diocese->id,
                'church_id' => $berlin->id,
                'member_id' => $berlinMember->id,
                'responsibility_type' => 'committee',
                'designation' => 'committee_member',
                'start_date' => '2022-01-01',
                'status' => 'active',
                'assigned_by' => $adminUserId,
            ]);
        }

        // 10. Organisation In-charge of Berlin
        if ($berlinMember) {
            MemberResponsibilityAssignment::create([
                'diocese_id' => $diocese->id,
                'church_id' => $berlin->id,
                'member_id' => $berlinMember->id,
                'responsibility_type' => 'organization',
                'designation' => 'incharge',
                'organization_type' => 'samajam',
                'start_date' => '2022-01-01',
                'status' => 'active',
                'assigned_by' => $adminUserId,
            ]);
        }

        // Seed Website Import Source
        WebsiteImportSource::create([
            'diocese_id' => $diocese->id,
            'source_type' => 'priests',
            'source_url' => 'https://mock.msoc-europe.org/priests',
            'status' => 'active',
        ]);

        // ========================================================
        // PUBLIC WEBSITE SEEDING
        // ========================================================
        $this->info('Seeding Public Website CMS and timings...');

        // 1. Brand & Homepage settings
        $settings = [
            'site_name' => 'MSOC Europe Diocese',
            'site_primary_color' => '#7A1E2C',
            'site_secondary_color' => '#C9A227',
            'homepage_hero_title' => 'Malankara Syrian Orthodox Church in Europe',
            'homepage_hero_subtitle' => 'Faith • Tradition • Community • Legacy',
            'homepage_welcome_title' => 'Welcome to MSOC Europe',
            'homepage_welcome_content' => 'We serve our faithful community across Europe with liturgical purity, theological depth, and pastoral care under the Holy Apostolic See of Antioch.',
            'announcement_text' => 'Annual Diocesan Family Conference 2026 starts this July!',
            'announcement_link_text' => 'Register Now',
            'announcement_link_url' => '/events',
            'is_active' => true,
            'public_contact_email' => 'office@msoc-europe.org',
            'public_contact_phone' => '+43 1 555 1234',
            'public_contact_address' => 'MSOC Europe Diocesan Center, Vienna, Austria',
            'homepage_metropolitan_profile_id' => PriestProfile::first()?->id,
            'homepage_featured_news_limit' => 3,
            'homepage_featured_events_limit' => 3,
            'homepage_gallery_limit' => 3,
            'homepage_download_limit' => 3,
        ];

        foreach ($settings as $k => $v) {
            \App\Models\WebsiteSetting::updateOrCreate(
                ['key' => $k],
                ['diocese_id' => $diocese->id, 'value' => $v, 'group' => 'public']
            );
        }

        // 2. Priest Public Slugs and bios
        $priests = PriestProfile::all();
        foreach ($priests as $idx => $p) {
            $slugName = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $p->ordination_name));
            $p->update([
                'public_slug' => "fr-{$slugName}-{$p->id}",
                'public_bio' => "Serving as priest/vicar in the MSOC Europe diocese. Ordained at {$p->ordination_place}.",
                'show_public_profile' => true,
                'show_public_phone' => true,
                'show_public_email' => true,
                'public_sort_order' => $idx + 1,
            ]);
        }

        // 3. Churches / Parishes Public Slugs, bios and service timings
        $allChurches = Church::all();
        foreach ($allChurches as $idx => $c) {
            $slugName = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $c->name));
            $c->update([
                'public_slug' => "parish-{$slugName}-{$c->id}",
                'public_description' => "Dignified Malankara Syriac Orthodox parish located in {$c->city}, {$c->country}.",
                'show_public_page' => true,
                'show_service_times' => true,
                'show_map' => true,
                'public_sort_order' => $idx + 1,
                'seo_title' => "{$c->name} - MSOC Europe",
                'seo_description' => "Welcome to {$c->name} in {$c->city}, {$c->country}. Join us for weekly Holy Qurbana.",
            ]);

            // Seed Timings
            \App\Models\ChurchServiceTiming::create([
                'church_id' => $c->id,
                'service_name' => 'Sunday Holy Qurbana',
                'day_of_week' => 'Sunday',
                'start_time' => '09:00:00',
                'end_time' => '11:30:00',
                'language' => $idx % 2 === 0 ? 'Malayalam' : 'English',
                'frequency' => 'weekly',
                'notes' => 'Confession starts at 08:30 AM.',
                'is_public' => true,
                'status' => 'active',
            ]);

            \App\Models\ChurchServiceTiming::create([
                'church_id' => $c->id,
                'service_name' => 'Saturday Evening Prayer',
                'day_of_week' => 'Saturday',
                'start_time' => '18:00:00',
                'end_time' => '19:00:00',
                'language' => 'Malayalam',
                'frequency' => 'weekly',
                'is_public' => true,
                'status' => 'active',
            ]);
        }

        // 4. Static CMS Pages
        $pagesList = [
            [
                'slug' => 'about',
                'title' => 'About the Diocese',
                'content' => 'The Malankara Syrian Orthodox Church in Europe is an archdiocese under the Holy Apostolic See of Antioch. We preserve the ancient liturgical heritage and faith of the Syriac Orthodox Church.',
            ],
            [
                'slug' => 'metropolitan',
                'title' => 'Our Metropolitan',
                'content' => 'Our Patriarchal Vicar guides the spiritual life of the parishes and communities in the United Kingdom and continental Europe.',
            ],
            [
                'slug' => 'administration',
                'title' => 'Diocesan Administration',
                'content' => 'The Diocesan Council coordinates administrative, financial, and regulatory frameworks across all parishes in Europe.',
            ],
            [
                'slug' => 'sunday-school',
                'title' => 'Sunday School (MJSSA)',
                'content' => 'The Malankara Jacobite Syrian Sunday School Association (MJSSA) Europe chapter implements a unified catechism syllabus and conducts annual assessments.',
            ],
            [
                'slug' => 'organization-sunday-school',
                'title' => 'Sunday School / MJSSA',
                'content' => 'Catechism and spiritual formation for children across all European parishes.',
            ],
            [
                'slug' => 'organization-youth-association',
                'title' => 'Youth Association',
                'content' => 'Building active faith, service projects, and networking opportunities for youth.',
            ],
            [
                'slug' => 'organization-marthamariyam-samajam',
                'title' => 'Marthamariyam Samajam',
                'content' => 'Prayers, retreat days, and charity outreach led by ladies of the parishes.',
            ],
        ];

        foreach ($pagesList as $p) {
            \App\Models\WebsitePage::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'diocese_id' => $diocese->id,
                    'title' => $p['title'],
                    'content' => $p['content'],
                    'status' => 'published',
                    'visibility' => 'public',
                    'created_by' => $adminUserId,
                ]
            );
        }

        // 5. Public News & Events seeding (Ensuring 10 news & 8 events)
        for ($i = 1; $i <= 10; $i++) {
            NewsPost::updateOrCreate(
                ['slug' => "public-news-announcement-{$i}"],
                [
                    'diocese_id' => $diocese->id,
                    'title' => "Public Diocesan News Announcement {$i}",
                    'excerpt' => "Summary of the news post number {$i} issued by the Diocesan media desk.",
                    'content' => "This is the full detailed news coverage for announcement {$i}. It outlines standard Diocesan activities and updates for all European parishes.",
                    'category' => 'diocese',
                    'status' => 'published',
                    'visibility' => 'public',
                    'created_by' => $adminUserId,
                    'published_at' => now()->subDays($i),
                ]
            );
        }

        for ($i = 1; $i <= 8; $i++) {
            Event::updateOrCreate(
                ['slug' => "public-event-gathering-{$i}"],
                [
                    'diocese_id' => $diocese->id,
                    'title' => "Public Diocesan Gathering Event {$i}",
                    'description' => "Join us for public event number {$i} organized by the central Diocesan council.",
                    'event_type' => 'conference',
                    'start_datetime' => Carbon::today()->addDays($i * 5)->setTime(10, 0, 0)->toDateTimeString(),
                    'end_datetime' => Carbon::today()->addDays($i * 5)->setTime(13, 0, 0)->toDateTimeString(),
                    'location_name' => 'Diocesan Center / Online Zoom',
                    'visibility' => 'public',
                    'status' => 'published',
                    'created_by' => $adminUserId,
                ]
            );
        }

        // 6. Public Downloads & Circulars
        for ($i = 1; $i <= 6; $i++) {
            \App\Models\WebsiteDownload::updateOrCreate(
                ['title' => "Public Diocese Form {$i}"],
                [
                    'diocese_id' => $diocese->id,
                    'slug' => "public-diocese-form-{$i}",
                    'description' => "Official diocese document {$i} available for download.",
                    'download_type' => 'form',
                    'file_path' => "public/downloads/form_{$i}.pdf",
                    'file_name' => "form_{$i}.pdf",
                    'file_type' => 'pdf',
                    'file_size' => 122880,
                    'visibility' => 'public',
                    'status' => 'published',
                    'created_by' => $adminUserId,
                ]
            );
        }

        for ($i = 1; $i <= 5; $i++) {
            KalpanaCircular::updateOrCreate(
                ['slug' => "public-circular-{$i}"],
                [
                    'diocese_id' => $diocese->id,
                    'title' => "Holy Apostolic Kalpana Circular {$i}",
                    'content' => "Apostolic instruction and circular number {$i} for all congregations.",
                    'circular_type' => 'circular',
                    'circular_date' => now()->subDays($i * 4)->toDateString(),
                    'reference_number' => "K-CIRC-2026-00{$i}",
                    'file_path' => "public/circulars/circular_{$i}.pdf",
                    'visibility' => 'public',
                    'status' => 'published',
                    'created_by' => $adminUserId,
                ]
            );
        }

        // 7. Galleries and items
        for ($i = 1; $i <= 6; $i++) {
            $alb = MediaGallery::updateOrCreate(
                ['slug' => "public-album-gallery-{$i}"],
                [
                    'diocese_id' => $diocese->id,
                    'title' => "Public Album Gallery {$i}",
                    'description' => "Photos and moments from diocese activity {$i}.",
                    'visibility' => 'public',
                    'status' => 'published',
                    'created_by' => $adminUserId,
                    'published_at' => now(),
                ]
            );

            // Add 5 media items per gallery
            for ($k = 1; $k <= 5; $k++) {
                MediaItem::create([
                    'media_gallery_id' => $alb->id,
                    'title' => "Photo {$k} from album {$i}",
                    'media_path' => "public/gallery/album_{$i}_photo_{$k}.jpg",
                    'status' => 'approved',
                    'created_by' => $adminUserId,
                ]);
            }
        }

        $this->info('Demo seeding completed successfully.');
        $this->info('Do not use these credentials in production.');
        $this->newLine();

        // Output summary table of login details
        $this->table(
            ['Role', 'Email', 'Password', 'Scope'],
            [
                ['Super Admin', 'superadmin@demo.msoc.test', 'Password@123', 'All Diocese'],
                ['Diocese Admin', 'dioceseadmin@demo.msoc.test', 'Password@123', 'All Diocese'],
                ['Diocese Treasurer', 'diocesetreasurer@demo.msoc.test', 'Password@123', 'All Diocese Finance'],
                ['Diocese Auditor', 'dioceseauditor@demo.msoc.test', 'Password@123', 'Read-only Audit'],
                ['Diocese PRO', 'diocesepro@demo.msoc.test', 'Password@123', 'CMS/Public Website'],
                ['Vienna Priest', 'priest.vienna@demo.msoc.test', 'Password@123', 'Vienna Only'],
                ['Berlin Priest', 'priest.berlin@demo.msoc.test', 'Password@123', 'Berlin Only'],
                ['Vienna Parish Admin', 'parishadmin.vienna@demo.msoc.test', 'Password@123', 'Vienna Only'],
                ['Berlin Parish Admin', 'parishadmin.berlin@demo.msoc.test', 'Password@123', 'Berlin Only'],
                ['Vienna Treasurer', 'parishtreasurer.vienna@demo.msoc.test', 'Password@123', 'Vienna Finance Only'],
                ['Vienna Teacher', 'teacher.vienna@demo.msoc.test', 'Password@123', 'Vienna SS Class Only'],
                ['Vienna Youth Coordinator', 'youthcoordinator.vienna@demo.msoc.test', 'Password@123', 'Vienna Youth Unit Only'],
                ['Vienna Samajam Coordinator', 'samajamcoordinator.vienna@demo.msoc.test', 'Password@123', 'Vienna Samajam Unit Only'],
                ['Family Head', 'member.familyhead@demo.msoc.test', 'Password@123', 'Own Family Only'],
                ['Parent', 'parent.vienna@demo.msoc.test', 'Password@123', 'Vienna - Children Only'],
                ['Single Member', 'member.single@demo.msoc.test', 'Password@123', 'Vienna - Own Profile Only'],
            ]
        );

        return self::SUCCESS;
    }
}
