<?php

namespace App\Services;

use App\Models\PriestChurchAssignment;
use App\Models\PriestProfile;
use App\Models\UserChurchAccess;
use App\Models\User;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class PriestAssignmentService
{
    /**
     * Create a new priest church assignment.
     */
    public static function assignPriest(array $data, User $adminUser): PriestChurchAssignment
    {
        return DB::transaction(function () use ($data, $adminUser) {
            $priestProfile = PriestProfile::findOrFail($data['priest_profile_id']);
            $churchId = $data['church_id'];
            $role = $data['assignment_role'];
            $startDate = $data['start_date'] ?? date('Y-m-d');
            $endDate = $data['end_date'] ?? null;
            $isPrimary = $data['is_primary'] ?? false;
            $status = $data['status'] ?? 'active';

            // Vicar limit validation: one active primary vicar per church
            if ($isPrimary && $status === 'active' && $role === 'vicar') {
                $existingVicar = PriestChurchAssignment::where('church_id', $churchId)
                    ->where('is_primary', true)
                    ->where('assignment_role', 'vicar')
                    ->where('status', 'active')
                    ->first();

                if ($existingVicar) {
                    throw new Exception("Church already has an active primary Vicar.");
                }
            }

            $assignment = PriestChurchAssignment::create(array_merge($data, [
                'diocese_id' => $priestProfile->diocese_id,
                'member_id' => $priestProfile->member_id,
                'user_id' => $priestProfile->user_id,
                'created_by' => $adminUser->id,
            ]));

            // Log Audit Entry
            AuditLogService::log(
                'assignments',
                'priest_assignment_created',
                "Assigned priest '{$priestProfile->display_name}' to Church ID {$churchId} as '{$role}'",
                null,
                $assignment->toArray(),
                $assignment,
                $churchId,
                $priestProfile->diocese_id
            );

            // Sync User Church Access
            if ($priestProfile->user_id && $status === 'active') {
                UserChurchAccess::updateOrCreate(
                    [
                        'user_id' => $priestProfile->user_id,
                        'church_id' => $churchId,
                    ],
                    [
                        'diocese_id' => $priestProfile->diocese_id,
                        'access_scope' => 'church_specific',
                        'starts_at' => $startDate,
                        'ends_at' => $endDate,
                        'status' => 'active',
                        'created_by' => $adminUser->id,
                    ]
                );
            }

            return $assignment;
        });
    }

    /**
     * End an active priest church assignment.
     */
    public static function endAssignment(PriestChurchAssignment $assignment, string $endDate, ?string $reason, User $adminUser): PriestChurchAssignment
    {
        return DB::transaction(function () use ($assignment, $endDate, $reason, $adminUser) {
            $assignment->update([
                'end_date' => $endDate,
                'status' => 'ended',
                'ended_by' => $adminUser->id,
                'ended_at' => now(),
                'end_reason' => $reason,
            ]);

            // Log Audit Entry
            AuditLogService::log(
                'assignments',
                'priest_assignment_ended',
                "Ended assignment ID {$assignment->id} for Priest '{$assignment->priestProfile?->display_name}' at Church ID {$assignment->church_id}",
                null,
                $assignment->toArray(),
                $assignment,
                $assignment->church_id,
                $assignment->diocese_id
            );

            // Deactivate User Church Access
            if ($assignment->user_id) {
                UserChurchAccess::where('user_id', $assignment->user_id)
                    ->where('church_id', $assignment->church_id)
                    ->update([
                        'status' => 'inactive',
                        'ends_at' => now(),
                    ]);
            }

            return $assignment;
        });
    }

    /**
     * Fetch active church assignments for a priest.
     */
    public static function getActiveAssignmentsForPriest(int $priestProfileId)
    {
        $today = date('Y-m-d');
        return PriestChurchAssignment::where('priest_profile_id', $priestProfileId)
            ->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
            })
            ->get();
    }

    /**
     * Fetch active church assignments for a user.
     */
    public static function getActiveAssignmentsForUser(User $user)
    {
        $profile = PriestProfile::where('user_id', $user->id)->first();
        if (!$profile) {
            return collect();
        }
        return self::getActiveAssignmentsForPriest($profile->id);
    }

    /**
     * Check if a priest user can access a specific church.
     */
    public static function canAccessChurch(User $user, int $churchId): bool
    {
        // Diocese Admins can access all
        if ($user->hasRole('Super Admin') || $user->hasRole('Diocese Admin')) {
            return true;
        }

        $activeAssignments = self::getActiveAssignmentsForUser($user);
        return $activeAssignments->pluck('church_id')->contains($churchId);
    }
}
