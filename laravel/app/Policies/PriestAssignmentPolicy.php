<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PriestAssignment;

class PriestAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view_priests', 'manage_priests', 'manage_priest_assignments']);
    }

    public function view(User $user, PriestAssignment $assignment): bool
    {
        return $user->hasAnyPermission(['view_priests', 'manage_priests', 'manage_priest_assignments']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_priest_assignments');
    }

    public function update(User $user, PriestAssignment $assignment): bool
    {
        return $user->hasPermissionTo('manage_priest_assignments');
    }

    public function delete(User $user, PriestAssignment $assignment): bool
    {
        return $user->hasPermissionTo('manage_priest_assignments');
    }
}
