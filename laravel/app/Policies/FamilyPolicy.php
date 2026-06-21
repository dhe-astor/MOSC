<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Family;
use App\Services\ChurchAccessService;

class FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_families', 'view_reports']) || $user->hasRole('Parish Treasurer');
    }

    public function view(User $user, Family $family): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $family->church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_families');
    }

    public function update(User $user, Family $family): bool
    {
        if (!$user->hasPermissionTo('manage_families')) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $family->church_id);
    }

    public function delete(User $user, Family $family): bool
    {
        if (!$user->hasPermissionTo('manage_families')) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $family->church_id);
    }

    public function approve(User $user, Family $family): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Diocese Secretary'])) {
            return true;
        }
        if ($user->hasRole('Priest / Vicar')) {
            return ChurchAccessService::canAccessChurch($user, $family->church_id);
        }
        return false;
    }
}
