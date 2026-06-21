<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Church;
use Illuminate\Support\Facades\DB;

class MemberCodeService
{
    /**
     * Generate and save a unique member code for an approved member.
     */
    public function generateCode(Member $member): string
    {
        if ($member->member_code) {
            return $member->member_code;
        }

        $church = Church::findOrFail($member->church_id);
        $prefixCode = $church->membership_code_prefix ?: strtoupper(substr($church->short_name, 0, 3));
        $prefix = 'MSOC-' . $prefixCode . '-M-';

        return DB::transaction(function () use ($member, $prefix) {
            // Lock records matching the prefix to prevent concurrent duplicate allocation
            $maxCode = Member::where('member_code', 'like', $prefix . '%')
                ->lockForUpdate()
                ->max('member_code');

            $nextNumber = 1;
            if ($maxCode) {
                $parts = explode('-', $maxCode);
                $lastPart = end($parts);
                $nextNumber = intval($lastPart) + 1;
            }

            $newCode = $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $member->member_code = $newCode;
            $member->save();

            return $newCode;
        });
    }
}
