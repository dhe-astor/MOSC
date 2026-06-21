<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Priest;

class PriestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view_priests', 'manage_priests']);
    }

    public function view(User $user, Priest $priest): bool
    {
        if ($user->hasAnyPermission(['view_priests', 'manage_priests'])) {
            return true;
        }

        // A priest can view their own profile
        return $priest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_priests');
    }

    public function update(User $user, Priest $priest): bool
    {
        if ($user->hasPermissionTo('manage_priests')) {
            return true;
        }

        // A priest can update their own profile
        return $priest->user_id === $user->id;
    }

    public function delete(User $user, Priest $priest): bool
    {
        return $user->hasPermissionTo('manage_priests');
    }
}
