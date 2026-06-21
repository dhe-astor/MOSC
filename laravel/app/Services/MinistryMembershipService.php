<?php

namespace App\Services;

use App\Models\MinistryMembership;
use App\Models\MinistryUnit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use App\Services\AuditLogService;

class MinistryMembershipService
{
    protected MinistryEligibilityService $eligibilityService;

    public function __construct(MinistryEligibilityService $eligibilityService)
    {
        $this->eligibilityService = $eligibilityService;
    }

    /**
     * Enroll a member in a ministry unit.
     */
    public function enroll(array $data, User $user): MinistryMembership
    {
        $unit = MinistryUnit::findOrFail($data['ministry_unit_id']);
        $member = Member::findOrFail($data['member_id']);

        // 1. Verify that the member is approved and active
        if ($member->membership_status !== 'active') {
            throw new Exception("Only active/approved parish members can enroll in ministries.");
        }

        // 2. Prevent duplicate active/pending membership in the same unit
        $exists = MinistryMembership::where('ministry_unit_id', $unit->id)
            ->where('member_id', $member->id)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        if ($exists) {
            throw new Exception("Member is already enrolled (active or pending) in this unit.");
        }

        // 3. Verify eligibility
        $allowOverride = $data['override_eligibility'] ?? false;
        $this->eligibilityService->validateEligibility($member, $unit->organization, $user, $allowOverride);

        // 4. Create membership record
        $membership = MinistryMembership::create([
            'diocese_id' => $unit->diocese_id,
            'church_id' => $unit->church_id,
            'ministry_unit_id' => $unit->id,
            'member_id' => $member->id,
            'family_id' => $member->family_id,
            'membership_type' => $data['membership_type'] ?? 'regular',
            'joined_date' => $data['joined_date'] ?? Carbon::today(),
            'status' => 'pending', // default to pending for approvals
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'member_enrolled',
            "Enrolled member {$member->full_name} in unit {$unit->unit_name} as pending",
            null,
            $membership->toArray(),
            $membership,
            $unit->church_id,
            $unit->diocese_id
        );

        return $membership;
    }

    /**
     * Approve a membership request.
     */
    public function approve(int $id, User $user): MinistryMembership
    {
        $membership = MinistryMembership::findOrFail($id);
        
        if ($membership->status !== 'pending') {
            throw new Exception("Membership record is not in a pending state.");
        }

        $membership->update([
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'updated_by' => $user->id,
        ]);

        $membership->load(['member', 'unit']);

        AuditLogService::log(
            'ministry',
            'member_approved',
            "Approved membership for {$membership->member->full_name} in {$membership->unit->unit_name}",
            null,
            $membership->toArray(),
            $membership,
            $membership->church_id,
            $membership->diocese_id
        );

        return $membership;
    }

    /**
     * Reject a membership request.
     */
    public function reject(int $id, User $user, string $remarks = null): MinistryMembership
    {
        $membership = MinistryMembership::findOrFail($id);
        
        if ($membership->status !== 'pending') {
            throw new Exception("Membership record is not in a pending state.");
        }

        $membership->update([
            'status' => 'inactive',
            'remarks' => $remarks ?? 'Rejected by administrator',
            'updated_by' => $user->id,
        ]);

        $membership->load(['member', 'unit']);

        AuditLogService::log(
            'ministry',
            'member_rejected',
            "Rejected membership for {$membership->member->full_name} in {$membership->unit->unit_name}",
            null,
            ['remarks' => $remarks],
            $membership,
            $membership->church_id,
            $membership->diocese_id
        );

        return $membership;
    }

    /**
     * Transfer a membership from one unit to another (for example when a member transfers parishes).
     */
    public function transfer(int $id, int $targetUnitId, User $user): MinistryMembership
    {
        $membership = MinistryMembership::findOrFail($id);
        $targetUnit = MinistryUnit::findOrFail($targetUnitId);

        if ($membership->unit->ministry_organization_id !== $targetUnit->ministry_organization_id) {
            throw new Exception("Cannot transfer membership to a different type of organization.");
        }

        // Terminate old membership
        $membership->update([
            'status' => 'transferred',
            'remarks' => "Transferred to unit {$targetUnit->unit_name}",
            'updated_by' => $user->id,
        ]);

        // Create new active membership in the target unit
        $newMembership = MinistryMembership::create([
            'diocese_id' => $targetUnit->diocese_id,
            'church_id' => $targetUnit->church_id,
            'ministry_unit_id' => $targetUnit->id,
            'member_id' => $membership->member_id,
            'family_id' => $membership->family_id,
            'membership_type' => $membership->membership_type,
            'joined_date' => Carbon::today(),
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'remarks' => "Transferred from unit {$membership->unit->unit_name}",
            'created_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'member_transferred',
            "Transferred member ID {$membership->member_id} to unit {$targetUnit->unit_name}",
            null,
            ['old_membership' => $membership->toArray(), 'new_membership' => $newMembership->toArray()],
            $newMembership,
            $targetUnit->church_id,
            $targetUnit->diocese_id
        );

        return $newMembership;
    }
}
