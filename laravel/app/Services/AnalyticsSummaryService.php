<?php

namespace App\Services;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Services\ChurchAccessService;

class AnalyticsSummaryService
{
    /**
     * Get aggregate statistics scoped to the user's church access.
     */
    public static function getHighLevelMetrics(User $user): array
    {
        $churchesQuery = Church::query();
        $familiesQuery = Family::query();
        $membersQuery = Member::query();

        $churchesQuery = ChurchAccessService::scopeQuery($user, $churchesQuery);
        $familiesQuery = ChurchAccessService::scopeQuery($user, $familiesQuery);
        $membersQuery = ChurchAccessService::scopeQuery($user, $membersQuery);

        return [
            'churches_count' => $churchesQuery->count(),
            'families_count' => $familiesQuery->count(),
            'members_count' => $membersQuery->count(),
        ];
    }
}
