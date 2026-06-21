<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Models\ProfileCorrectionRequest;
use App\Models\MemberPortalActivityLog;
use Exception;

class MemberPortalProfileService
{
    // Allowed whitelist fields to auto-apply on approval
    protected static $familyWhitelist = [
        'family_name',
        'primary_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country'
    ];

    protected static $memberWhitelist = [
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'whatsapp_phone',
        'email',
        'occupation',
        'employer_or_school',
        'emergency_contact_name',
        'emergency_contact_phone'
    ];

    public static function getFamilyProfile($familyId, $user)
    {
        if (!MemberPortalSecurity::validateFamilyAccess($user, $familyId)) {
            throw new Exception("Access Denied: You do not have access to this family profile.");
        }

        $family = Family::findOrFail($familyId);
        self::logActivity($family->diocese_id, $family->church_id, $user->id, $family->id, null, 'family_profile_viewed', "Viewed family profile: {$family->family_name}");

        return $family->makeHidden(['created_by', 'updated_by', 'approved_by', 'approved_at']);
    }

    public static function getMemberProfile($memberId, $user)
    {
        if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
            throw new Exception("Access Denied: You do not have access to this member profile.");
        }

        $member = Member::findOrFail($memberId);
        self::logActivity($member->diocese_id, $member->church_id, $user->id, $member->family_id, $member->id, 'member_profile_viewed', "Viewed member profile: {$member->full_name}");

        return $member->makeHidden(['created_by', 'updated_by', 'approved_by', 'approved_at']);
    }

    public static function createCorrectionRequest($data, $user)
    {
        $familyId = $data['family_id'] ?? null;
        $memberId = $data['member_id'] ?? null;
        $requestType = $data['request_type'];
        $requestedData = $data['requested_data'];
        $reason = $data['reason'] ?? null;

        $dioceseId = null;
        $churchId = null;
        $currentData = null;

        if ($memberId) {
            if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
                throw new Exception("Access Denied to this member profile.");
            }
            $member = Member::findOrFail($memberId);
            $dioceseId = $member->diocese_id;
            $churchId = $member->church_id;
            $familyId = $member->family_id;
            $currentData = $member->only(array_keys($requestedData));
        } elseif ($familyId) {
            if (!MemberPortalSecurity::validateFamilyAccess($user, $familyId)) {
                throw new Exception("Access Denied to this family profile.");
            }
            $family = Family::findOrFail($familyId);
            $dioceseId = $family->diocese_id;
            $churchId = $family->church_id;
            $currentData = $family->only(array_keys($requestedData));
        } else {
            throw new Exception("Either family_id or member_id must be provided.");
        }

        $request = ProfileCorrectionRequest::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'requested_by' => $user->id,
            'request_type' => $requestType,
            'current_data' => $currentData,
            'requested_data' => $requestedData,
            'reason' => $reason,
            'status' => 'submitted',
        ]);

        self::logActivity($dioceseId, $churchId, $user->id, $familyId, $memberId, 'correction_request_submitted', "Submitted profile correction request for {$requestType}");

        return $request;
    }

    public static function cancelCorrectionRequest(ProfileCorrectionRequest $request, $user)
    {
        if ($request->requested_by !== $user->id) {
            throw new Exception("Access Denied: You did not create this request.");
        }

        if ($request->status !== 'submitted') {
            throw new Exception("Only submitted correction requests can be cancelled.");
        }

        $request->update(['status' => 'cancelled']);

        self::logActivity($request->diocese_id, $request->church_id, $user->id, $request->family_id, $request->member_id, 'correction_request_cancelled', "Cancelled profile correction request");

        return $request;
    }

    public static function approveCorrectionRequest(ProfileCorrectionRequest $request, $reviewer)
    {
        if ($request->status !== 'submitted' && $request->status !== 'under_review') {
            throw new Exception("Request is not in a reviewable state.");
        }

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'applied_at' => now(),
        ]);

        // Auto-apply whitelisted changes
        if ($request->member_id) {
            $member = Member::find($request->member_id);
            if ($member) {
                $appliedChanges = [];
                foreach ($request->requested_data as $key => $value) {
                    if (in_array($key, self::$memberWhitelist)) {
                        $member->{$key} = $value;
                        $appliedChanges[$key] = $value;
                    }
                }
                if (isset($appliedChanges['first_name']) || isset($appliedChanges['last_name']) || isset($appliedChanges['middle_name'])) {
                    $member->full_name = trim(($appliedChanges['first_name'] ?? $member->first_name) . ' ' . ($appliedChanges['middle_name'] ?? $member->middle_name) . ' ' . ($appliedChanges['last_name'] ?? $member->last_name));
                }
                $member->save();
                self::logActivity($request->diocese_id, $request->church_id, $reviewer->id, $request->family_id, $request->member_id, 'correction_auto_applied', "Auto-applied member corrections for whitelisted fields: " . json_encode(array_keys($appliedChanges)));
            }
        } elseif ($request->family_id) {
            $family = Family::find($request->family_id);
            if ($family) {
                $appliedChanges = [];
                foreach ($request->requested_data as $key => $value) {
                    if (in_array($key, self::$familyWhitelist)) {
                        $family->{$key} = $value;
                        $appliedChanges[$key] = $value;
                    }
                }
                $family->save();
                self::logActivity($request->diocese_id, $request->church_id, $reviewer->id, $request->family_id, null, 'correction_auto_applied', "Auto-applied family corrections for whitelisted fields: " . json_encode(array_keys($appliedChanges)));
            }
        }

        self::logActivity($request->diocese_id, $request->church_id, $reviewer->id, $request->family_id, $request->member_id, 'correction_request_approved', "Approved profile correction request", $request->requested_by);

        return $request;
    }

    public static function rejectCorrectionRequest(ProfileCorrectionRequest $request, string $reason, $reviewer)
    {
        if ($request->status !== 'submitted' && $request->status !== 'under_review') {
            throw new Exception("Request is not in a reviewable state.");
        }

        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        self::logActivity($request->diocese_id, $request->church_id, $reviewer->id, $request->family_id, $request->member_id, 'correction_request_rejected', "Rejected profile correction request. Reason: {$reason}", $request->requested_by);

        return $request;
    }

    private static function logActivity($dioceseId, $churchId, $userId, $familyId, $memberId, string $action, string $description, $logUserId = null)
    {
        MemberPortalActivityLog::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'user_id' => $logUserId ?? $userId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
