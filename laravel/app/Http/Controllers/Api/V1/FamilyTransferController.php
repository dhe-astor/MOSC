<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\FamilyTransferRequest;
use App\Models\Family;
use App\Services\ChurchAccessService;
use App\Services\FamilyTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class FamilyTransferController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if (Gate::denies('viewAny', FamilyTransferRequest::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $user = $request->user();
        $query = FamilyTransferRequest::with(['family', 'fromChurch', 'toChurch', 'requester', 'sourceApprover', 'dioceseApprover', 'targetAccepter']);

        // Manual scoping to allow viewing requests involving either 'from' or 'to' accessible churches
        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
            if (empty($accessibleIds)) {
                return $this->successResponse([], 'No transfer requests found');
            }
            $query->where(function($q) use ($accessibleIds) {
                $q->whereIn('from_church_id', $accessibleIds)
                  ->orWhereIn('to_church_id', $accessibleIds);
            });
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        $perPage = $request->input('per_page', 50);
        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($transfers, 'Family transfer requests retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $transfer = FamilyTransferRequest::with(['family', 'fromChurch', 'toChurch', 'requester', 'sourceApprover', 'dioceseApprover', 'targetAccepter'])->find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        if (Gate::denies('view', $transfer)) {
            return $this->errorResponse('You do not have access to this transfer request', 403);
        }

        return $this->successResponse($transfer, 'Transfer request details retrieved successfully');
    }

    public function store(Request $request, FamilyTransferService $service)
    {
        if (Gate::denies('create', FamilyTransferRequest::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'family_id' => 'required|exists:families,id',
            'to_church_id' => 'required|exists:churches,id',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $family = Family::findOrFail($request->input('family_id'));

        // Verify that user has access to the family's current parish to initiate transfer
        if (!ChurchAccessService::canAccessChurch($request->user(), $family->church_id)) {
            return $this->errorResponse('You do not have access to this family\'s parish', 403);
        }

        try {
            $transfer = $service->createRequest(
                $family,
                $request->input('to_church_id'),
                $request->user(),
                $request->input('reason')
            );

            return $this->successResponse($transfer, 'Family transfer request submitted successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function sourceApprove(Request $request, FamilyTransferService $service, $id)
    {
        $transfer = FamilyTransferRequest::find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        if (Gate::denies('sourceApprove', $transfer)) {
            return $this->errorResponse('Unauthorized to approve this transfer from the source parish', 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $service->sourceApprove($transfer, $request->user(), $request->input('remarks'));
            return $this->successResponse($transfer, 'Transfer request approved by source parish');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function dioceseApprove(Request $request, FamilyTransferService $service, $id)
    {
        $transfer = FamilyTransferRequest::find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        if (Gate::denies('dioceseApprove', $transfer)) {
            return $this->errorResponse('Unauthorized to approve this transfer at the diocese level', 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $service->dioceseApprove($transfer, $request->user(), $request->input('remarks'));
            return $this->successResponse($transfer, 'Transfer request approved by diocese');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function targetAccept(Request $request, FamilyTransferService $service, $id)
    {
        $transfer = FamilyTransferRequest::find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        if (Gate::denies('targetAccept', $transfer)) {
            return $this->errorResponse('Unauthorized to accept this transfer at the target parish', 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $service->targetAccept($transfer, $request->user(), $request->input('remarks'));
            return $this->successResponse($transfer, 'Transfer request accepted by target parish');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function complete(Request $request, FamilyTransferService $service, $id)
    {
        $transfer = FamilyTransferRequest::find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        if (Gate::denies('complete', $transfer)) {
            return $this->errorResponse('Unauthorized to complete this transfer request', 403);
        }

        try {
            $service->complete($transfer, $request->user());
            return $this->successResponse($transfer, 'Transfer completed and family records updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function reject(Request $request, FamilyTransferService $service, $id)
    {
        $transfer = FamilyTransferRequest::find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer request not found', 404);
        }

        // Rejection can be done by source priest, diocese admin, or target parish
        $canReject = Gate::allows('sourceApprove', $transfer) || Gate::allows('dioceseApprove', $transfer) || Gate::allows('targetAccept', $transfer);
        if (!$canReject) {
            return $this->errorResponse('Unauthorized to reject this transfer request', 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $service->reject($transfer, $request->user(), $request->input('rejection_reason'));
            return $this->successResponse($transfer, 'Transfer request rejected successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
