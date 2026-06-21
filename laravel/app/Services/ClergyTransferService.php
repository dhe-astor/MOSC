<?php

namespace App\Services;

use App\Models\PriestTransferRequest;
use App\Models\PriestChurchAssignment;
use App\Models\PriestProfile;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class ClergyTransferService
{
    /**
     * Create a transfer request.
     */
    public static function createTransferRequest(array $data, User $user): PriestTransferRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $priestProfile = PriestProfile::findOrFail($data['priest_profile_id']);
            
            // Find current active assignment at from_church_id if not specified
            $fromChurchId = $data['from_church_id'] ?? null;
            $fromAssignmentId = $data['from_assignment_id'] ?? null;

            if ($fromChurchId && !$fromAssignmentId) {
                $activeAssignment = PriestChurchAssignment::where('priest_profile_id', $priestProfile->id)
                    ->where('church_id', $fromChurchId)
                    ->where('status', 'active')
                    ->first();
                $fromAssignmentId = $activeAssignment?->id;
            }

            return PriestTransferRequest::create(array_merge($data, [
                'diocese_id' => $priestProfile->diocese_id,
                'from_assignment_id' => $fromAssignmentId,
                'status' => 'draft',
                'requested_by' => $user->id,
            ]));
        });
    }

    /**
     * Approve transfer request.
     */
    public static function approveTransferRequest(PriestTransferRequest $request, User $user): PriestTransferRequest
    {
        if ($request->status !== 'draft') {
            throw new Exception("Only draft transfer requests can be approved.");
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $request;
    }

    /**
     * Cancel transfer request.
     */
    public static function cancelTransferRequest(PriestTransferRequest $request, User $user): PriestTransferRequest
    {
        $request->update([
            'status' => 'cancelled',
        ]);

        return $request;
    }

    /**
     * Complete a transfer request manually or scheduled.
     */
    public static function completeTransfer(PriestTransferRequest $request, User $user): PriestTransferRequest
    {
        if (!in_array($request->status, ['approved', 'scheduled'])) {
            throw new Exception("Only approved or scheduled transfer requests can be completed.");
        }

        return DB::transaction(function () use ($request, $user) {
            $priestProfile = $request->priestProfile;

            // 1. End old assignment if transfer_type is 'transfer' or 'end_assignment'
            if (in_array($request->transfer_type, ['transfer', 'end_assignment'])) {
                if ($request->from_assignment_id) {
                    $oldAssignment = PriestChurchAssignment::find($request->from_assignment_id);
                    if ($oldAssignment && $oldAssignment->status === 'active') {
                        // End day before effective_date
                        $endDate = date('Y-m-d', strtotime($request->effective_date->toDateString() . ' - 1 day'));
                        PriestAssignmentService::endAssignment($oldAssignment, $endDate, "Transferred to another charge", $user);
                    }
                }
            }

            // 2. Start new assignment unless transfer_type is 'end_assignment'
            if ($request->transfer_type !== 'end_assignment') {
                $isPrimary = in_array($request->new_assignment_role, ['vicar', 'priest_in_charge']);
                
                PriestAssignmentService::assignPriest([
                    'priest_profile_id' => $request->priest_profile_id,
                    'church_id' => $request->to_church_id,
                    'assignment_role' => $request->new_assignment_role,
                    'start_date' => $request->effective_date->toDateString(),
                    'is_primary' => $isPrimary,
                    'status' => 'active',
                    'appointment_reference' => $request->appointment_reference,
                    'appointment_document_path' => $request->appointment_document_path,
                    'notes' => $request->notes,
                ], $user);
            }

            $request->update([
                'status' => 'completed',
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Run the batch process for scheduled transfers.
     */
    public static function processScheduledTransfers(User $systemUser): int
    {
        $today = date('Y-m-d');
        
        $pendingTransfers = PriestTransferRequest::whereIn('status', ['approved', 'scheduled'])
            ->where('effective_date', '<=', $today)
            ->get();

        $count = 0;
        foreach ($pendingTransfers as $transfer) {
            try {
                self::completeTransfer($transfer, $systemUser);
                $count++;
            } catch (Exception $e) {
                // Log and continue
                logger()->error("Scheduled transfer failed: " . $e->getMessage(), ['transfer_id' => $transfer->id]);
            }
        }

        return $count;
    }
}
