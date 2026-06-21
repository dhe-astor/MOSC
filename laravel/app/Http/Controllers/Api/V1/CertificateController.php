<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Certificate;
use App\Services\CertificateVerificationService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    use ApiResponse;

    protected CertificateVerificationService $verificationService;

    public function __construct(CertificateVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function index(Request $request)
    {
        $query = Certificate::with(['diocese', 'church', 'member', 'family', 'sacrament', 'template', 'issuer']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                  ->orWhere('verification_code', 'like', "%{$search}%");
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 50);
        $certificates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($certificates, 'Certificates retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $certificate = Certificate::with(['diocese', 'church', 'member', 'family', 'sacrament', 'template', 'issuer'])
            ->findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certificate->church_id)) {
            return $this->errorResponse('Unauthorized to view this certificate', 403);
        }

        return $this->successResponse($certificate, 'Certificate retrieved successfully');
    }

    public function download(Request $request, $id)
    {
        $certificate = Certificate::findOrFail($id);

        if (!ChurchAccessService::canAccessChurch($request->user(), $certificate->church_id)) {
            return $this->errorResponse('Unauthorized to download this certificate', 403);
        }

        if (!Storage::exists($certificate->pdf_path)) {
            return $this->errorResponse('Certificate PDF file not found', 404);
        }

        AuditLogService::log(
            'certificates',
            'certificate_downloaded',
            "Certificate {$certificate->certificate_number} downloaded by user ID: {$request->user()->id}",
            null,
            null,
            $certificate,
            $certificate->church_id,
            $certificate->diocese_id
        );

        return Storage::download($certificate->pdf_path, "{$certificate->certificate_number}.pdf");
    }

    public function cancel(Request $request, $id)
    {
        // Only Diocese Admin or Super Admin can cancel certificates
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to cancel certificates', 403);
        }

        $certificate = Certificate::findOrFail($id);
        
        if ($certificate->status === 'cancelled') {
            return $this->errorResponse('Certificate is already cancelled', 400);
        }

        $oldValues = $certificate->toArray();
        $certificate->update(['status' => 'cancelled']);

        AuditLogService::log(
            'certificates',
            'certificate_cancelled',
            "Certificate {$certificate->certificate_number} cancelled by admin",
            $oldValues,
            $certificate->toArray(),
            $certificate,
            $certificate->church_id,
            $certificate->diocese_id
        );

        return $this->successResponse($certificate, 'Certificate cancelled successfully');
    }

    public function verify(Request $request, $code)
    {
        $certificate = $this->verificationService->verify($code);

        if (!$certificate) {
            return $this->errorResponse('Certificate not found or verification code is invalid', 404);
        }

        if (!$certificate->public_verification_enabled) {
            return $this->errorResponse('Public verification is disabled for this certificate', 400);
        }

        AuditLogService::log(
            'certificates',
            'public_verification_attempted',
            "Public verification attempted for code: {$code} (Found: true)",
            null,
            null,
            $certificate,
            $certificate->church_id,
            $certificate->diocese_id
        );

        // Privacy rule: Only return safe non-sensitive metadata
        return $this->successResponse([
            'certificate_number' => $certificate->certificate_number,
            'certificate_type' => $certificate->certificate_type,
            'issued_date' => $certificate->issued_date->toDateString(),
            'issuing_church' => $certificate->church->name,
            'status' => $certificate->status,
        ], 'Certificate verification status retrieved successfully');
    }
}
