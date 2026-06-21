<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Family;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use App\Services\FamilyApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class FamilyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if (Gate::denies('viewAny', Family::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = Family::with(['diocese', 'church', 'country', 'headMember']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('family_name', 'like', "%{$search}%")
                  ->orWhere('family_code', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%")
                  ->orWhere('primary_email', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('membership_status')) {
            $query->where('membership_status', $request->input('membership_status'));
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        $perPage = $request->input('per_page', 50);
        $families = $query->orderBy('family_name')->paginate($perPage);

        return $this->paginatedResponse($families, 'Families retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $family = Family::with(['diocese', 'church', 'country', 'headMember', 'members', 'history.church', 'documents', 'transferRequests.fromChurch', 'transferRequests.toChurch', 'changeRequests.member'])->find($id);

        if (!$family) {
            return $this->errorResponse('Family not found', 404);
        }

        if (Gate::denies('view', $family)) {
            return $this->errorResponse('You do not have access to this family', 403);
        }

        return $this->successResponse($family, 'Family details retrieved successfully');
    }

    public function store(Request $request)
    {
        if (Gate::denies('create', Family::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'required|exists:churches,id',
            'family_name' => 'required|string|max:255',
            'primary_phone' => 'required|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'primary_email' => 'nullable|email|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state_region' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'preferred_language' => 'required|in:en,ml,de',
            'notes' => 'nullable|string',
            'gdpr_consent' => 'required|boolean',
            'communication_consent' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Enforce church scoped access boundary
        if (!ChurchAccessService::canAccessChurch($request->user(), $data['church_id'])) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        $data['membership_status'] = 'pending';
        $data['created_by'] = $request->user()->id;

        if ($data['gdpr_consent']) {
            $data['gdpr_consent_at'] = Carbon::now();
        }

        $family = Family::create($data);

        AuditLogService::log(
            'families',
            'family_created',
            "Family '{$family->family_name}' created in pending approval status",
            null,
            $family->toArray(),
            $family,
            $family->church_id
        );

        return $this->successResponse($family, 'Family created successfully in pending approval status', 201);
    }

    public function update(Request $request, $id)
    {
        $family = Family::find($id);

        if (!$family) {
            return $this->errorResponse('Family not found', 404);
        }

        if (Gate::denies('update', $family)) {
            return $this->errorResponse('You do not have access to this family', 403);
        }

        $validator = Validator::make($request->all(), [
            'family_name' => 'sometimes|required|string|max:255',
            'primary_phone' => 'sometimes|required|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'primary_email' => 'nullable|email|max:255',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state_region' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'preferred_language' => 'sometimes|required|in:en,ml,de',
            'notes' => 'nullable|string',
            'gdpr_consent' => 'sometimes|required|boolean',
            'communication_consent' => 'sometimes|required|boolean',
            'head_member_id' => 'nullable|exists:members,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $family->toArray();
        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;

        if (isset($data['gdpr_consent']) && $data['gdpr_consent'] && !$family->gdpr_consent) {
            $data['gdpr_consent_at'] = Carbon::now();
        }

        $family->update($data);

        AuditLogService::log(
            'families',
            'family_updated',
            "Family '{$family->family_name}' updated successfully",
            $oldValues,
            $family->toArray(),
            $family,
            $family->church_id
        );

        return $this->successResponse($family, 'Family updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $family = Family::find($id);

        if (!$family) {
            return $this->errorResponse('Family not found', 404);
        }

        if (Gate::denies('delete', $family)) {
            return $this->errorResponse('You do not have access to this family', 403);
        }

        $oldValues = $family->toArray();

        // Mark inactive first before soft deleting
        $family->membership_status = 'inactive';
        $family->save();
        $family->delete();

        // Also soft-delete all members in this family
        $family->members()->update(['membership_status' => 'inactive']);
        $family->members()->delete();

        AuditLogService::log(
            'families',
            'family_deactivated',
            "Family '{$family->family_name}' and its members deactivated and soft-deleted successfully",
            $oldValues,
            $family->toArray(),
            $family,
            $family->church_id
        );

        return $this->successResponse([], 'Family and members soft-deleted successfully');
    }

    public function approve(Request $request, FamilyApprovalService $approvalService, $id)
    {
        $family = Family::find($id);
        if (!$family) {
            return $this->errorResponse('Family not found', 404);
        }

        if ($request->user()->cannot('approve', $family)) {
            return $this->errorResponse('Unauthorized to approve this family', 403);
        }

        $code = $approvalService->approve($family, $request->user());

        return $this->successResponse($family, "Family approved successfully. Assigned code: {$code}");
    }

    public function reject(Request $request, FamilyApprovalService $approvalService, $id)
    {
        $family = Family::find($id);
        if (!$family) {
            return $this->errorResponse('Family not found', 404);
        }

        if ($request->user()->cannot('approve', $family)) {
            return $this->errorResponse('Unauthorized to reject this family', 403);
        }

        $approvalService->reject($family, $request->user());

        return $this->successResponse([], 'Family rejected and soft-deleted successfully');
    }
}
