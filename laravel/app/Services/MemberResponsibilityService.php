<?php

namespace App\Services;

use App\Models\MemberResponsibilityAssignment;
use App\Models\Member;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class MemberResponsibilityService
{
    /**
     * Assign a responsibility to a member.
     */
    public static function assignResponsibility(array $data, User $adminUser): MemberResponsibilityAssignment
    {
        return DB::transaction(function () use ($data, $adminUser) {
            $member = Member::findOrFail($data['member_id']);
            $startDate = $data['start_date'] ?? date('Y-m-d');
            $endDate = $data['end_date'] ?? null;
            $responsibilityType = $data['responsibility_type'];
            $designation = $data['designation'];
            $churchId = $data['church_id'] ?? $member->church_id;

            // Optional: check permissions mapping or roles scoping
            $assignment = MemberResponsibilityAssignment::create(array_merge($data, [
                'diocese_id' => $member->diocese_id,
                'church_id' => $churchId,
                'user_id' => $member->user_id,
                'status' => 'active',
                'assigned_by' => $adminUser->id,
            ]));

            return $assignment;
        });
    }

    /**
     * End a responsibility.
     */
    public static function endResponsibility(MemberResponsibilityAssignment $assignment, string $endDate, User $adminUser): MemberResponsibilityAssignment
    {
        $assignment->update([
            'end_date' => $endDate,
            'status' => 'ended',
        ]);

        return $assignment;
    }

    /**
     * Fetch active responsibilities for a member.
     */
    public static function getActiveResponsibilitiesForMember(int $memberId)
    {
        $today = date('Y-m-d');
        return MemberResponsibilityAssignment::where('member_id', $memberId)
            ->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
            })
            ->get();
    }
}
