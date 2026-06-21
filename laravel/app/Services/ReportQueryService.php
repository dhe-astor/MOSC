<?php

namespace App\Services;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\Sacrament;
use App\Models\Certificate;
use App\Models\CourseBatch;
use App\Models\CourseRegistration;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolTeacher;
use App\Models\MinistryMembership;
use App\Models\MinistryUnit;
use App\Models\MinistryActivity;
use App\Models\Donation;
use App\Models\IncomeRecord;
use App\Models\ExpenseRecord;
use App\Models\Receipt;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\WebsiteDownload;
use App\Models\KalpanaCircular;
use App\Models\NotificationDelivery;
use App\Models\MemberPortalAccess;
use App\Models\ProfileCorrectionRequest;
use App\Models\AuditLog;
use App\Models\ReportDefinition;
use App\Models\SavedReport;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    /**
     * Run the report matching the key, applying filters and strict scopes.
     */
    public static function runReport(string $reportKey, array $filters, User $user): array
    {
        $definition = ReportDefinition::where('report_key', $reportKey)->firstOrFail();

        // 1. Authorize role and permission
        self::authorizeReport($definition, $user);

        // 2. Resolve church scope from filters
        $churchId = isset($filters['church_id']) ? (int)$filters['church_id'] : null;
        if ($churchId && !ChurchAccessService::canAccessChurch($user, $churchId)) {
            abort(403, 'Unauthorized access to this church report data.');
        }

        // Log sensitive report access
        $isSensitive = in_array($definition->report_category, ['finance', 'sunday_school', 'gdpr', 'audit']) || 
                       in_array($reportKey, ['members_families_list', 'portal_usage']);
        
        if ($isSensitive) {
            AuditLogService::log(
                'Reports',
                'Report Accessed',
                "User run sensitive report: {$definition->name}",
                null,
                ['report_key' => $reportKey, 'filters' => $filters],
                null,
                $churchId,
                $user->default_diocese_id
            );
        }

        $headers = [];
        $data = [];
        $summary = [];

        switch ($reportKey) {
            case 'diocese_overview':
                $headers = ['Metric', 'Value'];
                $totalChurches = Church::count();
                $totalFamilies = Family::count();
                $totalMembers = Member::count();
                $activeMembers = Member::where('membership_status', 'active')->count();
                $inactiveMembers = Member::where('membership_status', 'inactive')->count();
                $certificatesIssued = Certificate::where('status', 'active')->count();

                $data = [
                    ['Metric' => 'Total Parishes/Churches', 'Value' => $totalChurches],
                    ['Metric' => 'Total Families', 'Value' => $totalFamilies],
                    ['Metric' => 'Total Registered Members', 'Value' => $totalMembers],
                    ['Metric' => 'Active Members', 'Value' => $activeMembers],
                    ['Metric' => 'Inactive Members', 'Value' => $inactiveMembers],
                    ['Metric' => 'Active Certificates Issued', 'Value' => $certificatesIssued],
                ];
                break;

            case 'parish_overview':
                $targetChurchId = $churchId ?? $user->active_church_id ?? $user->default_church_id;
                if (!$targetChurchId) {
                    abort(400, 'Church context is required.');
                }
                $headers = ['Metric', 'Count'];
                $families = Family::where('church_id', $targetChurchId)->count();
                $members = Member::where('church_id', $targetChurchId)->count();
                $active = Member::where('church_id', $targetChurchId)->where('membership_status', 'active')->count();
                
                $data = [
                    ['Metric' => 'Parish Families', 'Count' => $families],
                    ['Metric' => 'Parish Members', 'Count' => $members],
                    ['Metric' => 'Active Parish Members', 'Count' => $active],
                ];
                break;

            case 'members_families_list':
                $query = Member::query()->with('family');
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                if (isset($filters['status'])) {
                    $query->where('membership_status', $filters['status']);
                }

                if (isset($filters['missing_contact']) && $filters['missing_contact']) {
                    $query->where(function ($q) {
                        $q->whereNull('email')->orWhereNull('phone');
                    });
                }

                if (isset($filters['missing_gdpr']) && $filters['missing_gdpr']) {
                    $query->where('gdpr_consent', false);
                }

                $membersList = $query->limit(500)->get();

                $headers = ['Member Code', 'Full Name', 'Gender', 'Email', 'Phone', 'Relationship to Head', 'Status'];
                $data = $membersList->map(function ($m) {
                    return [
                        'Member Code' => $m->member_code,
                        'Full Name' => $m->full_name,
                        'Gender' => $m->gender,
                        'Email' => $m->email,
                        'Phone' => $m->phone,
                        'Relationship to Head' => $m->relationship_to_head,
                        'Status' => $m->membership_status,
                    ];
                })->toArray();

                // Mask contacts
                $data = self::maskContacts($data, $user);
                break;

            case 'sacramental_records':
                $query = Sacrament::query()->with('member');
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }
                
                // Exclude marriage
                $query->where('sacrament_type', '!=', 'marriage');

                if (isset($filters['sacrament_type'])) {
                    $query->where('sacrament_type', $filters['sacrament_type']);
                }

                $records = $query->limit(500)->get();
                $headers = ['Member', 'Sacrament Type', 'Sacrament Date', 'Place', 'Officiant', 'Status'];
                $data = $records->map(function ($s) {
                    return [
                        'Member' => $s->member?->full_name ?? 'N/A',
                        'Sacrament Type' => ucfirst($s->sacrament_type),
                        'Sacrament Date' => $s->sacrament_date ? Carbon::parse($s->sacrament_date)->toDateString() : 'N/A',
                        'Place' => $s->place,
                        'Officiant' => $s->officiant?->full_name ?? 'N/A',
                        'Status' => $s->status,
                    ];
                })->toArray();
                break;

            case 'certificates_issued':
                $query = Certificate::query()->with(['request', 'member']);
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                $records = $query->limit(500)->get();
                $headers = ['Certificate Number', 'Member', 'Template', 'Issued By', 'Issued Date', 'Status'];
                $data = $records->map(function ($c) {
                    return [
                        'Certificate Number' => $c->certificate_number,
                        'Member' => $c->member?->full_name ?? 'N/A',
                        'Template' => $c->template_name,
                        'Issued By' => $c->issued_by_name,
                        'Issued Date' => $c->issued_at ? Carbon::parse($c->issued_at)->toDateString() : 'N/A',
                        'Status' => $c->status,
                    ];
                })->toArray();
                break;

            case 'courses_summary':
                $query = CourseBatch::query()->with('course');
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                $batches = $query->get();
                $headers = ['Course Name', 'Batch Code', 'Start Date', 'End Date', 'Mode', 'Status'];
                $data = $batches->map(function ($b) {
                    return [
                        'Course Name' => $b->course?->name ?? 'N/A',
                        'Batch Code' => $b->batch_code,
                        'Start Date' => $b->start_datetime ? Carbon::parse($b->start_datetime)->toDateString() : 'N/A',
                        'End Date' => $b->end_datetime ? Carbon::parse($b->end_datetime)->toDateString() : 'N/A',
                        'Mode' => $b->mode,
                        'Status' => $b->status,
                    ];
                })->toArray();
                break;

            case 'events_summary':
                $query = Event::query();
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                $events = $query->get();
                $headers = ['Title', 'Start Date', 'End Date', 'Mode', 'Location', 'Status'];
                $data = $events->map(function ($e) {
                    return [
                        'Title' => $e->title,
                        'Start Date' => $e->start_datetime ? Carbon::parse($e->start_datetime)->toDateString() : 'N/A',
                        'End Date' => $e->end_datetime ? Carbon::parse($e->end_datetime)->toDateString() : 'N/A',
                        'Mode' => $e->mode,
                        'Location' => $e->location_name,
                        'Status' => $e->status,
                    ];
                })->toArray();
                break;

            case 'sunday_school_progress':
                $query = SundaySchoolStudent::query()->with(['member', 'class']);
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                // Strict teacher scoping
                $teacher = SundaySchoolTeacher::where('user_id', $user->id)->first();
                if ($teacher && !$user->hasRole(['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar'])) {
                    $classIds = SundaySchoolClass::where(function ($q) use ($teacher) {
                        $q->where('primary_teacher_id', $teacher->id)
                          ->orWhere('assistant_teacher_id', $teacher->id);
                    })->pluck('id')->toArray();

                    $assignedClassIds = DB::table('sunday_school_class_teacher_assignments')
                        ->where('teacher_id', $teacher->id)
                        ->where('status', 'active')
                        ->pluck('class_id')
                        ->toArray();

                    $allClassIds = array_values(array_unique(array_merge($classIds, $assignedClassIds)));
                    $query->whereIn('class_id', $allClassIds);
                }

                $students = $query->limit(500)->get();
                $headers = ['Student Name', 'Class Name', 'Academic Year', 'Attendance Rate', 'Status'];
                $data = $students->map(function ($s) {
                    return [
                        'Student Name' => $s->member?->full_name ?? 'N/A',
                        'Class Name' => $s->class?->class_name ?? 'N/A',
                        'Academic Year' => $s->academic_year_id,
                        'Attendance Rate' => 'N/A',
                        'Status' => $s->status,
                    ];
                })->toArray();
                break;

            case 'ministries_overview':
                $query = MinistryMembership::query()->with(['member', 'unit']);
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                // Coordinator scoping
                $member = Member::where('user_id', $user->id)->first();
                $isCoordinatorOnly = !$user->hasRole(['Super Admin', 'Diocese Admin', 'Parish Admin', 'Priest / Vicar']);
                if ($isCoordinatorOnly && $member) {
                    $unitIds = MinistryUnit::where(function ($q) use ($member) {
                        $q->where('coordinator_member_id', $member->id)
                          ->orWhere('secretary_member_id', $member->id)
                          ->orWhere('treasurer_member_id', $member->id);
                    })->pluck('id')->toArray();

                    $query->whereIn('ministry_unit_id', $unitIds);
                }

                $memberships = $query->get();
                $headers = ['Member Name', 'Ministry Unit', 'Type', 'Joined Date', 'Status'];
                $data = $memberships->map(function ($m) {
                    return [
                        'Member Name' => $m->member?->full_name ?? 'N/A',
                        'Ministry Unit' => $m->unit?->unit_name ?? 'N/A',
                        'Type' => $m->membership_type,
                        'Joined Date' => $m->joined_date ? Carbon::parse($m->joined_date)->toDateString() : 'N/A',
                        'Status' => $m->status,
                    ];
                })->toArray();
                break;

            case 'finance_statement':
                if (!$user->hasPermissionTo('view_finance_reports')) {
                    abort(403, 'Unauthorized to view finance reports.');
                }
                $targetChurchId = $churchId ?? $user->active_church_id;
                
                // Restrict Parish Treasurer
                if ($user->hasRole('Parish Treasurer') && !$user->hasRole(['Super Admin', 'Diocese Admin', 'Diocese Treasurer'])) {
                    $targetChurchId = $user->active_church_id;
                }

                $donations = Donation::where('status', 'received');
                $income = IncomeRecord::whereIn('status', ['received', 'approved']);
                $expense = ExpenseRecord::whereIn('status', ['approved', 'paid']);

                if ($targetChurchId) {
                    $donations->where('church_id', $targetChurchId);
                    $income->where('church_id', $targetChurchId);
                    $expense->where('church_id', $targetChurchId);
                }

                $headers = ['Metric', 'Amount (EUR)'];
                $totalDonations = $donations->sum('amount');
                $totalIncome = $income->sum('amount');
                $totalExpense = $expense->sum('amount');
                $balance = ($totalDonations + $totalIncome) - $totalExpense;

                $data = [
                    ['Metric' => 'Total Donations Received', 'Amount (EUR)' => $totalDonations],
                    ['Metric' => 'Total Other Income', 'Amount (EUR)' => $totalIncome],
                    ['Metric' => 'Total Expenses Approved/Paid', 'Amount (EUR)' => $totalExpense],
                    ['Metric' => 'Net Parish Balance', 'Amount (EUR)' => $balance],
                ];
                break;

            case 'cms_publishing':
                $headers = ['Item Type', 'Active Count', 'Total Count'];
                $data = [
                    ['Item Type' => 'Web Pages', 'Active Count' => WebsitePage::where('status', 'published')->count(), 'Total Count' => WebsitePage::count()],
                    ['Item Type' => 'News Posts', 'Active Count' => NewsPost::where('status', 'published')->count(), 'Total Count' => NewsPost::count()],
                    ['Item Type' => 'Downloads', 'Active Count' => WebsiteDownload::where('status', 'active')->count(), 'Total Count' => WebsiteDownload::count()],
                    ['Item Type' => 'Kalpana Circulars', 'Active Count' => KalpanaCircular::where('status', 'published')->count(), 'Total Count' => KalpanaCircular::count()],
                ];
                break;

            case 'communications_delivery':
                $headers = ['Notification Category', 'Delivered Count', 'Failed Count'];
                $deliveries = NotificationDelivery::select('notification_category', 'status', DB::raw('count(*) as count'))
                    ->groupBy('notification_category', 'status')
                    ->get();
                
                $data = $deliveries->map(function ($d) {
                    return [
                        'Notification Category' => ucfirst($d->notification_category),
                        'Status' => $d->status,
                        'Count' => $d->count,
                    ];
                })->toArray();
                break;

            case 'portal_usage':
                $headers = ['Invite Status', 'Access Count'];
                $usage = MemberPortalAccess::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get();

                $data = $usage->map(function ($u) {
                    return [
                        'Invite Status' => ucfirst($u->status),
                        'Access Count' => $u->count,
                    ];
                })->toArray();
                break;

            case 'gdpr_privacy_audit':
                if (!$user->hasPermissionTo('view_gdpr_reports')) {
                    abort(403, 'Unauthorized GDPR view.');
                }
                $headers = ['GDPR Missing Count', 'Photo Consent Count'];
                $missingGdpr = Member::where('gdpr_consent', false)->count();
                $missingPhoto = Member::where('photo_publication_consent', false)->count();

                $data = [
                    ['GDPR Missing Count' => $missingGdpr, 'Photo Consent Count' => $missingPhoto],
                ];
                break;

            case 'audit_logs':
                if (!$user->hasPermissionTo('view_audit_reports')) {
                    abort(403, 'Unauthorized to view audit logs.');
                }
                $query = AuditLog::query()->with('user');
                $query = ChurchAccessService::scopeQuery($user, $query);
                if ($churchId) {
                    $query->where('church_id', $churchId);
                }

                $logs = $query->orderBy('created_at', 'desc')->limit(200)->get();
                $headers = ['User', 'Module', 'Action', 'Event', 'IP Address', 'Date'];
                $data = $logs->map(function ($l) {
                    return [
                        'User' => $l->user?->name ?? 'System',
                        'Module' => $l->module,
                        'Action' => $l->action,
                        'Event' => $l->event,
                        'IP Address' => $l->ip_address,
                        'Date' => $l->created_at->toDateTimeString(),
                    ];
                })->toArray();
                break;

            default:
                abort(400, 'Invalid or unsupported report key.');
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'summary' => $summary,
        ];
    }

    /**
     * Authorize report access based on role permission.
     */
    public static function authorizeReport(ReportDefinition $definition, User $user): void
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return;
        }

        $requiredPermissions = $definition->required_permissions ?? [];
        if (!empty($requiredPermissions)) {
            $hasPerm = false;
            foreach ($requiredPermissions as $perm) {
                if ($user->hasPermissionTo($perm)) {
                    $hasPerm = true;
                    break;
                }
            }
            if (!$hasPerm) {
                abort(403, 'Missing required permission to access this report.');
            }
        }

        $allowedRoles = $definition->allowed_roles ?? [];
        if (!empty($allowedRoles)) {
            if (!$user->hasRole($allowedRoles)) {
                abort(403, 'Access denied for this user role.');
            }
        }
    }

    /**
     * Helper to mask contact details in rows.
     */
    public static function maskContacts(array $rows, User $user): array
    {
        $hasFullContactsPermission = $user->hasPermissionTo('view_unmasked_report_contacts') || 
                                     $user->hasPermissionTo('export_member_reports') || 
                                     $user->hasRole(['Super Admin', 'Diocese Admin']);
        if ($hasFullContactsPermission) {
            return $rows;
        }

        return array_map(function ($row) {
            foreach (['Email', 'Phone', 'email', 'phone', 'whatsapp_phone', 'WhatsApp Phone'] as $key) {
                if (isset($row[$key])) {
                    if (str_contains(strtolower($key), 'email')) {
                        $row[$key] = self::maskEmail($row[$key]);
                    } else {
                        $row[$key] = self::maskPhone($row[$key]);
                    }
                }
            }
            return $row;
        }, $rows);
    }

    private static function maskEmail(?string $email): ?string
    {
        if (!$email) return null;
        $parts = explode('@', $email);
        if (count($parts) < 2) return $email;
        $name = $parts[0];
        $domain = $parts[1];
        $maskedName = strlen($name) > 1 ? $name[0] . str_repeat('*', min(3, strlen($name) - 1)) : $name;
        return $maskedName . '@' . $domain;
    }

    private static function maskPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        if (strlen($phone) < 6) return str_repeat('*', strlen($phone));
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }
}
