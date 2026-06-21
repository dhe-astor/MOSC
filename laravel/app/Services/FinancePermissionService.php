<?php

namespace App\Services;

use App\Models\User;
use Exception;

class FinancePermissionService
{
    /**
     * Verify if the user is a treasurer (either diocese-level or parish-level).
     */
    public static function checkTreasurer(User $user, ?int $churchId = null): bool
    {
        // Diocese Admin or Diocese Treasurer can access all churches
        if ($user->hasRole('Diocese Admin') || $user->hasRole('Diocese Treasurer')) {
            return true;
        }

        // Parish Treasurer can only access their specific church
        if ($user->hasRole('Parish Treasurer')) {
            if ($churchId !== null) {
                // Check if user is associated with this church
                return DB::table('user_church_access')
                    ->where('user_id', $user->id)
                    ->where('church_id', $churchId)
                    ->exists();
            }
            return true;
        }

        return false;
    }

    /**
     * Verify if the user is the primary vicar or has permission to manage priest payments.
     */
    public static function checkPriestAccess(User $user, int $priestId): bool
    {
        if ($user->hasRole('Diocese Admin') || $user->hasRole('Diocese Treasurer') || $user->hasRole('Parish Treasurer')) {
            return true;
        }

        // Priest can self-access their records
        $priest = \App\Models\Priest::where('user_id', $user->id)->first();
        if ($priest && $priest->id === $priestId) {
            return true;
        }

        return false;
    }

    /**
     * Verify if the user is a member self-accessing their records.
     */
    public static function checkMemberAccess(User $user, int $memberId): bool
    {
        if ($user->hasRole('Diocese Admin') || $user->hasRole('Diocese Treasurer') || $user->hasRole('Parish Treasurer')) {
            return true;
        }

        // Check if user is linked to the member record via portal access
        $member = \App\Models\Member::find($memberId);
        if ($member && $member->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
