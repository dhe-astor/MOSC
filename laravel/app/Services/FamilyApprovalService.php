<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyChurchHistory;
use App\Models\User;
use App\Services\FamilyCodeService;
use App\Services\MemberApprovalService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FamilyApprovalService
{
    protected $codeService;
    protected $memberApprovalService;

    public function __construct(FamilyCodeService $codeService, MemberApprovalService $memberApprovalService)
    {
        $this->codeService = $codeService;
        $this->memberApprovalService = $memberApprovalService;
    }

    /**
     * Approve a family registration.
     */
    public function approve(Family $family, User $user): string
    {
        if ($family->membership_status === 'active') {
            return $family->family_code;
        }

        return DB::transaction(function () use ($family, $user) {
            $oldValues = $family->toArray();

            $family->membership_status = 'active';
            $family->approved_by = $user->id;
            $family->approved_at = Carbon::now();
            $family->registered_date = $family->registered_date ?: Carbon::today();
            $family->save();

            // Generate unique family code
            $code = $this->codeService->generateCode($family);

            // Create initial family church history record
            FamilyChurchHistory::create([
                'family_id' => $family->id,
                'church_id' => $family->church_id,
                'start_date' => $family->registered_date ?: Carbon::today(),
                'status' => 'active',
                'remarks' => 'Initial parish registration approved.',
                'created_by' => $user->id,
            ]);

            // Auto-approve pending members in this family
            $pendingMembers = $family->members()->where('membership_status', 'pending')->get();
            foreach ($pendingMembers as $member) {
                $this->memberApprovalService->approve($member, $user);
            }

            AuditLogService::log(
                'families',
                'family_approved',
                "Family '{$family->family_name}' was approved and assigned code '{$code}'",
                $oldValues,
                $family->toArray(),
                $family,
                $family->church_id
            );

            return $code;
        });
    }

    /**
     * Reject a family registration.
     */
    public function reject(Family $family, User $user): void
    {
        DB::transaction(function () use ($family, $user) {
            $oldValues = $family->toArray();

            $family->membership_status = 'inactive';
            $family->save();
            $family->delete(); // Soft delete rejected family

            // Also reject and soft delete pending members
            $pendingMembers = $family->members()->where('membership_status', 'pending')->get();
            foreach ($pendingMembers as $member) {
                $this->memberApprovalService->reject($member, $user);
            }

            AuditLogService::log(
                'families',
                'family_rejected',
                "Family '{$family->family_name}' was rejected and soft-deleted",
                $oldValues,
                $family->toArray(),
                $family,
                $family->church_id
            );
        });
    }
}
