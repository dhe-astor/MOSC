<?php

namespace App\Services;

use App\Models\MemberChangeRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MemberChangeRequestService
{
    /**
     * Approve a member change request.
     */
    public function approve(MemberChangeRequest $changeRequest, User $user): void
    {
        if ($changeRequest->status === 'approved') {
            return;
        }

        DB::transaction(function () use ($changeRequest, $user) {
            $member = Member::findOrFail($changeRequest->member_id);
            $oldValues = $member->toArray();

            $newData = $changeRequest->new_data;

            // Apply new data to member
            foreach ($newData as $key => $value) {
                $member->$key = $value;
            }

            // Recalculate full name if name components were updated
            if (array_key_exists('first_name', $newData) || array_key_exists('middle_name', $newData) || array_key_exists('last_name', $newData)) {
                $member->full_name = trim($member->first_name . ' ' . ($member->middle_name ? $member->middle_name . ' ' : '') . $member->last_name);
            }

            $member->save();

            // Update change request status
            $changeRequest->status = 'approved';
            $changeRequest->approved_by = $user->id;
            $changeRequest->approved_at = Carbon::now();
            $changeRequest->save();

            AuditLogService::log(
                'members',
                'member_change_approved',
                "Sensitive change request #{$changeRequest->id} for Member '{$member->full_name}' was approved",
                $oldValues,
                $member->toArray(),
                $member,
                $member->church_id
            );
        });
    }

    /**
     * Reject a member change request.
     */
    public function reject(MemberChangeRequest $changeRequest, User $user, ?string $reason): void
    {
        if ($changeRequest->status === 'rejected') {
            return;
        }

        $changeRequest->status = 'rejected';
        $changeRequest->reviewed_by = $user->id;
        $changeRequest->reviewed_at = Carbon::now();
        $changeRequest->rejection_reason = $reason;
        $changeRequest->save();

        $member = Member::findOrFail($changeRequest->member_id);

        AuditLogService::log(
            'members',
            'member_change_rejected',
            "Sensitive change request #{$changeRequest->id} for Member '{$member->full_name}' was rejected. Reason: {$reason}",
            null,
            $changeRequest->toArray(),
            $member,
            $member->church_id
        );
    }
}
