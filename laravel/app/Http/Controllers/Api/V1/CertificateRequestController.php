<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\CertificateRequest;
use App\Services\CertificateRequestService;
use App\Services\CertificateGenerationService;
use App\Services\ChurchAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificateRequestController extends Controller
{
    use ApiResponse;

    protected CertificateRequestService $service;
    protected CertificateGenerationService $generationService;

    public function __construct(
        CertificateRequestService $service,
        CertificateGenerationService $generationService
    ) {
        $this->service = $service;
        $this->generationService = $generationService;
    }

    public function index(Request $request)
    {
        $query = CertificateRequest::with(['diocese', 'church', 'requester', 'family', 'member', 'sacrament', 'certificate']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('member', function ($mq) use ($search) {
                    $mq->where('full_name', 'like', "%{$search}%");
                })
                ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('certificate_type')) {
            $query->where('certificate_type', $request->input('certificate_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 50);
        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($requests, 'Certificate requests retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'required|exists:churches,id',
            'family_id' => 'nullable|exists:families,id',
            'member_id' => 'nullable|exists:members,id',
            'sacrament_id' => 'nullable|exists:sacraments,id',
            'certificate_type' => 'required|in:membership,baptism,marriage,death,recommendation,no_objection,course_completion,custom',
            'purpose' => 'required|string|max:255',
            'request_data' => 'nullable|array',
            'supporting_document_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $churchId = $request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        try {
            $data = $request->all();
            $data['requested_by'] = $request->user()->id;
            
            $certRequest = $this->service->create($data);
            \App\Services\NotificationTriggerService::triggerCertificateRequestSubmitted($certRequest);
            return $this->successResponse($certRequest, 'Certificate request created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function show(Request $request, $id)
    {
        $certRequest = CertificateRequest::with(['diocese', 'church', 'requester', 'family', 'member', 'sacrament', 'certificate'])
            ->findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certRequest->church_id)) {
            return $this->errorResponse('Unauthorized to view this request', 403);
        }

        return $this->successResponse($certRequest, 'Certificate request retrieved successfully');
    }

    public function parishReview(Request $request, $id)
    {
        $certRequest = CertificateRequest::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certRequest->church_id)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        if ($certRequest->status !== 'submitted') {
            return $this->errorResponse('Request must be in submitted state to review', 400);
        }

        $certRequest = $this->service->parishReview($certRequest);

        return $this->successResponse($certRequest, 'Certificate request parish-reviewed successfully');
    }

    public function priestApprove(Request $request, $id)
    {
        $certRequest = CertificateRequest::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certRequest->church_id)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Only Priest or Admin can approve
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to approve requests', 403);
        }

        if (!in_array($certRequest->status, ['submitted', 'parish_review'])) {
            return $this->errorResponse('Request must be in submitted or parish_review state to approve', 400);
        }

        $certRequest = $this->service->priestApprove($certRequest);
        if ($certRequest->status === 'approved') {
            \App\Services\NotificationTriggerService::triggerCertificateRequestApproved($certRequest);
        }
        return $this->successResponse($certRequest, 'Certificate request priest-approved successfully');
    }

    public function dioceseApprove(Request $request, $id)
    {
        $certRequest = CertificateRequest::findOrFail($id);

        // Only Diocese Admin / Super Admin can approve
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to perform diocese approval', 403);
        }

        if ($certRequest->status !== 'diocese_review') {
            return $this->errorResponse('Request must be in diocese_review state for diocese approval', 400);
        }

        $certRequest = $this->service->dioceseApprove($certRequest);
        \App\Services\NotificationTriggerService::triggerCertificateRequestApproved($certRequest);
        return $this->successResponse($certRequest, 'Certificate request diocese-approved successfully');
    }

    public function reject(Request $request, $id)
    {
        $certRequest = CertificateRequest::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certRequest->church_id)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to reject requests', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:3|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $certRequest = $this->service->reject($certRequest, $request->input('reason'));
        \App\Services\NotificationTriggerService::triggerCertificateRequestRejected($certRequest, $request->input('reason'));
        return $this->successResponse($certRequest, 'Certificate request rejected successfully');
    }

    public function issue(Request $request, $id)
    {
        $certRequest = CertificateRequest::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certRequest->church_id)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Restrict issuance to Vicar / Admins
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to issue certificates', 403);
        }

        if ($certRequest->status !== 'approved') {
            return $this->errorResponse('Request must be approved before issuing a certificate', 400);
        }

        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:certificate_templates,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $certificate = $this->generationService->generate($certRequest, $request->input('template_id'));
            \App\Services\NotificationTriggerService::triggerCertificateIssued($certificate);
            return $this->successResponse($certificate, 'Certificate generated and issued successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
