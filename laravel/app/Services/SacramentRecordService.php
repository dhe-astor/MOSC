<?php

namespace App\Services;

use App\Models\Sacrament;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class SacramentRecordService
{
    public function create(array $data): Sacrament
    {
        $user = Auth::user();
        $data['created_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'draft';

        $sacrament = Sacrament::create($data);

        AuditLogService::log(
            'sacraments',
            'sacrament_created',
            "Sacrament record ({$sacrament->sacrament_type}) created for member ID: {$sacrament->member_id}",
            null,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function update(Sacrament $sacrament, array $data): Sacrament
    {
        $oldValues = $sacrament->toArray();
        $sacrament->update($data);

        AuditLogService::log(
            'sacraments',
            'sacrament_updated',
            "Sacrament record ID: {$sacrament->id} updated",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function submit(Sacrament $sacrament): Sacrament
    {
        $oldValues = $sacrament->toArray();
        $sacrament->update(['status' => 'submitted']);

        AuditLogService::log(
            'sacraments',
            'sacrament_submitted',
            "Sacrament record ID: {$sacrament->id} submitted for verification",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function verify(Sacrament $sacrament): Sacrament
    {
        $user = Auth::user();
        $oldValues = $sacrament->toArray();
        $sacrament->update([
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        AuditLogService::log(
            'sacraments',
            'sacrament_verified',
            "Sacrament record ID: {$sacrament->id} verified by priest",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function approve(Sacrament $sacrament): Sacrament
    {
        $user = Auth::user();
        $oldValues = $sacrament->toArray();
        $sacrament->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditLogService::log(
            'sacraments',
            'sacrament_approved',
            "Sacrament record ID: {$sacrament->id} approved by vicar",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function reject(Sacrament $sacrament, string $reason): Sacrament
    {
        $oldValues = $sacrament->toArray();
        $sacrament->update([
            'status' => 'rejected',
            'remarks' => trim(($sacrament->remarks ?? '') . "\nRejection Reason: " . $reason),
        ]);

        AuditLogService::log(
            'sacraments',
            'sacrament_rejected',
            "Sacrament record ID: {$sacrament->id} rejected with reason: {$reason}",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }

    public function archive(Sacrament $sacrament): Sacrament
    {
        $oldValues = $sacrament->toArray();
        $sacrament->update(['status' => 'archived']);

        AuditLogService::log(
            'sacraments',
            'sacrament_archived',
            "Sacrament record ID: {$sacrament->id} archived",
            $oldValues,
            $sacrament->toArray(),
            $sacrament,
            $sacrament->church_id,
            $sacrament->diocese_id
        );

        return $sacrament;
    }
}
