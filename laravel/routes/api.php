<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChurchController;
use App\Http\Controllers\Api\V1\PriestController;
use App\Http\Controllers\Api\V1\PriestAssignmentController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\FamilyController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberChangeRequestController;
use App\Http\Controllers\Api\V1\FamilyTransferController;
use App\Http\Controllers\Api\V1\ImportExportController;
use App\Http\Controllers\Api\V1\SacramentController;
use App\Http\Controllers\Api\V1\CertificateTemplateController;
use App\Http\Controllers\Api\V1\CertificateRequestController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CourseBatchController;
use App\Http\Controllers\Api\V1\CourseRegistrationController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventRegistrationController;
use App\Http\Controllers\Api\V1\SundaySchoolController;
use App\Http\Controllers\Api\V1\MinistryController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\PriestPortalFinanceController;
use App\Http\Controllers\Api\V1\ClergyAdminController;
use App\Http\Controllers\Api\V1\PriestPortalController;

// Health Check Route
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'status' => 'OK',
        'database' => $dbStatus,
        'timestamp' => now()->toIso8601String()
    ]);
});

Route::prefix('v1')->group(function () {
    // Version Route
    Route::get('/version', function () {
        return response()->json([
            'version' => '1.0.0',
            'phase' => 1
        ]);
    });

    // Public/Guest Routes
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/login/2fa', [AuthController::class, 'login2fa'])->middleware('throttle:2fa_verify');
    Route::post('/auth/impersonate', [AuthController::class, 'impersonate']);
    Route::get('/certificates/verify/{verification_code}', [CertificateController::class, 'verify']);
    Route::get('/events/public', [EventController::class, 'publicEvents']);

    // Phase 8 Public Guest Routes (V2)
    Route::get('/public/home', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getHome']);
    Route::get('/public/pages/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getPage']);
    Route::get('/public/parishes', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getParishes']);
    Route::get('/public/parishes/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getParishItem']);
    Route::get('/public/priests', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getPriests']);
    Route::get('/public/priests/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getPriestItem']);
    Route::get('/public/news', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getNews']);
    Route::get('/public/news/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getNewsItem']);
    Route::get('/public/events', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getEvents']);
    Route::get('/public/events/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getEventItem']);
    Route::get('/public/organizations', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getOrganizations']);
    Route::get('/public/organizations/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getOrganizationItem']);
    Route::get('/public/galleries', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getGalleries']);
    Route::get('/public/galleries/{slug}', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getGalleryItem']);
    Route::get('/public/downloads', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getDownloads']);
    Route::get('/public/downloads/{id}/download', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getDownloadFile']);
    Route::get('/public/kalpana-circulars', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getCirculars']);
    Route::get('/public/contact', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getContact']);
    Route::post('/public/contact', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'postContact'])->middleware('throttle:3,1');
    Route::get('/public/search', [\App\Http\Controllers\Api\V1\PublicWebsiteController::class, 'getSearch']);

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth management
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/active-church', [AuthController::class, 'activeChurch']);

        // Churches CRUD
        Route::apiResource('churches', ChurchController::class);

        // Priests CRUD
        Route::apiResource('priests', PriestController::class);
        
        // Priest Assignments
        Route::post('/priests/{id}/assignments', [PriestAssignmentController::class, 'store']);
        Route::put('/priest-assignments/{id}', [PriestAssignmentController::class, 'update']);
        Route::delete('/priest-assignments/{id}', [PriestAssignmentController::class, 'destroy']);

        // User Management & Church Access Mappings
        Route::apiResource('users', UserController::class);
        Route::post('/users/{id}/access', [UserController::class, 'storeAccess']);
        Route::delete('/users/{id}/access/{accessId}', [UserController::class, 'destroyAccess']);

        // Roles & Permissions Lists
        Route::get('/roles', [RoleController::class, 'roles']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::get('/countries', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Country::orderBy('name')->get()
            ]);
        });

        // Dashboard & Audits
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Phase 2: Families & Members Directory
        Route::apiResource('families', FamilyController::class);
        Route::post('/families/{id}/approve', [FamilyController::class, 'approve']);
        
        Route::apiResource('members', MemberController::class);
        Route::post('/members/{id}/approve', [MemberController::class, 'approve']);
        Route::post('/members/{id}/mark-deceased', [MemberController::class, 'markDeceased']);
        Route::post('/members/{id}/upload-photo', [MemberController::class, 'uploadPhoto']);

        // Phase 2: Member Change Requests
        Route::apiResource('member-change-requests', MemberChangeRequestController::class);
        Route::post('/member-change-requests/{id}/approve', [MemberChangeRequestController::class, 'approve']);
        Route::post('/member-change-requests/{id}/reject', [MemberChangeRequestController::class, 'reject']);

        // Phase 2: Family Transfer Requests
        Route::apiResource('family-transfer-requests', FamilyTransferController::class);
        Route::post('/family-transfer-requests/{id}/source-approve', [FamilyTransferController::class, 'sourceApprove']);
        Route::post('/family-transfer-requests/{id}/diocese-approve', [FamilyTransferController::class, 'dioceseApprove']);
        Route::post('/family-transfer-requests/{id}/target-accept', [FamilyTransferController::class, 'targetAccept']);
        Route::post('/family-transfer-requests/{id}/complete', [FamilyTransferController::class, 'complete']);
        Route::post('/family-transfer-requests/{id}/reject', [FamilyTransferController::class, 'reject']);

        // Phase 2: CSV Import/Export
        Route::post('/imports/families', [ImportExportController::class, 'importFamilies']);
        Route::post('/imports/members', [ImportExportController::class, 'importMembers']);
        Route::get('/exports/families', [ImportExportController::class, 'exportFamilies']);
        Route::get('/exports/members', [ImportExportController::class, 'exportMembers']);

        // Phase 3: Sacraments
        Route::apiResource('sacraments', SacramentController::class);
        Route::post('/sacraments/{id}/submit', [SacramentController::class, 'submit']);
        Route::post('/sacraments/{id}/verify', [SacramentController::class, 'verify']);
        Route::post('/sacraments/{id}/approve', [SacramentController::class, 'approve']);
        Route::post('/sacraments/{id}/reject', [SacramentController::class, 'reject']);
        Route::post('/sacraments/{id}/archive', [SacramentController::class, 'archive']);

        // Phase 3: Certificate Templates
        Route::apiResource('certificate-templates', CertificateTemplateController::class);
        Route::post('/certificate-templates/{id}/activate', [CertificateTemplateController::class, 'activate']);
        Route::post('/certificate-templates/{id}/deactivate', [CertificateTemplateController::class, 'deactivate']);

        // Phase 3: Certificate Requests
        Route::apiResource('certificate-requests', CertificateRequestController::class);
        Route::post('/certificate-requests/{id}/parish-review', [CertificateRequestController::class, 'parishReview']);
        Route::post('/certificate-requests/{id}/priest-approve', [CertificateRequestController::class, 'priestApprove']);
        Route::post('/certificate-requests/{id}/diocese-approve', [CertificateRequestController::class, 'dioceseApprove']);
        Route::post('/certificate-requests/{id}/reject', [CertificateRequestController::class, 'reject']);
        Route::post('/certificate-requests/{id}/issue', [CertificateRequestController::class, 'issue']);

        // Phase 3: Certificates
        Route::get('/certificates/{id}/download', [CertificateController::class, 'download'])->middleware(['throttle:download_routes', 'require.2fa']);
        Route::post('/certificates/{id}/cancel', [CertificateController::class, 'cancel']);
        Route::apiResource('certificates', CertificateController::class)->except(['store', 'update', 'destroy']);

        // Phase 4: Courses
        Route::post('/courses/{id}/activate', [CourseController::class, 'activate']);
        Route::post('/courses/{id}/deactivate', [CourseController::class, 'deactivate']);
        Route::apiResource('courses', CourseController::class);

        // Phase 4: Course Batches
        Route::post('/course-batches/{id}/open', [CourseBatchController::class, 'open']);
        Route::post('/course-batches/{id}/complete', [CourseBatchController::class, 'complete']);
        Route::post('/course-batches/{id}/cancel', [CourseBatchController::class, 'cancel']);
        Route::get('/course-batches/{id}/sessions', [CourseBatchController::class, 'sessions']);
        Route::post('/course-batches/{id}/sessions', [CourseBatchController::class, 'storeSession']);
        Route::get('/course-batches/{id}/attendance', [CourseRegistrationController::class, 'getAttendance']);
        Route::get('/course-batches/{id}/feedback', [CourseRegistrationController::class, 'getFeedback']);
        Route::apiResource('course-batches', CourseBatchController::class);

        // Phase 4: Course Registrations
        Route::post('/course-registrations/{id}/confirm', [CourseRegistrationController::class, 'confirm']);
        Route::post('/course-registrations/{id}/cancel', [CourseRegistrationController::class, 'cancel']);
        Route::post('/course-registrations/{id}/mark-completed', [CourseRegistrationController::class, 'markCompleted']);
        Route::post('/course-registrations/{id}/issue-certificate', [CourseRegistrationController::class, 'issueCertificate']);
        Route::apiResource('course-registrations', CourseRegistrationController::class);

        // Phase 4: Course Attendance & Feedback
        Route::post('/course-attendance/mark', [CourseRegistrationController::class, 'markSessionAttendance']);
        Route::post('/course-attendance/qr-check-in', [CourseRegistrationController::class, 'qrCheckIn']);
        Route::post('/course-feedback', [CourseRegistrationController::class, 'submitFeedback']);

        // Phase 4: Events
        Route::post('/events/{id}/publish', [EventController::class, 'publish']);
        Route::post('/events/{id}/open-registration', [EventController::class, 'openRegistration']);
        Route::post('/events/{id}/close-registration', [EventController::class, 'closeRegistration']);
        Route::post('/events/{id}/complete', [EventController::class, 'complete']);
        Route::post('/events/{id}/cancel', [EventController::class, 'cancel']);
        Route::apiResource('events', EventController::class);

        // Phase 4: Event Registrations & Attendance
        Route::post('/event-registrations/{id}/confirm', [EventRegistrationController::class, 'confirm']);
        Route::post('/event-registrations/{id}/cancel', [EventRegistrationController::class, 'cancel']);
        Route::get('/events/{id}/attendance', [EventRegistrationController::class, 'getAttendance']);
        Route::post('/event-attendance/mark', [EventRegistrationController::class, 'markAttendance']);
        Route::post('/event-attendance/qr-check-in', [EventRegistrationController::class, 'qrCheckIn']);
        Route::apiResource('event-registrations', EventRegistrationController::class);

        // Phase 4: CSV Exports
        Route::get('/exports/course-registrations', [ImportExportController::class, 'exportCourseRegistrations']);
        Route::get('/exports/event-registrations', [ImportExportController::class, 'exportEventRegistrations']);
        Route::get('/exports/course-attendance', [ImportExportController::class, 'exportCourseAttendance']);
        Route::get('/exports/event-attendance', [ImportExportController::class, 'exportEventAttendance']);

        // Phase 5: Sunday School / MJSSA Europe Module
        Route::get('/sunday-school/dashboard', [SundaySchoolController::class, 'dashboard']);

        // Academic Years
        Route::get('/sunday-school/academic-years', [SundaySchoolController::class, 'listAcademicYears']);
        Route::post('/sunday-school/academic-years', [SundaySchoolController::class, 'storeAcademicYear']);
        Route::post('/sunday-school/academic-years/{id}/activate', [SundaySchoolController::class, 'activateAcademicYear']);
        Route::post('/sunday-school/academic-years/{id}/complete', [SundaySchoolController::class, 'completeAcademicYear']);

        // Levels
        Route::get('/sunday-school/levels', [SundaySchoolController::class, 'listLevels']);
        Route::post('/sunday-school/levels', [SundaySchoolController::class, 'storeLevel']);
        Route::post('/sunday-school/levels/{id}/archive', [SundaySchoolController::class, 'archiveLevel']);

        // Classes
        Route::get('/sunday-school/classes', [SundaySchoolController::class, 'listClasses']);
        Route::post('/sunday-school/classes', [SundaySchoolController::class, 'storeClass']);
        Route::get('/sunday-school/classes/{id}', [SundaySchoolController::class, 'showClass']);
        Route::get('/sunday-school/classes/{id}/students', [SundaySchoolController::class, 'classStudents']);
        Route::get('/sunday-school/classes/{id}/teachers', [SundaySchoolController::class, 'classTeachers']);
        Route::post('/sunday-school/classes/{id}/activate', [SundaySchoolController::class, 'activateClass']);
        Route::post('/sunday-school/classes/{id}/complete', [SundaySchoolController::class, 'completeClass']);
        Route::post('/sunday-school/classes/{id}/cancel', [SundaySchoolController::class, 'cancelClass']);

        // Teachers
        Route::get('/sunday-school/teachers', [SundaySchoolController::class, 'listTeachers']);
        Route::post('/sunday-school/teachers', [SundaySchoolController::class, 'storeTeacher']);
        Route::get('/sunday-school/teachers/{id}/classes', [SundaySchoolController::class, 'teacherClasses']);
        Route::post('/sunday-school/teachers/{id}/assign-class', [SundaySchoolController::class, 'assignClassTeacher']);
        Route::post('/sunday-school/teachers/{id}/end-assignment', [SundaySchoolController::class, 'endClassTeacherAssignment']);

        // Students
        Route::get('/sunday-school/students', [SundaySchoolController::class, 'listStudents']);
        Route::post('/sunday-school/students', [SundaySchoolController::class, 'storeStudentEnrollment']);
        Route::post('/sunday-school/students/{id}/approve', [SundaySchoolController::class, 'approveStudentEnrollment']);
        Route::post('/sunday-school/students/{id}/discontinue', [SundaySchoolController::class, 'discontinueStudentEnrollment']);

        // Attendance
        Route::post('/sunday-school/attendance/mark', [SundaySchoolController::class, 'markAttendance']);
        Route::get('/sunday-school/classes/{id}/attendance', [SundaySchoolController::class, 'classAttendance']);
        Route::get('/sunday-school/students/{id}/attendance', [SundaySchoolController::class, 'studentAttendance']);

        // Exams & Marks
        Route::get('/sunday-school/exams', [SundaySchoolController::class, 'listExams']);
        Route::post('/sunday-school/exams', [SundaySchoolController::class, 'storeExam']);
        Route::post('/sunday-school/exams/{id}/publish', [SundaySchoolController::class, 'publishExam']);
        Route::post('/sunday-school/exams/{id}/complete', [SundaySchoolController::class, 'completeExam']);
        Route::post('/sunday-school/marks', [SundaySchoolController::class, 'storeMarks']);
        Route::post('/sunday-school/marks/{id}/verify', [SundaySchoolController::class, 'verifyMarks']);

        // Reports & Promotions
        Route::post('/sunday-school/students/{id}/generate-progress-report', [SundaySchoolController::class, 'generateProgressReport'])->middleware(['throttle:download_routes', 'require.2fa']);
        Route::post('/sunday-school/students/{id}/promote', [SundaySchoolController::class, 'promoteStudent']);
        Route::get('/sunday-school/students/{id}/progress-reports', [SundaySchoolController::class, 'studentProgressReports']);

        // Certificates
        Route::post('/sunday-school/students/{id}/issue-certificate', [SundaySchoolController::class, 'issueCertificate']);
        Route::get('/sunday-school/students/{id}/certificates', [SundaySchoolController::class, 'studentCertificates']);

        // Parents Scoped Views
        Route::get('/sunday-school/parents/my-children', [SundaySchoolController::class, 'myChildren']);
        Route::get('/sunday-school/parents/children/{student_id}', [SundaySchoolController::class, 'childDetails']);

        // Protected exports
        Route::get('/sunday-school/exports/students', [SundaySchoolController::class, 'exportStudents']);
        Route::get('/sunday-school/exports/attendance', [SundaySchoolController::class, 'exportAttendance']);
        Route::get('/sunday-school/exports/exam-results', [SundaySchoolController::class, 'exportExamResults']);

        // Phase 6: Youth Association & Marthamariyam Samajam Module
        // Ministry Organizations
        Route::get('/ministry-organizations', [MinistryController::class, 'listOrganizations']);
        Route::post('/ministry-organizations', [MinistryController::class, 'storeOrganization']);
        Route::get('/ministry-organizations/{id}', [MinistryController::class, 'showOrganization']);
        Route::put('/ministry-organizations/{id}', [MinistryController::class, 'updateOrganization']);
        Route::post('/ministry-organizations/{id}/archive', [MinistryController::class, 'archiveOrganization']);

        // Ministry Units
        Route::get('/ministry-units', [MinistryController::class, 'listUnits']);
        Route::post('/ministry-units', [MinistryController::class, 'storeUnit']);
        Route::get('/ministry-units/{id}', [MinistryController::class, 'showUnit']);
        Route::put('/ministry-units/{id}', [MinistryController::class, 'updateUnit']);
        Route::post('/ministry-units/{id}/activate', [MinistryController::class, 'activateUnit']);
        Route::post('/ministry-units/{id}/archive', [MinistryController::class, 'archiveUnit']);

        // Ministry Memberships
        Route::get('/ministry-memberships', [MinistryController::class, 'listMemberships']);
        Route::post('/ministry-memberships', [MinistryController::class, 'storeMembership']);
        Route::post('/ministry-memberships/{id}/approve', [MinistryController::class, 'approveMembership']);
        Route::post('/ministry-memberships/{id}/reject', [MinistryController::class, 'rejectMembership']);
        Route::post('/ministry-memberships/{id}/archive', [MinistryController::class, 'archiveMembership']);

        // Ministry Office Bearers
        Route::get('/ministry-office-bearers', [MinistryController::class, 'listOfficeBearers']);
        Route::post('/ministry-office-bearers', [MinistryController::class, 'storeOfficeBearer']);
        Route::post('/ministry-office-bearers/{id}/end-term', [MinistryController::class, 'endOfficeBearerTerm']);

        // Ministry Activities
        Route::get('/ministry-activities', [MinistryController::class, 'listActivities']);
        Route::post('/ministry-activities', [MinistryController::class, 'storeActivity']);
        Route::post('/ministry-activities/{id}/publish', [MinistryController::class, 'publishActivity']);
        Route::post('/ministry-activities/{id}/complete', [MinistryController::class, 'completeActivity']);
        Route::post('/ministry-activities/{id}/cancel', [MinistryController::class, 'cancelActivity']);

        // Ministry Attendance
        Route::post('/ministry-attendance/mark', [MinistryController::class, 'markAttendance']);
        Route::get('/ministry-activities/{id}/attendance', [MinistryController::class, 'activityAttendance']);

        // Ministry Service Logs
        Route::get('/ministry-service-logs', [MinistryController::class, 'listServiceLogs']);
        Route::post('/ministry-service-logs', [MinistryController::class, 'storeServiceLog']);
        Route::post('/ministry-service-logs/{id}/verify', [MinistryController::class, 'verifyServiceLog']);
        Route::post('/ministry-service-logs/{id}/reject', [MinistryController::class, 'rejectServiceLog']);

        // Ministry Reports
        Route::get('/ministry-reports/overview', [MinistryController::class, 'reportsOverview']);
        Route::get('/ministry-reports/by-church', [MinistryController::class, 'reportsByChurch']);

        // Phase 7: Finance, Donations, Receipts & Basic Parish Accounting Module
        // Finance Category Routes
        Route::get('/finance/categories', [FinanceController::class, 'listCategories']);
        Route::post('/finance/categories', [FinanceController::class, 'storeCategory']);
        Route::get('/finance/categories/{id}', [FinanceController::class, 'showCategory']);
        Route::put('/finance/categories/{id}', [FinanceController::class, 'updateCategory']);
        Route::post('/finance/categories/{id}/archive', [FinanceController::class, 'archiveCategory']);

        // Donation Routes
        Route::get('/finance/donations', [FinanceController::class, 'listDonations']);
        Route::post('/finance/donations', [FinanceController::class, 'storeDonation']);
        Route::get('/finance/donations/{id}', [FinanceController::class, 'showDonation']);
        Route::put('/finance/donations/{id}', [FinanceController::class, 'updateDonation']);
        Route::post('/finance/donations/{id}/mark-received', [FinanceController::class, 'markDonationReceived']);
        Route::post('/finance/donations/{id}/approve', [FinanceController::class, 'approveDonation']);
        Route::post('/finance/donations/{id}/cancel', [FinanceController::class, 'cancelDonation']);
        Route::post('/finance/donations/{id}/generate-receipt', [FinanceController::class, 'generateDonationReceipt']);

        // Income Routes
        Route::get('/finance/income', [FinanceController::class, 'listIncome']);
        Route::post('/finance/income', [FinanceController::class, 'storeIncome']);
        Route::get('/finance/income/{id}', [FinanceController::class, 'showIncome']);
        Route::put('/finance/income/{id}', [FinanceController::class, 'updateIncome']);
        Route::post('/finance/income/{id}/submit', [FinanceController::class, 'submitIncome']);
        Route::post('/finance/income/{id}/approve', [FinanceController::class, 'approveIncome']);
        Route::post('/finance/income/{id}/reject', [FinanceController::class, 'rejectIncome']);
        Route::post('/finance/income/{id}/mark-received', [FinanceController::class, 'markIncomeReceived']);
        Route::post('/finance/income/{id}/generate-receipt', [FinanceController::class, 'generateIncomeReceipt']);

        // Expense Routes
        Route::get('/finance/expenses', [FinanceController::class, 'listExpenses']);
        Route::post('/finance/expenses', [FinanceController::class, 'storeExpense']);
        Route::get('/finance/expenses/{id}', [FinanceController::class, 'showExpense']);
        Route::put('/finance/expenses/{id}', [FinanceController::class, 'updateExpense']);
        Route::post('/finance/expenses/{id}/submit', [FinanceController::class, 'submitExpense']);
        Route::post('/finance/expenses/{id}/approve', [FinanceController::class, 'approveExpense']);
        Route::post('/finance/expenses/{id}/reject', [FinanceController::class, 'rejectExpense']);
        Route::post('/finance/expenses/{id}/mark-paid', [FinanceController::class, 'markExpensePaid']);
        Route::post('/finance/expenses/{id}/cancel', [FinanceController::class, 'cancelExpense']);
        Route::get('/finance/expenses/{id}/download-bill', [FinanceController::class, 'downloadBill'])->middleware(['throttle:download_routes', 'require.2fa']);

        // Receipt Routes
        Route::get('/finance/receipts', [FinanceController::class, 'listReceipts']);
        Route::get('/finance/receipts/{id}', [FinanceController::class, 'showReceipt']);
        Route::get('/finance/receipts/{id}/download', [FinanceController::class, 'downloadReceipt'])->middleware(['throttle:download_routes', 'require.2fa']);
        Route::post('/finance/receipts/{id}/cancel', [FinanceController::class, 'cancelReceipt'])->middleware('require.2fa');

        // Finance Approval Routes
        Route::get('/finance/approvals', [FinanceController::class, 'listApprovals']);
        Route::post('/finance/approvals/{id}/approve', [FinanceController::class, 'approveApproval']);
        Route::post('/finance/approvals/{id}/reject', [FinanceController::class, 'rejectApproval']);

        // Finance Report Routes
        Route::get('/finance/reports/summary', [FinanceController::class, 'reportsSummary']);
        Route::get('/finance/reports/by-church', [FinanceController::class, 'reportsByChurch']);
        Route::get('/finance/reports/by-category', [FinanceController::class, 'reportsByCategory']);
        Route::get('/finance/reports/monthly', [FinanceController::class, 'reportsMonthly']);
        Route::get('/finance/reports/export', [FinanceController::class, 'reportsExport'])->middleware('require.2fa');

        // Course/Event link
        Route::post('/finance/link-registration', [FinanceController::class, 'linkRegistrationPayment']);

        // Phase 13: Full Church Accounting, Programme Finance & Priest Payment Management
        // Masters
        Route::get('/finance/coa', [FinanceController::class, 'getCOA']);
        Route::get('/finance/income-heads', [FinanceController::class, 'listIncomeHeads']);
        Route::post('/finance/income-heads', [FinanceController::class, 'storeIncomeHead']);
        Route::get('/finance/expense-heads', [FinanceController::class, 'listExpenseHeads']);
        Route::post('/finance/expense-heads', [FinanceController::class, 'storeExpenseHead']);
        Route::get('/finance/fund-classes', [FinanceController::class, 'listFundClasses']);
        Route::get('/finance/programme-accounts', [FinanceController::class, 'listProgrammeAccounts']);
        Route::post('/finance/programme-accounts', [FinanceController::class, 'storeProgrammeAccount']);

        // Money Accounts & Balances
        Route::get('/finance/money-accounts', [FinanceController::class, 'listMoneyAccounts']);
        Route::get('/finance/money-accounts/balances', [FinanceController::class, 'listMoneyAccountBalances']);

        // Income Headers & Lines
        Route::get('/finance/income-headers', [FinanceController::class, 'listIncomeHeaders']);
        Route::post('/finance/income-headers', [FinanceController::class, 'storeIncomeHeader']);
        Route::get('/finance/income-headers/{id}', [FinanceController::class, 'showIncomeHeader']);
        Route::put('/finance/income-headers/{id}', [FinanceController::class, 'updateIncomeHeader']);
        Route::post('/finance/income-headers/{id}/confirm', [FinanceController::class, 'confirmIncomeHeader']);

        // Expense Headers & Lines
        Route::get('/finance/expense-headers', [FinanceController::class, 'listExpenseHeaders']);
        Route::post('/finance/expense-headers', [FinanceController::class, 'storeExpenseHeader']);
        Route::get('/finance/expense-headers/{id}', [FinanceController::class, 'showExpenseHeader']);
        Route::put('/finance/expense-headers/{id}', [FinanceController::class, 'updateExpenseHeader']);
        Route::post('/finance/expense-headers/{id}/pay', [FinanceController::class, 'payExpenseHeader']);

        // Priest Payments
        Route::get('/finance/priest-payments', [FinanceController::class, 'listPriestPayments']);
        Route::post('/finance/priest-payments', [FinanceController::class, 'storePriestPayment']);
        Route::post('/finance/priest-payments/{id}/confirm', [FinanceController::class, 'confirmPriestPayment']);

        // Cash Batches
        Route::get('/finance/cash-batches', [FinanceController::class, 'listCashBatches']);
        Route::post('/finance/cash-batches/open', [FinanceController::class, 'openCashBatch']);
        Route::post('/finance/cash-batches/{id}/close', [FinanceController::class, 'closeCashBatch']);

        // Transfers
        Route::get('/finance/transfers', [FinanceController::class, 'listTransfers']);
        Route::post('/finance/transfers', [FinanceController::class, 'storeTransfer']);
        Route::post('/finance/transfers/{id}/confirm', [FinanceController::class, 'confirmTransfer']);

        // Bank Statement Imports & Reconciliation
        Route::post('/finance/bank-statements/import', [FinanceController::class, 'importBankStatement']);
        Route::get('/finance/bank-statements/lines', [FinanceController::class, 'listBankStatementLines']);
        Route::post('/finance/bank-statements/lines/{id}/match', [FinanceController::class, 'matchBankStatementLine']);

        // Ledger Entries
        Route::get('/finance/ledger-entries', [FinanceController::class, 'listLedgerEntries']);

        // Priest Portal self-view stipend claims
        Route::get('/priest/finance', [PriestPortalFinanceController::class, 'listPayments']);
        Route::get('/priest/finance/{id}/advice', [PriestPortalFinanceController::class, 'downloadAdvice'])->middleware(['throttle:download_routes', 'require.2fa']);

        // Clergy Admin Routes
        Route::get('/clergy/import-sources', [ClergyAdminController::class, 'listImportSources']);
        Route::post('/clergy/import-sources', [ClergyAdminController::class, 'createImportSource']);
        Route::post('/clergy/import-sources/{id}/fetch', [ClergyAdminController::class, 'triggerFetch']);
        Route::get('/clergy/import-runs', [ClergyAdminController::class, 'listImportRuns']);
        Route::get('/clergy/import-runs/{id}', [ClergyAdminController::class, 'getImportRun']);
        Route::get('/clergy/import-runs/{id}/records', [ClergyAdminController::class, 'getImportRecords']);
        Route::post('/clergy/import-records/{id}/accept', [ClergyAdminController::class, 'acceptImportRecord']);
        Route::post('/clergy/import-records/{id}/link-member', [ClergyAdminController::class, 'linkImportRecordMember']);
        Route::post('/clergy/import-records/{id}/ignore', [ClergyAdminController::class, 'ignoreImportRecord']);

        Route::get('/clergy/priests', [ClergyAdminController::class, 'listPriests']);
        Route::post('/clergy/priests', [ClergyAdminController::class, 'createPriest']);
        Route::get('/clergy/priests/{id}', [ClergyAdminController::class, 'getPriest']);
        Route::put('/clergy/priests/{id}', [ClergyAdminController::class, 'updatePriest']);
        Route::post('/clergy/priests/{id}/create-login', [ClergyAdminController::class, 'createPriestLogin']);
        Route::post('/clergy/priests/{id}/archive', [ClergyAdminController::class, 'archivePriest']);

        Route::get('/clergy/assignments', [ClergyAdminController::class, 'listAssignments']);
        Route::post('/clergy/assignments', [ClergyAdminController::class, 'createAssignment']);
        Route::get('/clergy/assignments/{id}', [ClergyAdminController::class, 'getAssignment']);
        Route::put('/clergy/assignments/{id}', [ClergyAdminController::class, 'updateAssignment']);
        Route::post('/clergy/assignments/{id}/end', [ClergyAdminController::class, 'endAssignment']);
        Route::get('/clergy/priests/{id}/assignments', [ClergyAdminController::class, 'getPriestAssignments']);
        Route::get('/clergy/churches/{id}/clergy', [ClergyAdminController::class, 'getChurchClergy']);

        Route::get('/clergy/transfers', [ClergyAdminController::class, 'listTransfers']);
        Route::post('/clergy/transfers', [ClergyAdminController::class, 'createTransfer']);
        Route::get('/clergy/transfers/{id}', [ClergyAdminController::class, 'getTransfer']);
        Route::post('/clergy/transfers/{id}/approve', [ClergyAdminController::class, 'approveTransfer']);
        Route::post('/clergy/transfers/{id}/complete', [ClergyAdminController::class, 'completeTransfer']);
        Route::post('/clergy/transfers/{id}/cancel', [ClergyAdminController::class, 'cancelTransfer']);

        Route::get('/clergy/responsibilities', [ClergyAdminController::class, 'listResponsibilities']);
        Route::post('/clergy/responsibilities', [ClergyAdminController::class, 'createResponsibility']);
        Route::get('/clergy/responsibilities/{id}', [ClergyAdminController::class, 'getResponsibility']);
        Route::put('/clergy/responsibilities/{id}', [ClergyAdminController::class, 'updateResponsibility']);
        Route::post('/clergy/responsibilities/{id}/end', [ClergyAdminController::class, 'endResponsibility']);
        Route::get('/clergy/members/{id}/responsibilities', [ClergyAdminController::class, 'getMemberResponsibilities']);
        Route::get('/clergy/churches/{id}/office-bearers', [ClergyAdminController::class, 'getChurchOfficeBearers']);

        // Priest Portal Routes
        Route::get('/priest/dashboard', [PriestPortalController::class, 'getDashboard']);
        Route::get('/priest/assigned-churches', [PriestPortalController::class, 'getAssignedChurches']);
        Route::post('/priest/switch-church', [PriestPortalController::class, 'switchChurch']);
        Route::get('/priest/assignments', [PriestPortalController::class, 'getAssignments']);

        // Phase 8 Website CMS Admin Routes
        Route::get('/cms/settings', [\App\Http\Controllers\Api\V1\CmsController::class, 'getSettings']);
        Route::put('/cms/settings', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateSetting']);

        // Website Pages
        Route::get('/cms/pages', [\App\Http\Controllers\Api\V1\CmsController::class, 'listPages']);
        Route::post('/cms/pages', [\App\Http\Controllers\Api\V1\CmsController::class, 'storePage']);
        Route::get('/cms/pages/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'showPage']);
        Route::put('/cms/pages/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updatePage']);
        Route::post('/cms/pages/{id}/submit', [\App\Http\Controllers\Api\V1\CmsController::class, 'submitPage']);
        Route::post('/cms/pages/{id}/approve', [\App\Http\Controllers\Api\V1\CmsController::class, 'approvePage']);
        Route::post('/cms/pages/{id}/reject', [\App\Http\Controllers\Api\V1\CmsController::class, 'rejectPage']);
        Route::post('/cms/pages/{id}/publish', [\App\Http\Controllers\Api\V1\CmsController::class, 'publishPage']);
        Route::post('/cms/pages/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archivePage']);

        // News Posts
        Route::get('/cms/news', [\App\Http\Controllers\Api\V1\CmsController::class, 'listNews']);
        Route::post('/cms/news', [\App\Http\Controllers\Api\V1\CmsController::class, 'storeNews']);
        Route::get('/cms/news/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'showNews']);
        Route::put('/cms/news/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateNews']);
        Route::post('/cms/news/{id}/submit', [\App\Http\Controllers\Api\V1\CmsController::class, 'submitNews']);
        Route::post('/cms/news/{id}/approve', [\App\Http\Controllers\Api\V1\CmsController::class, 'approveNews']);
        Route::post('/cms/news/{id}/reject', [\App\Http\Controllers\Api\V1\CmsController::class, 'rejectNews']);
        Route::post('/cms/news/{id}/publish', [\App\Http\Controllers\Api\V1\CmsController::class, 'publishNews']);
        Route::post('/cms/news/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archiveNews']);

        // Downloads
        Route::get('/cms/downloads', [\App\Http\Controllers\Api\V1\CmsController::class, 'listDownloads']);
        Route::post('/cms/downloads', [\App\Http\Controllers\Api\V1\CmsController::class, 'storeDownload']);
        Route::get('/cms/downloads/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'showDownload']);
        Route::put('/cms/downloads/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateDownload']);
        Route::post('/cms/downloads/{id}/submit', [\App\Http\Controllers\Api\V1\CmsController::class, 'submitDownload']);
        Route::post('/cms/downloads/{id}/approve', [\App\Http\Controllers\Api\V1\CmsController::class, 'approveDownload']);
        Route::post('/cms/downloads/{id}/reject', [\App\Http\Controllers\Api\V1\CmsController::class, 'rejectDownload']);
        Route::post('/cms/downloads/{id}/publish', [\App\Http\Controllers\Api\V1\CmsController::class, 'publishDownload']);
        Route::post('/cms/downloads/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archiveDownload']);
        Route::get('/cms/downloads/{id}/download', [\App\Http\Controllers\Api\V1\CmsController::class, 'userDownloadFile']);

        // Kalpanas / Circulars
        Route::get('/cms/kalpana-circulars', [\App\Http\Controllers\Api\V1\CmsController::class, 'listKalpanas']);
        Route::post('/cms/kalpana-circulars', [\App\Http\Controllers\Api\V1\CmsController::class, 'storeKalpana']);
        Route::get('/cms/kalpana-circulars/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'showKalpana']);
        Route::put('/cms/kalpana-circulars/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateKalpana']);
        Route::post('/cms/kalpana-circulars/{id}/submit', [\App\Http\Controllers\Api\V1\CmsController::class, 'submitKalpana']);
        Route::post('/cms/kalpana-circulars/{id}/approve', [\App\Http\Controllers\Api\V1\CmsController::class, 'approveKalpana']);
        Route::post('/cms/kalpana-circulars/{id}/reject', [\App\Http\Controllers\Api\V1\CmsController::class, 'rejectKalpana']);
        Route::post('/cms/kalpana-circulars/{id}/publish', [\App\Http\Controllers\Api\V1\CmsController::class, 'publishKalpana']);
        Route::post('/cms/kalpana-circulars/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archiveKalpana']);

        // Galleries
        Route::get('/cms/galleries', [\App\Http\Controllers\Api\V1\CmsController::class, 'listGalleries']);
        Route::post('/cms/galleries', [\App\Http\Controllers\Api\V1\CmsController::class, 'storeGallery']);
        Route::get('/cms/galleries/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'showGallery']);
        Route::put('/cms/galleries/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateGallery']);
        Route::post('/cms/galleries/{id}/submit', [\App\Http\Controllers\Api\V1\CmsController::class, 'submitGallery']);
        Route::post('/cms/galleries/{id}/approve', [\App\Http\Controllers\Api\V1\CmsController::class, 'approveGallery']);
        Route::post('/cms/galleries/{id}/reject', [\App\Http\Controllers\Api\V1\CmsController::class, 'rejectGallery']);
        Route::post('/cms/galleries/{id}/publish', [\App\Http\Controllers\Api\V1\CmsController::class, 'publishGallery']);
        Route::post('/cms/galleries/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archiveGallery']);
        Route::post('/cms/galleries/{id}/items', [\App\Http\Controllers\Api\V1\CmsController::class, 'addGalleryItem']);
        Route::put('/cms/gallery-items/{id}', [\App\Http\Controllers\Api\V1\CmsController::class, 'updateGalleryItem']);
        Route::post('/cms/gallery-items/{id}/archive', [\App\Http\Controllers\Api\V1\CmsController::class, 'archiveGalleryItem']);

        // Approvals Queue
        Route::get('/cms/approvals', [\App\Http\Controllers\Api\V1\CmsController::class, 'listApprovals']);
        Route::post('/cms/approvals/{id}/decision', [\App\Http\Controllers\Api\V1\CmsController::class, 'processApprovalDecision']);

        // CMS reports
        Route::get('/cms/reports', [\App\Http\Controllers\Api\V1\CmsController::class, 'cmsReports']);

        // =========================================================================
        // Communication Module Routes
        // =========================================================================
        // Templates
        Route::get('/communications/templates', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'listTemplates']);
        Route::post('/communications/templates', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'storeTemplate']);
        Route::get('/communications/templates/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'showTemplate']);
        Route::put('/communications/templates/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'updateTemplate']);
        Route::post('/communications/templates/{id}/archive', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'archiveTemplate']);

        // Announcements
        Route::get('/communications/announcements', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'listAnnouncements']);
        Route::post('/communications/announcements', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'storeAnnouncement']);
        Route::get('/communications/announcements/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'showAnnouncement']);
        Route::put('/communications/announcements/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'updateAnnouncement']);
        Route::post('/communications/announcements/{id}/send', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'sendAnnouncement']);
        Route::post('/communications/announcements/{id}/schedule', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'scheduleAnnouncement']);
        Route::post('/communications/announcements/{id}/cancel', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'cancelAnnouncement']);
        Route::post('/communications/announcements/{id}/archive', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'archiveAnnouncement']);
        Route::post('/communications/announcements/preview-targets', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'previewTargets']);

        // In-App Notifications (Inbox)
        Route::get('/communications/notifications', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'listNotifications']);
        Route::get('/communications/notifications/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'showNotification']);
        Route::post('/communications/notifications/{id}/mark-read', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'markRead']);
        Route::post('/communications/notifications/mark-all-read', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'markAllRead']);

        // Delivery Logs
        Route::get('/communications/deliveries', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'listDeliveries']);
        Route::get('/communications/deliveries/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'showDelivery']);
        Route::post('/communications/deliveries/{id}/retry', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'retryDelivery']);

        // Preferences
        Route::get('/communications/preferences', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'getPreferences']);
        Route::put('/communications/preferences', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'updatePreference']);

        // Reminders
        Route::get('/communications/reminders', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'listReminders']);
        Route::post('/communications/reminders', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'storeReminder']);
        Route::get('/communications/reminders/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'showReminder']);
        Route::put('/communications/reminders/{id}', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'updateReminder']);
        Route::post('/communications/reminders/{id}/cancel', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'cancelReminder']);

        // Reports
        Route::get('/communications/reports/overview', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'reportsOverview']);
        Route::get('/communications/reports/delivery-status', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'reportsDeliveryStatus']);
        Route::get('/communications/reports/failed-deliveries', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'reportsFailedDeliveries']);
        Route::get('/communications/reports/by-channel', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'reportsByChannel']);
        Route::get('/communications/reports/export', [\App\Http\Controllers\Api\V1\CommunicationController::class, 'reportsExport']);

        // =========================================================================
        // Member Portal Routes
        // =========================================================================
        Route::prefix('member-portal')->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'me']);
            Route::get('/dashboard', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'dashboard']);
            Route::get('/family', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'familyProfile']);
            Route::get('/family/members', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'familyMembers']);
            Route::get('/members/{id}', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'memberProfile']);
            
            // Correction Requests
            Route::get('/correction-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listCorrectionRequests']);
            Route::post('/correction-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'storeCorrectionRequest']);
            Route::get('/correction-requests/{id}', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'showCorrectionRequest']);
            Route::post('/correction-requests/{id}/cancel', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'cancelCorrectionRequest']);
            
            // Documents
            Route::get('/documents', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listDocuments']);
            Route::post('/documents', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'storeDocument']);
            Route::get('/documents/{id}/download', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'downloadDocument'])->middleware(['throttle:download_routes', 'require.2fa']);
            Route::post('/documents/{id}/archive', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'archiveDocument']);
            
            // Certificates
            Route::get('/certificate-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listCertificateRequests']);
            Route::post('/certificate-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'storeCertificateRequest']);
            Route::get('/certificate-requests/{id}', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'showCertificateRequest']);
            Route::get('/certificates', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listCertificates']);
            Route::get('/certificates/{id}/download', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'downloadCertificate'])->middleware(['throttle:download_routes', 'require.2fa']);
            
            // Events & Courses
            Route::get('/events', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listEvents']);
            Route::post('/events/{id}/register', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'registerEvent']);
            Route::get('/event-registrations', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listEventRegistrations']);
            Route::get('/courses', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listCourses']);
            Route::post('/course-batches/{id}/register', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'registerCourseBatch']);
            Route::get('/course-registrations', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listCourseRegistrations']);
            
            // Sunday School Parent View
            Route::get('/children', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listChildren']);
            Route::get('/children/{id}/sunday-school', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'childSundaySchool']);
            Route::get('/children/{id}/attendance', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'childAttendance']);
            Route::get('/children/{id}/marks', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'childMarks']);
            Route::get('/children/{id}/progress-reports', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'childProgressReports']);
            Route::get('/children/{id}/certificates', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'childCertificates']);
            
            // Finance / Receipts
            Route::get('/donations', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listDonations']);
            Route::get('/receipts', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listReceipts']);
            Route::get('/receipts/{id}/download', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'downloadReceipt'])->middleware(['throttle:download_routes', 'require.2fa']);
            
            // Transfer Requests
            Route::get('/transfer-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listTransferRequests']);
            Route::post('/transfer-requests', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'storeTransferRequest']);
            Route::get('/transfer-requests/{id}', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'showTransferRequest']);
            Route::post('/transfer-requests/{id}/cancel', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'cancelTransferRequest']);
            
            // Notifications & Preferences
            Route::get('/notifications', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'listNotifications']);
            Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'markNotificationRead']);
            Route::get('/preferences', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'getPreferences']);
            Route::put('/preferences', [\App\Http\Controllers\Api\V1\MemberPortalController::class, 'updatePreferences']);

            // Admin Management Endpoints
            Route::get('/admin/access', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'listAccess']);
            Route::post('/admin/access/invite', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'inviteAccess']);
            Route::post('/admin/access/{id}/suspend', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'suspendAccess'])->middleware('require.2fa');
            Route::post('/admin/access/{id}/revoke', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'revokeAccess'])->middleware('require.2fa');
            Route::get('/admin/correction-requests', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'listCorrectionRequests']);
            Route::post('/admin/correction-requests/{id}/approve', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'approveCorrectionRequest']);
            Route::post('/admin/correction-requests/{id}/reject', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'rejectCorrectionRequest']);
            Route::get('/admin/documents', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'listDocuments']);
            Route::post('/admin/documents/{id}/accept', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'acceptDocument']);
            Route::post('/admin/documents/{id}/reject', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'rejectDocument']);
            Route::get('/admin/activity-logs', [\App\Http\Controllers\Api\V1\MemberPortalAdminController::class, 'listActivityLogs']);
        });

        // =========================================================================
        // Advanced Reports, Analytics & Diocese Intelligence Routes
        // =========================================================================
        Route::prefix('reports')->group(function () {
            // Definitions
            Route::get('/definitions', [\App\Http\Controllers\Api\V1\ReportController::class, 'definitions']);
            Route::get('/definitions/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'definition']);

            // Saved Reports
            Route::get('/saved', [\App\Http\Controllers\Api\V1\ReportController::class, 'savedReports']);
            Route::post('/saved', [\App\Http\Controllers\Api\V1\ReportController::class, 'storeSavedReport']);
            Route::get('/saved/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'showSavedReport']);
            Route::put('/saved/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'updateSavedReport']);
            Route::delete('/saved/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'destroySavedReport']);

            // Runs
            Route::post('/run', [\App\Http\Controllers\Api\V1\ReportController::class, 'run']);
            Route::get('/runs', [\App\Http\Controllers\Api\V1\ReportController::class, 'runs']);
            Route::get('/runs/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'showRun']);

            // Exports
            Route::post('/runs/{id}/export', [\App\Http\Controllers\Api\V1\ReportController::class, 'createExport'])->middleware('require.2fa');
            Route::get('/exports', [\App\Http\Controllers\Api\V1\ReportController::class, 'exports']);
            Route::get('/exports/{id}/download', [\App\Http\Controllers\Api\V1\ReportController::class, 'downloadExport'])->middleware(['throttle:download_routes', 'require.2fa']);
            Route::post('/exports/{id}/expire', [\App\Http\Controllers\Api\V1\ReportController::class, 'expireExport']);

            // Scheduled
            Route::get('/scheduled', [\App\Http\Controllers\Api\V1\ReportController::class, 'scheduledReports']);
            Route::post('/scheduled', [\App\Http\Controllers\Api\V1\ReportController::class, 'storeScheduledReport']);
            Route::get('/scheduled/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'showScheduledReport']);
            Route::put('/scheduled/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'updateScheduledReport']);
            Route::post('/scheduled/{id}/pause', [\App\Http\Controllers\Api\V1\ReportController::class, 'pauseScheduledReport']);
            Route::post('/scheduled/{id}/resume', [\App\Http\Controllers\Api\V1\ReportController::class, 'resumeScheduledReport']);
            Route::post('/scheduled/{id}/cancel', [\App\Http\Controllers\Api\V1\ReportController::class, 'cancelScheduledReport']);

            // Widgets
            Route::get('/dashboard-widgets', [\App\Http\Controllers\Api\V1\ReportController::class, 'dashboardWidgets']);
            Route::put('/dashboard-widgets/{id}', [\App\Http\Controllers\Api\V1\ReportController::class, 'updateDashboardWidget']);

            // Shortcuts
            Route::get('/overview/diocese', [\App\Http\Controllers\Api\V1\ReportController::class, 'dioceseOverview']);
            Route::get('/overview/parish', [\App\Http\Controllers\Api\V1\ReportController::class, 'parishOverview']);
            Route::get('/members', [\App\Http\Controllers\Api\V1\ReportController::class, 'members']);
            Route::get('/sacraments', [\App\Http\Controllers\Api\V1\ReportController::class, 'sacraments']);
            Route::get('/certificates', [\App\Http\Controllers\Api\V1\ReportController::class, 'certificates']);
            Route::get('/courses-events', [\App\Http\Controllers\Api\V1\ReportController::class, 'coursesEvents']);
            Route::get('/sunday-school', [\App\Http\Controllers\Api\V1\ReportController::class, 'sundaySchool']);
            Route::get('/ministries', [\App\Http\Controllers\Api\V1\ReportController::class, 'ministries']);
            Route::get('/finance', [\App\Http\Controllers\Api\V1\ReportController::class, 'finance']);
            Route::get('/cms', [\App\Http\Controllers\Api\V1\ReportController::class, 'cms']);
            Route::get('/communications', [\App\Http\Controllers\Api\V1\ReportController::class, 'communications']);
            Route::get('/member-portal', [\App\Http\Controllers\Api\V1\ReportController::class, 'memberPortal']);
            Route::get('/gdpr', [\App\Http\Controllers\Api\V1\ReportController::class, 'gdpr']);
            Route::get('/audit', [\App\Http\Controllers\Api\V1\ReportController::class, 'audit']);
        });

        // System Health & Monitoring Routes
        Route::prefix('system')->group(function () {
            Route::get('/health', [\App\Http\Controllers\Api\V1\SystemController::class, 'health']);
            Route::get('/security-summary', [\App\Http\Controllers\Api\V1\SystemController::class, 'securitySummary']);
            Route::get('/role-permission-audit', [\App\Http\Controllers\Api\V1\SystemController::class, 'rolePermissionAudit']);
            Route::get('/storage-check', [\App\Http\Controllers\Api\V1\SystemController::class, 'storageCheck']);
            Route::get('/queue-status', [\App\Http\Controllers\Api\V1\SystemController::class, 'queueStatus']);
            Route::get('/scheduler-status', [\App\Http\Controllers\Api\V1\SystemController::class, 'schedulerStatus']);
        });

        // GDPR Workflows Routes
        Route::prefix('gdpr')->group(function () {
            Route::get('/requests', [\App\Http\Controllers\Api\V1\GdprController::class, 'requests']);
            Route::post('/export-request', [\App\Http\Controllers\Api\V1\GdprController::class, 'exportRequest']);
            Route::post('/anonymization-request', [\App\Http\Controllers\Api\V1\GdprController::class, 'anonymizationRequest']);
            Route::post('/requests/{id}/approve', [\App\Http\Controllers\Api\V1\GdprController::class, 'approve'])->middleware('require.2fa:recent');
            Route::post('/requests/{id}/reject', [\App\Http\Controllers\Api\V1\GdprController::class, 'reject'])->middleware('require.2fa:recent');
            Route::get('/consent-summary', [\App\Http\Controllers\Api\V1\GdprController::class, 'consentSummary']);
            Route::get('/data-retention-summary', [\App\Http\Controllers\Api\V1\GdprController::class, 'dataRetentionSummary']);
        });

        // Security Admin & 2FA Management Routes
        Route::prefix('security')->group(function () {
            Route::get('/2fa/status', [\App\Http\Controllers\Api\V1\SecurityController::class, 'status']);
            Route::post('/2fa/enable', [\App\Http\Controllers\Api\V1\SecurityController::class, 'enable']);
            Route::post('/2fa/verify', [\App\Http\Controllers\Api\V1\SecurityController::class, 'verify'])->middleware('throttle:2fa_verify');
            Route::post('/2fa/disable', [\App\Http\Controllers\Api\V1\SecurityController::class, 'disable'])->middleware('require.2fa:recent');
            Route::get('/login-audit', [\App\Http\Controllers\Api\V1\SecurityController::class, 'loginAudit']);
            Route::get('/sensitive-permissions', [\App\Http\Controllers\Api\V1\SecurityController::class, 'sensitivePermissions']);
        });
    });
});
