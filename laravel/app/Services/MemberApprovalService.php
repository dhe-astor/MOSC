<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberCodeService;
use App\Services\AuditLogService;
use Illuminate\Support\Carbon;

class MemberApprovalService
{
    protected $codeService;

    public function __construct(MemberCodeService $codeService)
    {
        $this->codeService = $codeService;
    }

    /**
     * Approve a member registration.
     */
    public function approve(Member $member, User $user): string
    {
        if ($member->membership_status === 'active') {
            return $member->member_code;
        }

        $oldValues = $member->toArray();

        $member->membership_status = 'active';
        $member->approved_by = $user->id;
        $member->approved_at = Carbon::now();
        $member->save();

        // Generate unique member code
        $code = $this->codeService->generateCode($member);

        AuditLogService::log(
            'members',
            'member_approved',
            "Member '{$member->full_name}' was approved and assigned code '{$code}'",
            $oldValues,
            $member->toArray(),
            $member,
            $member->church_id
        );

        return $code;
    }

    /**
     * Reject a member registration.
     */
    public function reject(Member $member, User $user): void
    {
        $oldValues = $member->toArray();

        $member->membership_status = 'inactive';
        $member->save();
        $member->delete(); // Soft delete rejected member

        AuditLogService::log(
            'members',
            'member_rejected',
            "Member '{$member->full_name}' was rejected and soft-deleted",
            $oldValues,
            $member->toArray(),
            $member,
            $member->church_id
        );
    }
}
