<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\MemberPortalActivityLog;
use App\Models\Member;
use App\Models\Family;
use Illuminate\Support\Facades\Storage;
use App\Services\CertificateRequestService;
use Exception;

class MemberPortalCertificateService
{
    public static function listRequests($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        return CertificateRequest::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', $familyIds)
            ->with(['member', 'family'])
            ->get();
    }

    public static function getRequest($id, $user)
    {
        if (!MemberPortalSecurity::validateCertificateRequestAccess($user, $id)) {
            throw new Exception("Access Denied to this certificate request.");
        }

        return CertificateRequest::findOrFail($id);
    }

    public static function createRequest(array $data, $user)
    {
        $memberId = $data['member_id'] ?? null;
        $familyId = $data['family_id'] ?? null;

        if ($memberId) {
            if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
                throw new Exception("Access Denied to request certificate for this member.");
            }
            $member = Member::findOrFail($memberId);
            $data['diocese_id'] = $member->diocese_id;
            $data['church_id'] = $member->church_id;
            $data['family_id'] = $member->family_id;
        } elseif ($familyId) {
            if (!MemberPortalSecurity::validateFamilyAccess($user, $familyId)) {
                throw new Exception("Access Denied to request certificate for this family.");
            }
            $family = Family::findOrFail($familyId);
            $data['diocese_id'] = $family->diocese_id;
            $data['church_id'] = $family->church_id;
        } else {
            throw new Exception("Either member_id or family_id must be provided.");
        }

        $data['requested_by'] = $user->id;
        $request = (new CertificateRequestService())->create($data);

        self::logActivity($request->diocese_id, $request->church_id, $user->id, $request->family_id, $request->member_id, 'certificate_requested', "Requested certificate of type: {$request->certificate_type}");

        return $request;
    }

    public static function listCertificates($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        return Certificate::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', $familyIds)
            ->with(['member', 'family'])
            ->get();
    }

    public static function download(Certificate $certificate, $user)
    {
        if (!MemberPortalSecurity::validateCertificateAccess($user, $certificate->id)) {
            throw new Exception("Access Denied: You are not authorized to download this certificate.");
        }

        if (!$certificate->pdf_path || !Storage::exists($certificate->pdf_path)) {
            throw new Exception("Certificate file not found.");
        }

        self::logActivity($certificate->diocese_id, $certificate->church_id, $user->id, $certificate->family_id, $certificate->member_id, 'certificate_downloaded', "Downloaded certificate: {$certificate->certificate_number}");

        return Storage::download($certificate->pdf_path, "{$certificate->certificate_number}.pdf");
    }

    private static function logActivity($dioceseId, $churchId, $userId, $familyId, $memberId, string $action, string $description)
    {
        MemberPortalActivityLog::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'user_id' => $userId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
