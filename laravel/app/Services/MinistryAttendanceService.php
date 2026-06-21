<?php

namespace App\Services;

use App\Models\MinistryActivity;
use App\Models\MinistryActivityAttendance;
use App\Models\MinistryMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Services\AuditLogService;

class MinistryAttendanceService
{
    /**
     * Mark attendance for a ministry activity.
     */
    public function markAttendance(int $activityId, array $records, User $user): array
    {
        $activity = MinistryActivity::findOrFail($activityId);

        // Check if activity is completed or cancelled
        if ($activity->status === 'cancelled') {
            throw new Exception("Cannot mark attendance for a cancelled activity.");
        }

        $results = [];

        DB::transaction(function () use ($activity, $records, $user, &$results) {
            foreach ($records as $record) {
                $membershipId = $record['ministry_membership_id'] ?? null;
                $memberId = $record['member_id'] ?? null;

                if (!$membershipId && !$memberId) {
                    throw new Exception("Either ministry_membership_id or member_id is required.");
                }

                if ($membershipId) {
                    $membership = MinistryMembership::findOrFail($membershipId);
                    $memberId = $membership->member_id;
                }

                // Prevent duplicate attendance for the same activity and member
                $attendance = MinistryActivityAttendance::updateOrCreate(
                    [
                        'ministry_activity_id' => $activity->id,
                        'member_id' => $memberId,
                    ],
                    [
                        'ministry_membership_id' => $membershipId,
                        'status' => $record['status'], // present, absent, late, excused
                        'marked_by' => $user->id,
                        'marked_at' => Carbon::now(),
                        'remarks' => $record['remarks'] ?? null,
                    ]
                );

                $results[] = $attendance;
            }

            // Update activity status to completed if it was published
            if ($activity->status === 'published') {
                $activity->update(['status' => 'completed']);
            }

            AuditLogService::log(
                'ministry',
                'attendance_marked',
                "Marked attendance for activity ID {$activity->id} with " . count($records) . " records.",
                null,
                ['activity_id' => $activity->id, 'count' => count($records)],
                $activity,
                $activity->church_id,
                $activity->diocese_id
            );
        });

        return $results;
    }

    /**
     * Get attendance summary stats for a ministry unit.
     */
    public function getUnitAttendanceStats(int $unitId): array
    {
        $activities = MinistryActivity::where('ministry_unit_id', $unitId)
            ->where('status', 'completed')
            ->pluck('id');

        if ($activities->isEmpty()) {
            return [
                'total_meetings' => 0,
                'average_attendance_percentage' => 0,
            ];
        }

        $totalPresent = MinistryActivityAttendance::whereIn('ministry_activity_id', $activities)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $totalRecords = MinistryActivityAttendance::whereIn('ministry_activity_id', $activities)
            ->count();

        return [
            'total_meetings' => $activities->count(),
            'average_attendance_percentage' => $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 2) : 0,
        ];
    }
}
