<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyTransferRequest;
use App\Models\MemberPortalActivityLog;
use App\Models\MemberPortalAccess;
use App\Services\FamilyTransferService;
use Exception;

class MemberPortalTransferService
{
    public static function listRequests($user)
    {
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);
        return FamilyTransferRequest::whereIn('family_id', $familyIds)
            ->with(['family', 'fromChurch', 'toChurch'])
            ->get();
    }

    public static function getRequest($id, $user)
    {
        if (!MemberPortalSecurity::validateTransferRequestAccess($user, $id)) {
            throw new Exception("Access Denied to this transfer request.");
        }

        return FamilyTransferRequest::findOrFail($id);
    }

    public static function createRequest(array $data, $user)
    {
        $familyId = $data['family_id'];

        // Access check: only Family Heads can submit transfer requests
        $access = MemberPortalAccess::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->where('access_type', 'family_head')
            ->where('status', 'active')
            ->first();

        if (!$access) {
            throw new Exception("Access Denied: Only Family Heads can submit family transfer requests.");
        }

        $family = Family::findOrFail($familyId);

        $transfer = (new FamilyTransferService())->createRequest(
            $family,
            $data['to_church_id'],
            $user,
            $data['reason'] ?? null
        );

        self::logActivity($family->diocese_id, $family->church_id, $user->id, $family->id, null, 'transfer_request_submitted', "Submitted family transfer request to church ID: {$data['to_church_id']}");

        return $transfer;
    }

    public static function cancel(FamilyTransferRequest $tr, $user)
    {
        if (!MemberPortalSecurity::validateTransferRequestAccess($user, $tr->id)) {
            throw new Exception("Access Denied to this transfer request.");
        }

        if ($tr->status !== 'requested') {
            throw new Exception("Transfer request cannot be cancelled once approval has started.");
        }

        $tr->status = 'cancelled';
        $tr->save();

        self::logActivity($tr->family?->diocese_id, $tr->from_church_id, $user->id, $tr->family_id, null, 'transfer_request_cancelled', "Cancelled family transfer request");

        return $tr;
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
