<?php

namespace App\Services;

use App\Models\MinistryActivity;
use App\Models\MinistryUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use App\Services\AuditLogService;

class MinistryActivityService
{
    /**
     * Create a new activity for a ministry unit.
     */
    public function create(array $data, User $user): MinistryActivity
    {
        $unit = MinistryUnit::findOrFail($data['ministry_unit_id']);

        $activity = MinistryActivity::create([
            'diocese_id' => $unit->diocese_id,
            'church_id' => $unit->church_id,
            'ministry_unit_id' => $unit->id,
            'title' => $data['title'],
            'activity_type' => $data['activity_type'],
            'description' => $data['description'] ?? null,
            'start_datetime' => Carbon::parse($data['start_datetime']),
            'end_datetime' => isset($data['end_datetime']) ? Carbon::parse($data['end_datetime']) : null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'location_name' => $data['location_name'] ?? null,
            'mode' => $data['mode'] ?? 'offline',
            'meeting_link' => $data['meeting_link'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'activity_created',
            "Created activity '{$activity->title}' for unit {$unit->unit_name}",
            null,
            $activity->toArray(),
            $activity,
            $unit->church_id,
            $unit->diocese_id
        );

        return $activity;
    }

    /**
     * Publish a draft activity.
     */
    public function publish(int $id, User $user): MinistryActivity
    {
        $activity = MinistryActivity::findOrFail($id);

        if ($activity->status !== 'draft') {
            throw new Exception("Activity is not in draft state.");
        }

        $activity->update([
            'status' => 'published',
            'updated_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'activity_published',
            "Published activity '{$activity->title}'",
            null,
            $activity->toArray(),
            $activity,
            $activity->church_id,
            $activity->diocese_id
        );

        return $activity;
    }

    /**
     * Complete a published activity.
     */
    public function complete(int $id, User $user): MinistryActivity
    {
        $activity = MinistryActivity::findOrFail($id);

        if (!in_array($activity->status, ['draft', 'published'])) {
            throw new Exception("Cannot complete an activity that is {$activity->status}.");
        }

        $activity->update([
            'status' => 'completed',
            'updated_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'activity_completed',
            "Completed activity '{$activity->title}'",
            null,
            $activity->toArray(),
            $activity,
            $activity->church_id,
            $activity->diocese_id
        );

        return $activity;
    }

    /**
     * Cancel an activity.
     */
    public function cancel(int $id, User $user): MinistryActivity
    {
        $activity = MinistryActivity::findOrFail($id);

        if ($activity->status === 'completed' || $activity->status === 'cancelled') {
            throw new Exception("Cannot cancel a completed or already cancelled activity.");
        }

        $activity->update([
            'status' => 'cancelled',
            'updated_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'activity_cancelled',
            "Cancelled activity '{$activity->title}'",
            null,
            $activity->toArray(),
            $activity,
            $activity->church_id,
            $activity->diocese_id
        );

        return $activity;
    }
}
