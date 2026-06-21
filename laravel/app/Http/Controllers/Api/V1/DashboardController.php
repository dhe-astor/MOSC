<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Church;
use App\Models\Priest;
use App\Models\PriestAssignment;
use App\Models\User;
use App\Models\UserChurchAccess;
use App\Models\AuditLog;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberChangeRequest;
use App\Models\FamilyTransferRequest;
use App\Services\ChurchAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->input('type');

        if (!$type) {
            // Auto-detect dashboard view
            if (ChurchAccessService::hasDioceseAccess($user)) {
                $type = 'diocese';
            } elseif ($user->priest()->exists()) {
                $type = 'priest';
            } else {
                $type = 'parish';
            }
        }

        if ($type === 'diocese') {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized for diocese dashboard', 403);
            }
            return $this->getDioceseDashboard($user);
        }

        if ($type === 'priest') {
            $user->loadMissing('priest');
            $priest = $user->priest;
            if (!$priest) {
                return $this->errorResponse('Priest profile not found', 404);
            }
            return $this->getPriestDashboard($priest);
        }

        // Parish Dashboard
        $churchId = $request->input('church_id', $user->active_church_id ?? $user->default_church_id);
        if (!$churchId) {
            // Try to find first accessible church
            $accessible = ChurchAccessService::getAccessibleChurchIds($user);
            if (!empty($accessible)) {
                $churchId = $accessible[0];
            } else {
                return $this->errorResponse('No active church selected and no accessible churches found', 400);
            }
        }

        if (!ChurchAccessService::canAccessChurch($user, $churchId)) {
            return $this->errorResponse('You do not have access to this church dashboard', 403);
        }

        $church = Church::find($churchId);
        if (!$church) {
            return $this->errorResponse('Church not found', 404);
        }

        return $this->getParishDashboard($church);
    }

    protected function getDioceseDashboard(User $user)
    {
        $totalChurches = Church::count();
        $activeChurches = Church::where('canonical_status', 'active')->count();
        $totalPriests = Priest::count();
        $activeAssignments = PriestAssignment::where('status', 'active')->count();

        // Churches without assigned priest (no active assignment)
        $churchesWithPriest = PriestAssignment::where('status', 'active')->pluck('church_id')->unique()->toArray();
        $churchesWithoutPriest = Church::whereNotIn('id', $churchesWithPriest)->count();

        $totalUsers = User::count();
        
        // Pending access setup (active users with no user_church_access and not superadmin)
        $usersWithAccess = UserChurchAccess::pluck('user_id')->unique()->toArray();
        $pendingAccess = User::where('is_active', true)
            ->whereNotIn('id', $usersWithAccess)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'Super Admin');
            })
            ->count();

        $recentAudits = AuditLog::with(['user', 'church'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $totalFamilies = Family::count();
        $totalMembers = Member::count();
        $pendingFamilies = Family::where('membership_status', 'pending')->count();
        $pendingMembers = Member::where('membership_status', 'pending')->count();
        $pendingChangeRequests = MemberChangeRequest::whereIn('status', ['submitted', 'parish_review', 'priest_review'])->count();
        $pendingTransfers = FamilyTransferRequest::whereIn('status', ['requested', 'source_approved', 'diocese_approved', 'target_accepted'])->count();

        // Phase 3 stats
        $pendingSacraments = \App\Models\Sacrament::whereIn('status', ['submitted', 'verified'])->count();
        $pendingCertificateRequests = \App\Models\CertificateRequest::whereIn('status', ['submitted', 'parish_review', 'priest_review', 'diocese_review'])->count();
        $issuedCertificates = \App\Models\Certificate::where('status', 'active')->count();

        return $this->successResponse([
            'total_churches' => $totalChurches,
            'active_churches' => $activeChurches,
            'total_priests' => $totalPriests,
            'active_assignments' => $activeAssignments,
            'churches_without_priest' => $churchesWithoutPriest,
            'total_users' => $totalUsers,
            'pending_access_setup' => $pendingAccess,
            'recent_audit_logs' => $recentAudits,
            'total_families' => $totalFamilies,
            'total_members' => $totalMembers,
            'pending_families_count' => $pendingFamilies,
            'pending_members_count' => $pendingMembers,
            'pending_change_requests_count' => $pendingChangeRequests,
            'pending_transfers_count' => $pendingTransfers,
            'pending_sacraments_count' => $pendingSacraments,
            'pending_certificate_requests_count' => $pendingCertificateRequests,
            'issued_certificates_count' => $issuedCertificates,
        ], 'Diocese dashboard stats retrieved successfully');
    }

    protected function getPriestDashboard(Priest $priest)
    {
        $assignments = PriestAssignment::with('church')
            ->where('priest_id', $priest->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('assignment_start_date', 'desc')
            ->get();

        $activeAssignments = $assignments->where('status', 'active');
        $primaryAssignment = $activeAssignments->where('is_primary', true)->first();

        // Calculate profile completion percentage
        $fields = ['baptism_name', 'ordination_date', 'date_of_birth', 'whatsapp_phone', 'email', 'address', 'city', 'country', 'photo_path', 'biography'];
        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($priest->$field)) {
                $filled++;
            }
        }
        $completion = round((($filled + 3) / (count($fields) + 3)) * 100); // add 3 for required fields (full_name, primary_phone, rank)

        $assignedChurchIds = $activeAssignments->pluck('church_id')->toArray();

        $totalFamilies = Family::whereIn('church_id', $assignedChurchIds)->count();
        $totalMembers = Member::whereIn('church_id', $assignedChurchIds)->count();
        $pendingFamilies = Family::whereIn('church_id', $assignedChurchIds)->where('membership_status', 'pending')->count();
        $pendingMembers = Member::whereIn('church_id', $assignedChurchIds)->where('membership_status', 'pending')->count();
        $pendingChangeRequests = MemberChangeRequest::whereIn('church_id', $assignedChurchIds)->whereIn('status', ['submitted', 'parish_review', 'priest_review'])->count();
        $pendingTransfers = FamilyTransferRequest::where(function($q) use ($assignedChurchIds) {
                $q->whereIn('from_church_id', $assignedChurchIds)
                  ->orWhereIn('to_church_id', $assignedChurchIds);
            })
            ->whereIn('status', ['requested', 'source_approved', 'diocese_approved', 'target_accepted'])
            ->count();

        // Phase 3 stats
        $pendingSacraments = \App\Models\Sacrament::whereIn('church_id', $assignedChurchIds)->whereIn('status', ['submitted', 'verified'])->count();
        $pendingCertificateRequests = \App\Models\CertificateRequest::whereIn('church_id', $assignedChurchIds)->whereIn('status', ['submitted', 'parish_review', 'priest_review', 'diocese_review'])->count();
        $issuedCertificates = \App\Models\Certificate::whereIn('church_id', $assignedChurchIds)->where('status', 'active')->count();

        return $this->successResponse([
            'priest' => [
                'id' => $priest->id,
                'full_name' => $priest->full_name,
                'title' => $priest->title,
                'clergy_rank' => $priest->clergy_rank,
                'email' => $priest->email,
                'primary_phone' => $priest->primary_phone
            ],
            'profile_completeness' => $completion,
            'primary_church' => $primaryAssignment ? [
                'id' => $primaryAssignment->church->id,
                'name' => $primaryAssignment->church->name,
                'short_name' => $primaryAssignment->church->short_name
            ] : null,
            'assigned_churches' => $activeAssignments->map(function ($a) {
                return [
                    'id' => $a->church->id,
                    'name' => $a->church->name,
                    'short_name' => $a->church->short_name,
                    'role' => $a->role,
                    'is_primary' => $a->is_primary
                ];
            })->values(),
            'assignment_history' => $assignments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'church_name' => $a->church->name,
                    'role' => $a->role,
                    'start_date' => $a->assignment_start_date->toDateString(),
                    'end_date' => $a->assignment_end_date ? $a->assignment_end_date->toDateString() : null,
                    'is_primary' => $a->is_primary,
                    'status' => $a->status
                ];
            })->values(),
            'total_families' => $totalFamilies,
            'total_members' => $totalMembers,
            'pending_families_count' => $pendingFamilies,
            'pending_members_count' => $pendingMembers,
            'pending_change_requests_count' => $pendingChangeRequests,
            'pending_transfers_count' => $pendingTransfers,
            'pending_sacraments_count' => $pendingSacraments,
            'pending_certificate_requests_count' => $pendingCertificateRequests,
            'issued_certificates_count' => $issuedCertificates,
        ], 'Priest dashboard stats retrieved successfully');
    }

    protected function getParishDashboard(Church $church)
    {
        // Profile completeness (based on filled fields)
        $fields = ['patron_saint', 'state_region', 'address_line_1', 'address_line_2', 'postal_code', 'latitude', 'longitude', 'public_email', 'public_phone', 'website_url', 'established_date', 'description', 'history', 'qurbana_timing'];
        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($church->$field)) {
                $filled++;
            }
        }
        $completion = round((($filled + 5) / (count($fields) + 5)) * 100); // add 5 for required fields (name, short_name, city, country, type)

        // Current vicar
        $primaryVicarAssignment = PriestAssignment::with('priest')
            ->where('church_id', $church->id)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->first();

        $currentPriest = $primaryVicarAssignment ? [
            'id' => $primaryVicarAssignment->priest->id,
            'full_name' => $primaryVicarAssignment->priest->full_name,
            'title' => $primaryVicarAssignment->priest->title,
            'phone' => $primaryVicarAssignment->priest->primary_phone,
            'email' => $primaryVicarAssignment->priest->email,
        ] : null;

        // Active users for this church
        $activeUsersCount = UserChurchAccess::where('church_id', $church->id)
            ->where('status', 'active')
            ->count();

        // Recent audits for this church
        $recentAudits = AuditLog::with('user')
            ->where('church_id', $church->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $totalFamilies = Family::where('church_id', $church->id)->count();
        $totalMembers = Member::where('church_id', $church->id)->count();
        $pendingFamilies = Family::where('church_id', $church->id)->where('membership_status', 'pending')->count();
        $pendingMembers = Member::where('church_id', $church->id)->where('membership_status', 'pending')->count();
        $pendingChangeRequests = MemberChangeRequest::where('church_id', $church->id)->whereIn('status', ['submitted', 'parish_review', 'priest_review'])->count();
        $pendingTransfers = FamilyTransferRequest::where(function($q) use ($church) {
                $q->where('from_church_id', $church->id)
                  ->orWhere('to_church_id', $church->id);
            })
            ->whereIn('status', ['requested', 'source_approved', 'diocese_approved', 'target_accepted'])
            ->count();

        // Phase 3 stats
        $pendingSacraments = \App\Models\Sacrament::where('church_id', $church->id)->whereIn('status', ['submitted', 'verified'])->count();
        $pendingCertificateRequests = \App\Models\CertificateRequest::where('church_id', $church->id)->whereIn('status', ['submitted', 'parish_review', 'priest_review', 'diocese_review'])->count();
        $issuedCertificates = \App\Models\Certificate::where('church_id', $church->id)->where('status', 'active')->count();

        return $this->successResponse([
            'church' => [
                'id' => $church->id,
                'name' => $church->name,
                'short_name' => $church->short_name,
                'canonical_status' => $church->canonical_status
            ],
            'profile_completeness' => $completion,
            'current_priest' => $currentPriest,
            'active_users_count' => $activeUsersCount,
            'recent_changes' => $recentAudits,
            'total_families' => $totalFamilies,
            'total_members' => $totalMembers,
            'pending_families_count' => $pendingFamilies,
            'pending_members_count' => $pendingMembers,
            'pending_change_requests_count' => $pendingChangeRequests,
            'pending_transfers_count' => $pendingTransfers,
            'pending_sacraments_count' => $pendingSacraments,
            'pending_certificate_requests_count' => $pendingCertificateRequests,
            'issued_certificates_count' => $issuedCertificates,
        ], 'Parish dashboard stats retrieved successfully');
    }
}
