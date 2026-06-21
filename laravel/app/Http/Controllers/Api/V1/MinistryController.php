<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use App\Models\MinistryOfficeBearer;
use App\Models\MinistryActivity;
use App\Models\MinistryActivityAttendance;
use App\Models\MinistryServiceLog;
use App\Models\Member;
use App\Services\MinistryMembershipService;
use App\Services\MinistryOfficeBearerService;
use App\Services\MinistryActivityService;
use App\Services\MinistryAttendanceService;
use App\Services\MinistryServiceLogService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Exception;

class MinistryController extends Controller
{
    use ApiResponse;

    protected MinistryMembershipService $membershipService;
    protected MinistryOfficeBearerService $officeBearerService;
    protected MinistryActivityService $activityService;
    protected MinistryAttendanceService $attendanceService;
    protected MinistryServiceLogService $serviceLogService;

    public function __construct(
        MinistryMembershipService $membershipService,
        MinistryOfficeBearerService $officeBearerService,
        MinistryActivityService $activityService,
        MinistryAttendanceService $attendanceService,
        MinistryServiceLogService $serviceLogService
    ) {
        $this->membershipService = $membershipService;
        $this->officeBearerService = $officeBearerService;
        $this->activityService = $activityService;
        $this->attendanceService = $attendanceService;
        $this->serviceLogService = $serviceLogService;
    }

