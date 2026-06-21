<?php

namespace App\Services;

use App\Models\MinistryServiceLog;
use App\Models\MinistryUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use App\Services\AuditLogService;

class MinistryServiceLogService
{
    /**
     * Submit a new volunteer/charity service log.
     */
    public function submit(array $data, User $user): MinistryServiceLog
    {
        $unit = MinistryUnit::findOrFail($data['ministry_unit_id']);

        $log = MinistryServiceLog::create([
            'diocese_id' => $unit->diocese_id,
            'church_id' => $unit->church_id,
            'ministry_unit_id' => $unit->id,
            'member_id' => $data['member_id'] ?? null,
            'activity_id' => $data['activity_id'] ?? null,
            'service_type' => $data['service_type'], // charity, volunteering, hospital_visit, home_visit, food_support, fundraising_support, event_support, other
            'service_date' => Carbon::parse($data['service_date']),
            'hours_count' => $data['hours_count'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'submitted',
            'created_by' => $user->id,
        ]);

        AuditLogService::log(
            'ministry',
            'service_log_submitted',
            "Submitted service log for unit {$unit->unit_name} by {$user->name}",
            null,
            $log->toArray(),
            $log,
            $unit->church_id,
            $unit->diocese_id
        );

        return $log;
    }

    /**
     * Verify a submitted service log.
     */
    public function verify(int $id, User $user): MinistryServiceLog
    {
        $log = MinistryServiceLog::findOrFail($id);

        if ($log->status !== 'submitted') {
            throw new Exception("Service log is not in submitted state.");
        }

        $log->update([
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => Carbon::now(),
        ]);

        AuditLogService::log(
            'ministry',
            'service_log_verified',
            "Verified service log ID {$log->id} for unit ID {$log->ministry_unit_id}",
            null,
            $log->toArray(),
            $log,
            $log->church_id,
            $log->diocese_id
        );

        return $log;
    }

    /**
     * Reject a service log.
     */
    public function reject(int $id, User $user, string $remarks = null): MinistryServiceLog
    {
        $log = MinistryServiceLog::findOrFail($id);

        if ($log->status !== 'submitted') {
            throw new Exception("Service log is not in submitted state.");
        }

        $log->update([
            'status' => 'rejected',
            'description' => $log->description . ($remarks ? " [Rejection reason: {$remarks}]" : ""),
        ]);

        AuditLogService::log(
            'ministry',
            'service_log_rejected',
            "Rejected service log ID {$log->id} for unit ID {$log->ministry_unit_id}",
            null,
            ['remarks' => $remarks],
            $log,
            $log->church_id,
            $log->diocese_id
        );

        return $log;
    }
}
