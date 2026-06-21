<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\MemberPortalAccessService;
use App\Services\MemberPortalProfileService;
use App\Services\MemberPortalDocumentService;
use App\Services\ChurchAccessService;
use App\Models\MemberPortalAccess;
use App\Models\ProfileCorrectionRequest;
use App\Models\MemberPortalDocument;
use App\Models\MemberPortalActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class MemberPortalAdminController extends Controller
{
    use ApiResponse;

    public function listAccess(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_member_portal_access')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $query = MemberPortalAccess::with(['diocese', 'church', 'family', 'member', 'user']);

        // Church Scoping
        $user = $request->user();
        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $accessibleIds = ChurchAccessService::getAccessibleChurches($user);
            $query->whereIn('church_id', $accessibleIds);
        }

        return $this->successResponse($query->get(), 'Portal access list retrieved successfully');
    }

    public function inviteAccess(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_member_portal_access')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'access_type' => 'required|string|in:family_head,member,parent_guardian',
            'family_id' => 'nullable|integer|exists:families,id',
            'member_id' => 'nullable|integer|exists:members,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $access = MemberPortalAccessService::invite($validator->validated(), $request->user());
            return $this->successResponse($access, 'Member portal access invitation sent successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function suspendAccess(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('suspend_member_portal_access')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $access = MemberPortalAccess::findOrFail($id);
        
        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $access->church_id)) {
            return $this->errorResponse('Unauthorized to modify access for this parish.', 403);
        }

        try {
            $suspended = MemberPortalAccessService::suspend($access, $request->input('reason'), $request->user());
            return $this->successResponse($suspended, 'Member portal access suspended successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function revokeAccess(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('revoke_member_portal_access')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $access = MemberPortalAccess::findOrFail($id);

        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $access->church_id)) {
            return $this->errorResponse('Unauthorized to modify access for this parish.', 403);
        }

        try {
            $revoked = MemberPortalAccessService::revoke($access, $request->user());
            return $this->successResponse($revoked, 'Member portal access revoked successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listCorrectionRequests(Request $request)
    {
        if (!$request->user()->hasPermissionTo('review_profile_corrections')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $query = ProfileCorrectionRequest::with(['diocese', 'church', 'family', 'member', 'requester'])
            ->orderBy('created_at', 'desc');

        // Scope Check
        $user = $request->user();
        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $accessibleIds = ChurchAccessService::getAccessibleChurches($user);
            $query->whereIn('church_id', $accessibleIds);
        }

        return $this->successResponse($query->get(), 'Profile correction requests retrieved successfully');
    }

    public function approveCorrectionRequest(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('review_profile_corrections')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $correction = ProfileCorrectionRequest::findOrFail($id);

        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $correction->church_id)) {
            return $this->errorResponse('Unauthorized to approve changes in this parish.', 403);
        }

        try {
            $approved = MemberPortalProfileService::approveCorrectionRequest($correction, $request->user());
            return $this->successResponse($approved, 'Correction request approved and whitelisted changes applied successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function rejectCorrectionRequest(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('review_profile_corrections')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $correction = ProfileCorrectionRequest::findOrFail($id);

        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $correction->church_id)) {
            return $this->errorResponse('Unauthorized to reject changes in this parish.', 403);
        }

        try {
            $rejected = MemberPortalProfileService::rejectCorrectionRequest(
                $correction,
                $request->input('reason'),
                $request->user()
            );
            return $this->successResponse($rejected, 'Correction request rejected successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listDocuments(Request $request)
    {
        if (!$request->user()->hasPermissionTo('review_portal_documents')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $query = MemberPortalDocument::with(['diocese', 'church', 'family', 'member', 'uploader'])
            ->orderBy('created_at', 'desc');

        // Scope Check
        $user = $request->user();
        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $accessibleIds = ChurchAccessService::getAccessibleChurches($user);
            $query->whereIn('church_id', $accessibleIds);
        }

        return $this->successResponse($query->get(), 'Portal documents list retrieved successfully');
    }

    public function acceptDocument(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('review_portal_documents')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $doc = MemberPortalDocument::findOrFail($id);

        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $doc->church_id)) {
            return $this->errorResponse('Unauthorized to review document from this parish.', 403);
        }

        try {
            $accepted = MemberPortalDocumentService::accept($doc, $request->user());
            return $this->successResponse($accepted, 'Document accepted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function rejectDocument(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('review_portal_documents')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $doc = MemberPortalDocument::findOrFail($id);

        // Scope Check
        if (!ChurchAccessService::canAccessChurch($request->user(), $doc->church_id)) {
            return $this->errorResponse('Unauthorized to review document from this parish.', 403);
        }

        try {
            $rejected = MemberPortalDocumentService::reject($doc, $request->input('reason'), $request->user());
            return $this->successResponse($rejected, 'Document rejected successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listActivityLogs(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_portal_activity_logs')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $query = MemberPortalActivityLog::with(['diocese', 'church', 'user', 'family', 'member'])
            ->orderBy('created_at', 'desc');

        // Scope Check
        $user = $request->user();
        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $accessibleIds = ChurchAccessService::getAccessibleChurches($user);
            $query->whereIn('church_id', $accessibleIds);
        }

        return $this->successResponse($query->get(), 'Portal activity logs retrieved successfully');
    }
}
