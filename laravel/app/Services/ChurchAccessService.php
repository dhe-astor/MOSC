<?php

namespace App\Services;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestChurchAssignment;
use App\Models\UserChurchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ChurchAccessService
{
    /**
     * Check if the user has diocese-wide access.
     */
    public static function hasDioceseAccess(User $user): bool
    {
        if ($user->hasRole([
            'Super Admin',
            'Diocese Admin',
            'Diocese Secretary',
            'Priest Secretary',
            'Diocese PRO',
            'Diocese Treasurer',
            'Diocese Auditor',
            'Parish Admin',
            'Parish Secretary',
            'Parish Treasurer',
            'Sunday School Admin',
            'Youth Association Coordinator',
            'Marthamariyam Coordinator',
            'Sunday School Teacher',
            'Priest / Vicar'
        ])) {
            return true;
        }

        return UserChurchAccess::where('user_id', $user->id)
            ->where('access_scope', 'diocese_all')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', Carbon::now());
            })
            ->exists();
    }

    /**
     * Get all church IDs accessible by the user.
     * Returns null if they have diocese-wide access (all churches).
     */
    public static function getAccessibleChurchIds(User $user): ?array
    {
        if (self::hasDioceseAccess($user)) {
            return null; // Null indicates all churches
        }

        $churchIds = [];

        // 1. Check user_church_access table
        $accessIds = UserChurchAccess::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', Carbon::now());
            })
            ->pluck('church_id')
            ->filter()
            ->toArray();

        $churchIds = array_merge($churchIds, $accessIds);

        // 2. If user is a priest, check their assignments
        $user->loadMissing('priest');
        $priest = $user->priest;
        if ($priest) {
            $assignmentIds = PriestChurchAssignment::where('priest_profile_id', $priest->id)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', Carbon::today());
                })
                ->pluck('church_id')
                ->toArray();
            
            $churchIds = array_merge($churchIds, $assignmentIds);
        }

        return array_values(array_unique($churchIds));
    }

    /**
     * Check if a user has access to a specific church ID.
     */
    public static function canAccessChurch(User $user, int $churchId): bool
    {
        if (self::hasDioceseAccess($user)) {
            return true;
        }

        $accessibleIds = self::getAccessibleChurchIds($user);
        
        return in_array($churchId, $accessibleIds ?? []);
    }

    /**
     * Scope an Eloquent query to only show records belonging to accessible churches.
     */
    public static function scopeQuery(User $user, Builder $query, string $churchIdColumn = 'church_id'): Builder
    {
        if (self::hasDioceseAccess($user)) {
            return $query;
        }

        $accessibleIds = self::getAccessibleChurchIds($user);

        if (empty($accessibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        if ($query->getModel() instanceof Church) {
            return $query->whereIn('id', $accessibleIds);
        }

        return $query->whereIn($churchIdColumn, $accessibleIds);
    }
}
