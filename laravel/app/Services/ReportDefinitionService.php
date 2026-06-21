<?php

namespace App\Services;

use App\Models\ReportDefinition;
use App\Models\User;

class ReportDefinitionService
{
    /**
     * Get all active report definitions authorized for the logged-in user.
     */
    public static function getAvailableDefinitions(User $user)
    {
        $query = ReportDefinition::where('status', 'active');

        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $query->get();
        }

        return $query->get()->filter(function ($definition) use ($user) {
            // Check required permissions
            $requiredPermissions = $definition->required_permissions ?? [];
            if (!empty($requiredPermissions)) {
                $hasPerm = false;
                foreach ($requiredPermissions as $perm) {
                    if ($user->hasPermissionTo($perm)) {
                        $hasPerm = true;
                        break;
                    }
                }
                if (!$hasPerm) {
                    return false;
                }
            }

            // Check allowed roles
            $allowedRoles = $definition->allowed_roles ?? [];
            if (!empty($allowedRoles)) {
                if (!$user->hasRole($allowedRoles)) {
                    return false;
                }
            }

            return true;
        })->values();
    }
}
