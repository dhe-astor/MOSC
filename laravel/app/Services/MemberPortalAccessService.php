<?php

namespace App\Services;

use App\Models\MemberPortalAccess;
use App\Models\MemberPortalActivityLog;
use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use Exception;

class MemberPortalAccessService
{
    public static function invite($data, $creator)
    {
        $userId = $data['user_id'];
        $accessType = $data['access_type'];
        $familyId = $data['family_id'] ?? null;
        $memberId = $data['member_id'] ?? null;

        // Verify that there is no duplicate active/invited/suspended access for this user identity combo
        $existing = MemberPortalAccess::where('user_id', $userId)
            ->where('family_id', $familyId)
            ->where('member_id', $memberId)
            ->where('access_type', $accessType)
            ->whereIn('status', ['invited', 'active', 'suspended'])
            ->first();

        if ($existing) {
            throw new Exception("Active or pending portal access already exists for this combination.");
        }

        // Validate member and family exist
        if ($memberId) {
            $member = Member::findOrFail($memberId);
            $data['diocese_id'] = $member->diocese_id;
            $data['church_id'] = $member->church_id;
        } elseif ($familyId) {
            $family = Family::findOrFail($familyId);
            $data['diocese_id'] = $family->diocese_id;
            $data['church_id'] = $family->church_id;
        }

        $access = MemberPortalAccess::create([
            'diocese_id' => $data['diocese_id'],
            'church_id' => $data['church_id'],
            'family_id' => $familyId,
            'member_id' => $memberId,
            'user_id' => $userId,
            'access_type' => $accessType,
            'status' => 'invited',
            'invited_by' => $creator->id,
            'invited_at' => now(),
        ]);

        self::logActivity($access, 'portal_access_invited', "Invited user to member portal as {$accessType}", $creator->id);

        return $access;
    }

    public static function activate(MemberPortalAccess $access)
    {
        if ($access->status !== 'invited') {
            throw new Exception("Only invited portal access records can be activated.");
        }

        $access->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        self::logActivity($access, 'portal_access_activated', "Activated member portal access");

        return $access;
    }

    public static function suspend(MemberPortalAccess $access, string $reason, $updater)
    {
        if ($access->status !== 'active') {
            throw new Exception("Only active portal access records can be suspended.");
        }

        $access->update([
            'status' => 'suspended',
            'suspended_by' => $updater->id,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        // Revoke active sessions / personal access tokens
        if ($access->user) {
            $access->user->tokens()->delete();
        }

        self::logActivity($access, 'portal_access_suspended', "Suspended member portal access. Reason: {$reason}", $updater->id);

        return $access;
    }

    public static function revoke(MemberPortalAccess $access, $updater)
    {
        $access->update([
            'status' => 'revoked',
            'revoked_by' => $updater->id,
            'revoked_at' => now(),
        ]);

        // Revoke active sessions / personal access tokens
        if ($access->user) {
            $access->user->tokens()->delete();
        }

        self::logActivity($access, 'portal_access_revoked', "Revoked member portal access", $updater->id);

        return $access;
    }

    public static function getPortalContexts($user): array
    {
        $accesses = MemberPortalAccess::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $contexts = [];
        foreach ($accesses as $access) {
            $label = match ($access->access_type) {
                'member' => 'My Profile',
                'family_head' => 'Family Head',
                'parent_guardian' => 'Parent / Guardian',
                default => 'Member Portal'
            };

            $contexts[] = [
                'id' => $access->id,
                'context_type' => $access->access_type,
                'member_id' => $access->member_id,
                'family_id' => $access->family_id,
                'label' => $label
            ];
        }

        return $contexts;
    }

    private static function logActivity(MemberPortalAccess $access, string $action, string $description, $userId = null)
    {
        MemberPortalActivityLog::create([
            'diocese_id' => $access->diocese_id,
            'church_id' => $access->church_id,
            'user_id' => $userId ?? $access->user_id,
            'family_id' => $access->family_id,
            'member_id' => $access->member_id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