    // ==========================================
    // Helper: Enforce Scoped Access Control
    // ==========================================
    protected function checkUnitAccess($user, MinistryUnit $unit): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return true;
        }

        // Check coordinator self-management override
        $member = Member::where('user_id', $user->id)->first();
        if ($member && $unit->coordinator_member_id === $member->id) {
            return true;
        }

        // If diocese-level unit, only diocese admins can edit
        if ($unit->unit_level === 'diocese' || $unit->church_id === null) {
            return false;
        }

        // Parish level scoping
        return ChurchAccessService::canAccessChurch($user, $unit->church_id);
    }

    // ==========================================
    // 1. Ministry Organizations
    // ==========================================
    public function listOrganizations(Request $request)
    {
        $orgs = MinistryOrganization::all();
        return $this->successResponse($orgs, 'Organizations list retrieved.');
    }

    public function storeOrganization(Request $request)
    {
        if (!$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:ministry_organizations,slug',
            'organization_type' => 'required|string|in:youth_association,marthamariyam_samajam,other',
            'description' => 'nullable|string',
            'eligibility_rules' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $org = MinistryOrganization::create(array_merge($validator->validated(), [
            'created_by' => $request->user()->id,
        ]));

        return $this->successResponse($org, 'Organization created successfully.', 201);
    }

    public function showOrganization(Request $request, $id)
    {
        $org = MinistryOrganization::findOrFail($id);
        return $this->successResponse($org, 'Organization details retrieved.');
    }

    public function updateOrganization(Request $request, $id)
    {
        if (!$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $org = MinistryOrganization::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'eligibility_rules' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive,archived',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $org->update(array_merge($validator->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return $this->successResponse($org, 'Organization updated successfully.');
    }

    public function archiveOrganization(Request $request, $id)
    {
        if (!$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $org = MinistryOrganization::findOrFail($id);
        $org->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        return $this->successResponse($org, 'Organization archived successfully.');
    }

    // ==========================================
    // 2. Ministry Units
    // ==========================================
    public function listUnits(Request $request)
    {
        $user = $request->user();
        $query = MinistryUnit::with(['organization', 'president', 'coordinator', 'secretary', 'treasurer']);

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            // Apply scoping
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->where(function ($q) use ($accessibleChurchIds, $user) {
                $q->whereIn('church_id', $accessibleChurchIds);
                // Also allow coordinator units access
                $member = Member::where('user_id', $user->id)->first();
                if ($member) {
                    $q->orWhere('coordinator_member_id', $member->id);
                }
            });
        }

        if ($request->has('ministry_organization_id')) {
            $query->where('ministry_organization_id', $request->input('ministry_organization_id'));
        }

        $units = $query->get();
        return $this->successResponse($units, 'Units list retrieved.');
    }

    public function storeUnit(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'ministry_organization_id' => 'required|exists:ministry_organizations,id',
            'unit_name' => 'required|string|max:255',
            'unit_level' => 'required|string|in:diocese,parish',
            'start_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Scope validation
        if ($request->input('unit_level') === 'parish' && $request->filled('church_id')) {
            if (!ChurchAccessService::canAccessChurch($user, $request->input('church_id'))) {
                return $this->errorResponse('Access Denied: Cannot create unit for another parish.', 403);
            }
        } else {
            // Diocese level can only be created by diocese admins
            if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
                return $this->errorResponse('Access Denied: Only Diocese Admin can create central units.', 403);
            }
        }

        $unit = MinistryUnit::create(array_merge($validator->validated(), [
            'created_by' => $user->id,
        ]));

        return $this->successResponse($unit, 'Unit created successfully.', 201);
    }

    public function showUnit(Request $request, $id)
    {
        $unit = MinistryUnit::with(['organization', 'president', 'coordinator', 'secretary', 'treasurer'])->findOrFail($id);
        
        // Read access scoping
        if (!$this->checkUnitAccess($request->user(), $unit) && !ChurchAccessService::hasDioceseAccess($request->user())) {
            // Check if they have general church view access
            if ($unit->church_id && !ChurchAccessService::canAccessChurch($request->user(), $unit->church_id)) {
                return $this->errorResponse('Access Denied: Cannot view this unit.', 403);
            }
        }

        return $this->successResponse($unit, 'Unit details retrieved.');
    }

    public function updateUnit(Request $request, $id)
    {
        $unit = MinistryUnit::findOrFail($id);

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied: You do not have permission to edit this unit.', 403);
        }

        $validator = Validator::make($request->all(), [
            'unit_name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $unit->update(array_merge($validator->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return $this->successResponse($unit, 'Unit updated successfully.');
    }

    public function activateUnit(Request $request, $id)
    {
        $unit = MinistryUnit::findOrFail($id);
        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }
        $unit->update(['status' => 'active', 'updated_by' => $request->user()->id]);
        return $this->successResponse($unit, 'Unit activated.');
    }

    public function archiveUnit(Request $request, $id)
    {
        $unit = MinistryUnit::findOrFail($id);
        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }
        $unit->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        return $this->successResponse($unit, 'Unit archived.');
    }

    // ==========================================
    // 3. Ministry Memberships
    // ==========================================
    public function listMemberships(Request $request)
    {
        $user = $request->user();
        $query = MinistryMembership::with(['member', 'unit.organization']);

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            // Apply scoping
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->where(function ($q) use ($accessibleChurchIds, $user) {
                $q->whereIn('church_id', $accessibleChurchIds);
                // Or coordinator
                $member = Member::where('user_id', $user->id)->first();
                if ($member) {
                    $q->orWhereHas('unit', function ($uq) use ($member) {
                        $uq->where('coordinator_member_id', $member->id);
                    });
                }
            });
        }

        if ($request->has('ministry_unit_id')) {
            $query->where('ministry_unit_id', $request->input('ministry_unit_id'));
        }

        $memberships = $query->get();
        return $this->successResponse($memberships, 'Memberships list retrieved.');
    }

    public function storeMembership(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ministry_unit_id' => 'required|exists:ministry_units,id',
            'member_id' => 'required|exists:members,id',
            'membership_type' => 'nullable|string|in:regular,office_bearer,volunteer,advisor',
            'joined_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'override_eligibility' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $unit = MinistryUnit::findOrFail($request->input('ministry_unit_id'));

        // Scoping check
        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied: You do not have access to manage this unit.', 403);
        }

        try {
            $membership = $this->membershipService->enroll($request->all(), $request->user());
            return $this->successResponse($membership, 'Member enrolled successfully as pending.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function approveMembership(Request $request, $id)
    {
        $membership = MinistryMembership::findOrFail($id);
        $unit = $membership->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $approved = $this->membershipService->approve($id, $request->user());
            return $this->successResponse($approved, 'Membership approved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function rejectMembership(Request $request, $id)
    {
        $membership = MinistryMembership::findOrFail($id);
        $unit = $membership->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $rejected = $this->membershipService->reject($id, $request->user(), $request->input('remarks'));
            return $this->successResponse($rejected, 'Membership rejected.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function archiveMembership(Request $request, $id)
    {
        $membership = MinistryMembership::findOrFail($id);
        $unit = $membership->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        $membership->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        return $this->successResponse($membership, 'Membership archived.');
    }

    // ==========================================
    // 4. Ministry Office Bearers
    // ==========================================
    public function listOfficeBearers(Request $request)
    {
        $user = $request->user();
        $query = MinistryOfficeBearer::with(['member', 'priest', 'unit']);

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->whereHas('unit', function ($q) use ($accessibleChurchIds, $user) {
                $q->whereIn('church_id', $accessibleChurchIds);
                $member = Member::where('user_id', $user->id)->first();
                if ($member) {
                    $q->orWhere('coordinator_member_id', $member->id);
                }
            });
        }

        if ($request->has('ministry_unit_id')) {
            $query->where('ministry_unit_id', $request->input('ministry_unit_id'));
        }

        $bearers = $query->orderBy('sort_order')->get();
        return $this->successResponse($bearers, 'Office bearers retrieved.');
    }

    public function storeOfficeBearer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ministry_unit_id' => 'required|exists:ministry_units,id',
            'member_id' => 'nullable|exists:members,id',
            'priest_id' => 'nullable|exists:priest_profiles,id',
            'external_name' => 'nullable|string',
            'role_title' => 'required|string|max:255',
            'role_category' => 'required|string',
            'start_date' => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $unit = MinistryUnit::findOrFail($request->input('ministry_unit_id'));

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $bearer = $this->officeBearerService->assign($request->all(), $request->user());
            return $this->successResponse($bearer, 'Office bearer assigned successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function endOfficeBearerTerm(Request $request, $id)
    {
        $bearer = MinistryOfficeBearer::findOrFail($id);
        $unit = $bearer->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $ended = $this->officeBearerService->endTerm($id, $request->user(), $request->input('end_date'));
            return $this->successResponse($ended, 'Office bearer term ended.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ==========================================
    // 5. Ministry Activities
    // ==========================================
    public function listActivities(Request $request)
    {
        $user = $request->user();
        $query = MinistryActivity::with(['unit.organization']);

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->where(function ($q) use ($accessibleChurchIds, $user) {
                $q->whereIn('church_id', $accessibleChurchIds);
                $member = Member::where('user_id', $user->id)->first();
                if ($member) {
                    $q->orWhereHas('unit', function ($uq) use ($member) {
                        $uq->where('coordinator_member_id', $member->id);
                    });
                }
            });
        }

        if ($request->has('ministry_unit_id')) {
            $query->where('ministry_unit_id', $request->input('ministry_unit_id'));
        }

        $activities = $query->get();
        return $this->successResponse($activities, 'Activities list retrieved.');
    }

    public function storeActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ministry_unit_id' => 'required|exists:ministry_units,id',
            'title' => 'required|string|max:255',
            'activity_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'timezone' => 'nullable|string',
            'location_name' => 'nullable|string',
            'mode' => 'nullable|string|in:online,offline,hybrid',
            'meeting_link' => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $unit = MinistryUnit::findOrFail($request->input('ministry_unit_id'));

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $activity = $this->activityService->create($request->all(), $request->user());
            return $this->successResponse($activity, 'Activity created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function publishActivity(Request $request, $id)
    {
        $activity = MinistryActivity::findOrFail($id);
        if (!$this->checkUnitAccess($request->user(), $activity->unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $published = $this->activityService->publish($id, $request->user());
            return $this->successResponse($published, 'Activity published.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function completeActivity(Request $request, $id)
    {
        $activity = MinistryActivity::findOrFail($id);
        if (!$this->checkUnitAccess($request->user(), $activity->unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $completed = $this->activityService->complete($id, $request->user());
            return $this->successResponse($completed, 'Activity completed.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function cancelActivity(Request $request, $id)
    {
        $activity = MinistryActivity::findOrFail($id);
        if (!$this->checkUnitAccess($request->user(), $activity->unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $cancelled = $this->activityService->cancel($id, $request->user());
            return $this->successResponse($cancelled, 'Activity cancelled.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ==========================================
    // 6. Attendance Routes
    // ==========================================
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ministry_activity_id' => 'required|exists:ministry_activities,id',
            'records' => 'required|array|min:1',
            'records.*.member_id' => 'nullable|exists:members,id',
            'records.*.ministry_membership_id' => 'nullable|exists:ministry_memberships,id',
            'records.*.status' => 'required|string|in:present,absent,late,excused',
            'records.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $activity = MinistryActivity::findOrFail($request->input('ministry_activity_id'));

        if (!$this->checkUnitAccess($request->user(), $activity->unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $results = $this->attendanceService->markAttendance(
                $request->input('ministry_activity_id'),
                $request->input('records'),
                $request->user()
            );
            return $this->successResponse($results, 'Attendance marked successfully.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function activityAttendance(Request $request, $id)
    {
        $activity = MinistryActivity::findOrFail($id);
        
        if (!$this->checkUnitAccess($request->user(), $activity->unit) && !ChurchAccessService::hasDioceseAccess($request->user())) {
            return $this->errorResponse('Access Denied', 403);
        }

        $records = MinistryActivityAttendance::where('ministry_activity_id', $activity->id)
            ->with(['member', 'membership'])
            ->get();

        return $this->successResponse($records, 'Attendance records retrieved.');
    }

    // ==========================================
    // 7. Service Logs
    // ==========================================
    public function listServiceLogs(Request $request)
    {
        $user = $request->user();
        $query = MinistryServiceLog::with(['member', 'unit', 'activity']);

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->where(function ($q) use ($accessibleChurchIds, $user) {
                $q->whereIn('church_id', $accessibleChurchIds);
                $member = Member::where('user_id', $user->id)->first();
                if ($member) {
                    $q->orWhereHas('unit', function ($uq) use ($member) {
                        $uq->where('coordinator_member_id', $member->id);
                    });
                }
            });
        }

        if ($request->has('ministry_unit_id')) {
            $query->where('ministry_unit_id', $request->input('ministry_unit_id'));
        }

        $logs = $query->get();
        return $this->successResponse($logs, 'Service logs retrieved.');
    }

    public function storeServiceLog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ministry_unit_id' => 'required|exists:ministry_units,id',
            'member_id' => 'required|exists:members,id',
            'activity_id' => 'nullable|exists:ministry_activities,id',
            'service_type' => 'required|string',
            'service_date' => 'required|date',
            'hours_count' => 'nullable|numeric|min:0.5',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $unit = MinistryUnit::findOrFail($request->input('ministry_unit_id'));

        // Scoping check (submitting member can log hours, or coordinators/admins can)
        $member = Member::where('user_id', $request->user()->id)->first();
        $isSelfSubmit = $member && (int)$request->input('member_id') === $member->id;

        if (!$isSelfSubmit && !$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $log = $this->serviceLogService->submit($request->all(), $request->user());
            return $this->successResponse($log, 'Service log submitted.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function verifyServiceLog(Request $request, $id)
    {
        $log = MinistryServiceLog::findOrFail($id);
        $unit = $log->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied: You do not have permission to verify logs in this unit.', 403);
        }

        try {
            $verified = $this->serviceLogService->verify($id, $request->user());
            return $this->successResponse($verified, 'Service log verified.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function rejectServiceLog(Request $request, $id)
    {
        $log = MinistryServiceLog::findOrFail($id);
        $unit = $log->unit;

        if (!$this->checkUnitAccess($request->user(), $unit)) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $rejected = $this->serviceLogService->reject($id, $request->user(), $request->input('remarks'));
            return $this->successResponse($rejected, 'Service log rejected.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ==========================================
    // 8. Reports & Dashboard Stats
    // ==========================================
    public function reportsOverview(Request $request)
    {
        $user = $request->user();
        
        $unitsQuery = MinistryUnit::query();
        $membersQuery = MinistryMembership::where('status', 'active');
        $activitiesQuery = MinistryActivity::where('status', 'completed');
        $serviceQuery = MinistryServiceLog::where('status', 'verified');

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            
            $unitsQuery->whereIn('church_id', $accessibleChurchIds);
            $membersQuery->whereIn('church_id', $accessibleChurchIds);
            $activitiesQuery->whereIn('church_id', $accessibleChurchIds);
            $serviceQuery->whereIn('church_id', $accessibleChurchIds);
        }

        // Split members by organization type
        $youthMembersCount = (clone $membersQuery)->whereHas('unit.organization', function($q){
            $q->where('organization_type', 'youth_association');
        })->count();

        $samajamMembersCount = (clone $membersQuery)->whereHas('unit.organization', function($q){
            $q->where('organization_type', 'marthamariyam_samajam');
        })->count();

        return $this->successResponse([
            'total_active_units' => $unitsQuery->where('status', 'active')->count(),
            'total_youth_members' => $youthMembersCount,
            'total_marthamariyam_members' => $samajamMembersCount,
            'completed_activities_count' => $activitiesQuery->count(),
            'verified_service_hours' => $serviceQuery->sum('hours_count'),
        ], 'Ministry reports overview retrieved.');
    }

    public function reportsByChurch(Request $request)
    {
        $user = $request->user();
        $query = DB::table('ministry_units')
            ->join('churches', 'ministry_units.church_id', '=', 'churches.id')
            ->leftJoin('ministry_memberships', function ($join) {
                $join->on('ministry_units.id', '=', 'ministry_memberships.ministry_unit_id')
                     ->where('ministry_memberships.status', '=', 'active');
            })
            ->select('churches.name as church_name', DB::raw('count(distinct ministry_units.id) as units_count'), DB::raw('count(distinct ministry_memberships.id) as active_members_count'))
            ->groupBy('churches.name');

        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->whereIn('ministry_units.church_id', $accessibleChurchIds);
        }

        return $this->successResponse($query->get(), 'Ministry stats by church.');
    }
}
