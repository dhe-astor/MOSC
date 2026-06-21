<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_users');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->hasPermissionTo('manage_users')) {
            return true;
        }

        // A user can view their own profile
        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasPermissionTo('manage_users')) {
            return true;
        }

        // A user can update their own profile details
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('manage_users');
    }

    public function manageAccess(User $user): bool
    {
        return $user->hasPermissionTo('manage_user_church_access');
    }
}
