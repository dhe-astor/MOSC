<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Member;
use App\Services\ChurchAccessService;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_members', 'view_reports']) || $user->hasRole('Parish Treasurer');
    }

    public function view(User $user, Member $member): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $member->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_members');
    }

    public function update(User $user, Member $member): bool
    {
        if (!$user->hasPermissionTo('manage_members')) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $member->church_id);
    }

    public function delete(User $user, Member $member): bool
    {
        if (!$user->hasPermissionTo('manage_members')) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $member->church_id);
    }

    public function approve(User $user, Member $member): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Diocese Secretary'])) {
            return true;
        }
        if ($user->hasRole('Priest / Vicar')) {
            return ChurchAccessService::canAccessChurch($user, $member->church_id);
        }
        return false;
    }
}
