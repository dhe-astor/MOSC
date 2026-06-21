<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MemberChangeRequest;
use App\Services\ChurchAccessService;

class MemberChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_members', 'approve_member_changes']);
    }

    public function view(User $user, MemberChangeRequest $request): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $request->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_members');
    }

    public function approve(User $user, MemberChangeRequest $request): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Diocese Secretary'])) {
            return true;
        }
        if ($user->hasPermissionTo('approve_member_changes')) {
            return ChurchAccessService::canAccessChurch($user, $request->church_id);
        }
        return false;
    }

    public function reject(User $user, MemberChangeRequest $request): bool
    {
        return $this->approve($user, $request);
    }
}
