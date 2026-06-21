<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyTransferRequest;
use App\Models\FamilyChurchHistory;
use App\Models\User;
use App\Models\Church;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FamilyTransferService
{
    /**
     * Create a new family transfer request.
     */
    public function createRequest(Family $family, int $toChurchId, User $user, ?string $reason): FamilyTransferRequest
    {
        if ($family->church_id === $toChurchId) {
            throw new \InvalidArgumentException('Source and target churches must be different.');
        }

        // Check if there is already a pending transfer request for this family
        $exists = FamilyTransferRequest::where('family_id', $family->id)
            ->whereIn('status', ['requested', 'source_approved', 'diocese_approved', 'target_accepted'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('A pending transfer request already exists for this family.');
        }

        $request = FamilyTransferRequest::create([
            'family_id' => $family->id,
            'from_church_id' => $family->church_id,
            'to_church_id' => $toChurchId,
            'requested_by' => $user->id,
            'status' => 'requested',
            'reason' => $reason,
        ]);

        AuditLogService::log(
            'families',
            'family_transfer_requested',
            "Family '{$family->family_name}' transfer request created from church ID {$family->church_id} to {$toChurchId}",
            null,
            $request->toArray(),
            $family,
            $family->church_id
        );

        return $request;
    }

    /**
     * Source church priest approves the transfer.
     */
    public function sourceApprove(FamilyTransferRequest $request, User $user, ?string $remarks): void
    {
        if ($request->status !== 'requested') {
            throw new \InvalidArgumentException('Transfer request is not in requested status.');
        }

        $request->status = 'source_approved';
        $request->source_approved_by = $user->id;
        $request->source_approved_at = Carbon::now();
        $request->remarks = $remarks;
        $request->save();

        $family = Family::findOrFail($request->family_id);

        AuditLogService::log(
            'families',
            'family_transfer_source_approved',
            "Source priest approved transfer request #{$request->id} for family '{$family->family_name}'",
            null,
            $request->toArray(),
            $family,
            $request->from_church_id
        );
    }

    /**
     * Diocese admin approves the transfer.
     */
    public function dioceseApprove(FamilyTransferRequest $request, User $user, ?string $remarks): void
    {
        if ($request->status !== 'source_approved') {
            throw new \InvalidArgumentException('Transfer request must be approved by source church first.');
        }

        $request->status = 'diocese_approved';
        $request->diocese_approved_by = $user->id;
        $request->diocese_approved_at = Carbon::now();
        $request->remarks = $remarks;
        $request->save();

        $family = Family::findOrFail($request->family_id);

        AuditLogService::log(
            'families',
            'family_transfer_diocese_approved',
            "Diocese approved transfer request #{$request->id} for family '{$family->family_name}'",
            null,
            $request->toArray(),
            $family,
            $request->from_church_id
        );
    }

    /**
     * Target church accepts the transfer.
     */
    public function targetAccept(FamilyTransferRequest $request, User $user, ?string $remarks): void
    {
        $requireDiocese = config('settings.require_diocese_transfer_approval', true);
        $expectedStatus = $requireDiocese ? 'diocese_approved' : 'source_approved';

        if ($request->status !== $expectedStatus) {
            throw new \InvalidArgumentException("Transfer request cannot be accepted from its current status: '{$request->status}'. Expected status: '{$expectedStatus}'.");
        }

        $request->status = 'target_accepted';
        $request->target_accepted_by = $user->id;
        $request->target_accepted_at = Carbon::now();
        $request->remarks = $remarks;
        $request->save();

        $family = Family::findOrFail($request->family_id);

        AuditLogService::log(
            'families',
            'family_transfer_target_accepted',
            "Target church accepted transfer request #{$request->id} for family '{$family->family_name}'",
            null,
            $request->toArray(),
            $family,
            $request->to_church_id
        );
    }

    /**
     * Complete the transfer and execute the database updates.
     */
    public function complete(FamilyTransferRequest $request, User $user): void
    {
        if ($request->status !== 'target_accepted') {
            throw new \InvalidArgumentException('Transfer request must be accepted by target church before completion.');
        }

        DB::transaction(function () use ($request, $user) {
            $request->status = 'completed';
            $request->save();

            $family = Family::findOrFail($request->family_id);
            $fromChurch = Church::findOrFail($request->from_church_id);
            $toChurch = Church::findOrFail($request->to_church_id);

            // Update family church pointer
            $oldFamilyData = $family->toArray();
            $family->church_id = $request->to_church_id;
            $family->membership_status = 'active';
            $family->save();

            // Update all members to target church
            $family->members()->update(['church_id' => $request->to_church_id]);

            // Close old history
            FamilyChurchHistory::where('family_id', $family->id)
                ->where('status', 'active')
                ->update([
                    'end_date' => Carbon::today(),
                    'status' => 'transferred',
                    'remarks' => "Transferred to {$toChurch->name}"
                ]);

            // Create new history
            FamilyChurchHistory::create([
                'family_id' => $family->id,
                'church_id' => $request->to_church_id,
                'start_date' => Carbon::today(),
                'status' => 'active',
                'remarks' => "Transferred from {$fromChurch->name}",
                'created_by' => $user->id,
            ]);

            AuditLogService::log(
                'families',
                'family_transfer_completed',
                "Family '{$family->family_name}' transfer completed from '{$fromChurch->name}' to '{$toChurch->name}'",
                $oldFamilyData,
                $family->toArray(),
                $family,
                $request->to_church_id
            );
        });
    }

    /**
     * Reject transfer request.
     */
    public function reject(FamilyTransferRequest $request, User $user, ?string $reason): void
    {
        if (in_array($request->status, ['completed', 'rejected', 'cancelled'])) {
            throw new \InvalidArgumentException('This request is already resolved.');
        }

        $request->status = 'rejected';
        $request->remarks = $reason;
        $request->save();

        $family = Family::findOrFail($request->family_id);

        AuditLogService::log(
            'families',
            'family_transfer_rejected',
            "Transfer request #{$request->id} for family '{$family->family_name}' was rejected",
            null,
            $request->toArray(),
            $family,
            $request->from_church_id
        );
    }
}
