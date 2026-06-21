<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Models\Family;
use App\Models\Member;
use App\Models\Sacrament;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class CertificateRequestService
{
    public function create(array $data): CertificateRequest
    {
        $user = Auth::user();
        $data['created_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'submitted';

        // 1. Verify active status of member if specified
        if (!empty($data['member_id'])) {
            $member = Member::find($data['member_id']);
            if (!$member || $member->membership_status !== 'active') {
                throw new \InvalidArgumentException("Certificate request can be created only for active members.");
            }
        }

        // 2. Verify active status of family if specified
        if (!empty($data['family_id'])) {
            $family = Family::find($data['family_id']);
            if (!$family || $family->membership_status !== 'active') {
                throw new \InvalidArgumentException("Certificate request can be created only for active families.");
            }
        }

        // 3. Verify sacrament record if specified
        if (!empty($data['sacrament_id'])) {
            $sacrament = Sacrament::find($data['sacrament_id']);
            if (!$sacrament || $sacrament->status !== 'approved') {
                throw new \InvalidArgumentException("Sacramental records must be approved before certificates can be requested.");
            }
        }

        // 4. Sacramental type validation (baptism, marriage, death)
        $type = $data['certificate_type'] ?? '';
        if (in_array($type, ['baptism', 'marriage', 'death'])) {
            if (empty($data['sacrament_id'])) {
                // Must be an admin/staff with supporting document
                $isStaff = $user->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar', 'Parish Admin', 'Parish Secretary']);
                if (!$isStaff || empty($data['supporting_document_path'])) {
                    throw new \InvalidArgumentException("Sacramental certificates (baptism, marriage, death) must link to an approved sacrament record, or be requested by an authorized parish/diocese admin with a supporting document.");
                }
            }
        }

        $request = CertificateRequest::create($data);

        AuditLogService::log(
            'certificates',
            'certificate_request_created',
            "Certificate request of type {$request->certificate_type} created",
            null,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }

    public function parishReview(CertificateRequest $request): CertificateRequest
    {
        $user = Auth::user();
        $oldValues = $request->toArray();
        $request->update([
            'status' => 'parish_review',
            'parish_reviewed_by' => $user->id,
            'parish_reviewed_at' => now(),
        ]);

        AuditLogService::log(
            'certificates',
            'certificate_request_parish_reviewed',
            "Certificate request ID: {$request->id} parish-reviewed",
            $oldValues,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }

    public function priestApprove(CertificateRequest $request): CertificateRequest
    {
        $user = Auth::user();
        $oldValues = $request->toArray();

        // Check if diocese approval is required
        $dioceseReq = config("settings.certificate_diocese_approval_required.{$request->certificate_type}", false);
        $nextStatus = $dioceseReq ? 'diocese_review' : 'approved';

        $request->update([
            'status' => $nextStatus,
            'priest_approved_by' => $user->id,
            'priest_approved_at' => now(),
        ]);

        AuditLogService::log(
            'certificates',
            'certificate_request_priest_approved',
            "Certificate request ID: {$request->id} approved by priest (Next status: {$nextStatus})",
            $oldValues,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }

    public function dioceseApprove(CertificateRequest $request): CertificateRequest
    {
        $user = Auth::user();
        $oldValues = $request->toArray();
        $request->update([
            'status' => 'approved',
            'diocese_approved_by' => $user->id,
            'diocese_approved_at' => now(),
        ]);

        AuditLogService::log(
            'certificates',
            'certificate_request_diocese_approved',
            "Certificate request ID: {$request->id} approved by diocese admin",
            $oldValues,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }

    public function reject(CertificateRequest $request, string $reason): CertificateRequest
    {
        $oldValues = $request->toArray();
        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        AuditLogService::log(
            'certificates',
            'certificate_request_rejected',
            "Certificate request ID: {$request->id} rejected. Reason: {$reason}",
            $oldValues,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }

    public function cancel(CertificateRequest $request): CertificateRequest
    {
        $oldValues = $request->toArray();
        $request->update(['status' => 'cancelled']);

        AuditLogService::log(
            'certificates',
            'certificate_request_cancelled',
            "Certificate request ID: {$request->id} cancelled",
            $oldValues,
            $request->toArray(),
            $request,
            $request->church_id,
            $request->diocese_id
        );

        return $request;
    }
}
