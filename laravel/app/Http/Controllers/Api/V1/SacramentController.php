<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Sacrament;
use App\Services\SacramentRecordService;
use App\Services\ChurchAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;

class SacramentController extends Controller
{
    use ApiResponse;

    protected SacramentRecordService $service;

    public function __construct(SacramentRecordService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = Sacrament::with(['diocese', 'church', 'member', 'officiant', 'spouse']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('member', function ($mq) use ($search) {
                    $mq->where('full_name', 'like', "%{$search}%");
                })
                ->orWhere('place', 'like', "%{$search}%")
                ->orWhere('register_book_number', 'like', "%{$search}%")
                ->orWhere('register_page_number', 'like', "%{$search}%")
                ->orWhere('certificate_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('sacrament_type')) {
            $query->where('sacrament_type', $request->input('sacrament_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 50);
        $sacraments = $query->orderBy('sacrament_date', 'desc')->paginate($perPage);

        return $this->paginatedResponse($sacraments, 'Sacraments retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'required|exists:churches,id',
            'member_id' => 'required|exists:members,id',
            'family_id' => 'nullable|exists:families,id',
            'sacrament_type' => 'required|in:baptism,holy_communion,confirmation,marriage,funeral,other',
            'sacrament_date' => 'required|date',
            'place' => 'required|string|max:255',
            'officiated_by_priest_id' => 'nullable|exists:priest_profiles,id',
            'register_book_number' => 'nullable|string|max:100',
            'register_page_number' => 'nullable|string|max:100',
            'witness_1_name' => 'nullable|string|max:255',
            'witness_2_name' => 'nullable|string|max:255',
            'spouse_member_id' => 'nullable|exists:members,id',
            'spouse_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'document_path' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,submitted',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $churchId = $request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        $sacrament = $this->service->create($request->all());

        return $this->successResponse($sacrament, 'Sacrament record created successfully', 201);
    }

    public function show(Request $request, $id)
    {
        $sacrament = Sacrament::with(['diocese', 'church', 'member', 'officiant', 'spouse'])->findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to view this record', 403);
        }

        return $this->successResponse($sacrament, 'Sacrament record retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to modify this record', 403);
        }

        // Restrict updates if not in draft/rejected state, unless Super/Diocese Admin
        $isAdmin = $request->user()->hasAnyRole(['Super Admin', 'Diocese Admin']);
        if (!$isAdmin && !in_array($sacrament->status, ['draft', 'rejected'])) {
            return $this->errorResponse('Cannot modify a verified or approved sacramental record', 400);
        }

        $validator = Validator::make($request->all(), [
            'sacrament_date' => 'date',
            'place' => 'string|max:255',
            'officiated_by_priest_id' => 'nullable|exists:priest_profiles,id',
            'register_book_number' => 'nullable|string|max:100',
            'register_page_number' => 'nullable|string|max:100',
            'witness_1_name' => 'nullable|string|max:255',
            'witness_2_name' => 'nullable|string|max:255',
            'spouse_member_id' => 'nullable|exists:members,id',
            'spouse_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'document_path' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,submitted',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $sacrament = $this->service->update($sacrament, $request->all());

        return $this->successResponse($sacrament, 'Sacrament record updated successfully');
    }

    public function submit(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to modify this record', 403);
        }

        if ($sacrament->status !== 'draft') {
            return $this->errorResponse('Only draft records can be submitted', 400);
        }

        $sacrament = $this->service->submit($sacrament);

        return $this->successResponse($sacrament, 'Sacrament record submitted for verification');
    }

    public function verify(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to verify this record', 403);
        }

        // Only priest or admins can verify
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to verify sacraments', 403);
        }

        if ($sacrament->status !== 'submitted') {
            return $this->errorResponse('Only submitted records can be verified', 400);
        }

        $sacrament = $this->service->verify($sacrament);

        return $this->successResponse($sacrament, 'Sacrament record verified successfully');
    }

    public function approve(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to approve this record', 403);
        }

        // Only priest or admins can approve
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to approve sacraments', 403);
        }

        if (!in_array($sacrament->status, ['submitted', 'verified'])) {
            return $this->errorResponse('Only submitted or verified records can be approved', 400);
        }

        $sacrament = $this->service->approve($sacrament);

        return $this->successResponse($sacrament, 'Sacrament record approved successfully');
    }

    public function reject(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to reject this record', 403);
        }

        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to reject sacraments', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:3|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $sacrament = $this->service->reject($sacrament, $request->input('reason'));

        return $this->successResponse($sacrament, 'Sacrament record rejected successfully');
    }

    public function archive(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to archive this record', 403);
        }

        if ($sacrament->status !== 'approved') {
            return $this->errorResponse('Only approved records can be archived', 400);
        }

        $sacrament = $this->service->archive($sacrament);

        return $this->successResponse($sacrament, 'Sacrament record archived successfully');
    }

    public function destroy(Request $request, $id)
    {
        $sacrament = Sacrament::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $sacrament->church_id)) {
            return $this->errorResponse('Unauthorized to delete this record', 403);
        }

        // Restrict deletes to Admin / Vicar
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to delete sacramental records', 403);
        }

        $sacrament->delete();

        return $this->successResponse(null, 'Sacrament record soft deleted successfully');
    }
}
