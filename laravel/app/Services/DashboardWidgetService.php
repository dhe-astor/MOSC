<?php

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\User;

class DashboardWidgetService
{
    /**
     * Get active widgets that the user has permissions to view.
     */
    public static function getActiveWidgets(User $user)
    {
        $query = DashboardWidget::where('status', 'active')->orderBy('sort_order');

        $widgets = $query->get()->filter(function ($widget) use ($user) {
            // Check required permissions
            $requiredPermissions = $widget->required_permissions ?? [];
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
            $allowedRoles = $widget->allowed_roles ?? [];
            if (!empty($allowedRoles)) {
                if (!$user->hasRole($allowedRoles)) {
                    return false;
                }
            }

            return true;
        })->values();

        return $widgets;
    }
}
