<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\MemberChangeRequest;
use App\Services\ChurchAccessService;
use App\Services\MemberChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class MemberChangeRequestController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if (Gate::denies('viewAny', MemberChangeRequest::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = MemberChangeRequest::with(['member', 'family', 'church', 'requester', 'reviewer', 'approver']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('change_type')) {
            $query->where('change_type', $request->input('change_type'));
        }

        if ($request->has('member_id')) {
            $query->where('member_id', $request->input('member_id'));
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        $perPage = $request->input('per_page', 50);
        $changeRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($changeRequests, 'Member change requests retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $changeRequest = MemberChangeRequest::with(['member', 'family', 'church', 'requester', 'reviewer', 'approver'])->find($id);

        if (!$changeRequest) {
            return $this->errorResponse('Change request not found', 404);
        }

        if (Gate::denies('view', $changeRequest)) {
            return $this->errorResponse('You do not have access to this change request', 403);
        }

        return $this->successResponse($changeRequest, 'Change request details retrieved successfully');
    }

    public function approve(Request $request, MemberChangeRequestService $service, $id)
    {
        $changeRequest = MemberChangeRequest::find($id);

        if (!$changeRequest) {
            return $this->errorResponse('Change request not found', 404);
        }

        if (Gate::denies('approve', $changeRequest)) {
            return $this->errorResponse('Unauthorized to approve this change request', 403);
        }

        if ($changeRequest->status !== 'submitted') {
            return $this->errorResponse('This request has already been processed', 400);
        }

        $service->approve($changeRequest, $request->user());

        return $this->successResponse($changeRequest, 'Change request approved and applied successfully');
    }

    public function reject(Request $request, MemberChangeRequestService $service, $id)
    {
        $changeRequest = MemberChangeRequest::find($id);

        if (!$changeRequest) {
            return $this->errorResponse('Change request not found', 404);
        }

        if (Gate::denies('reject', $changeRequest)) {
            return $this->errorResponse('Unauthorized to reject this change request', 403);
        }

        if ($changeRequest->status !== 'submitted') {
            return $this->errorResponse('This request has already been processed', 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $reason = $request->input('rejection_reason', 'Changes rejected by Priest/Vicar');
        $service->reject($changeRequest, $request->user(), $reason);

        return $this->successResponse($changeRequest, 'Change request rejected successfully');
    }
}
