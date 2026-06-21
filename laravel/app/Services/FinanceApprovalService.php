<?php

namespace App\Services;

use App\Models\FinanceApproval;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FinanceApprovalService
{
    /**
     * Create a new finance approval request.
     */
    public static function createApprovalRequest(Model $record, string $approvalType, User $user): FinanceApproval
    {
        return FinanceApproval::create([
            'diocese_id' => $record->diocese_id,
            'church_id' => $record->church_id,
            'approvable_type' => get_class($record),
            'approvable_id' => $record->id,
            'approval_type' => $approvalType,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Resolve (approve, reject, or cancel) an approval request.
     */
    public static function resolveApprovalRequest(Model $record, string $status, ?string $remarks, ?string $rejectionReason, User $user): ?FinanceApproval
    {
        $approval = FinanceApproval::where('approvable_type', get_class($record))
            ->where('approvable_id', $record->id)
            ->where('status', 'pending')
            ->first();

        if (!$approval) {
            return null; // no pending approval found
        }

        return DB::transaction(function () use ($approval, $status, $remarks, $rejectionReason, $user) {
            $updateData = [
                'status' => $status,
                'remarks' => $remarks,
            ];

            if ($status === 'approved') {
                $updateData['approved_by'] = $user->id;
                $updateData['approved_at'] = now();
            } elseif ($status === 'rejected') {
                $updateData['rejected_by'] = $user->id;
                $updateData['rejected_at'] = now();
                $updateData['rejection_reason'] = $rejectionReason;
            }

            $approval->update($updateData);

            AuditLogService::log(
                'Finance',
                'Approval Request Resolved',
                "Resolved approval request ID {$approval->id} as {$status}",
                null,
                $approval->toArray(),
                $approval,
                $approval->church_id,
                $approval->diocese_id
            );

            return $approval;
        });
    }
}
