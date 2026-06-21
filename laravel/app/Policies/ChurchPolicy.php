<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Church;
use App\Services\ChurchAccessService;

class ChurchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view_churches', 'manage_churches']);
    }

    public function view(User $user, Church $church): bool
    {
        if (!$user->hasAnyPermission(['view_churches', 'manage_churches'])) {
            return false;
        }

        return ChurchAccessService::canAccessChurch($user, $church->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_churches');
    }

    public function update(User $user, Church $church): bool
    {
        if (!$user->hasPermissionTo('manage_churches')) {
            return false;
        }

        return ChurchAccessService::canAccessChurch($user, $church->id);
    }

    public function delete(User $user, Church $church): bool
    {
        // Deactivate/archive/soft delete is allowed if user can manage churches
        if (!$user->hasPermissionTo('manage_churches')) {
            return false;
        }

        return ChurchAccessService::canAccessChurch($user, $church->id);
    }
}
