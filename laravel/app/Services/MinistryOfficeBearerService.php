<?php

namespace App\Services;

use App\Models\MinistryOfficeBearer;
use App\Models\MinistryUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use App\Services\AuditLogService;

class MinistryOfficeBearerService
{
    /**
     * Assign a member or priest as an office bearer in a unit.
     */
    public function assign(array $data, User $user): MinistryOfficeBearer
    {
        $unit = MinistryUnit::findOrFail($data['ministry_unit_id']);
        
        $roleCategory = $data['role_category'];
        $validCategories = ['president', 'vice_president', 'secretary', 'joint_secretary', 'treasurer', 'coordinator', 'committee_member', 'advisor', 'auditor', 'other'];
        if (!in_array($roleCategory, $validCategories)) {
            throw new Exception("Invalid role category. Must be one of: " . implode(', ', $validCategories));
        }

        // End any existing active office bearer in the same unit with the same role category/title (if specified)
        MinistryOfficeBearer::where('ministry_unit_id', $unit->id)
            ->where('role_category', $roleCategory)
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'end_date' => $data['start_date'] ?? Carbon::today(),
                'updated_by' => $user->id,
            ]);

        $officeBearer = MinistryOfficeBearer::create([
            'ministry_unit_id' => $unit->id,
            'member_id' => $data['member_id'] ?? null,
            'priest_id' => $data['priest_id'] ?? null,
            'external_name' => $data['external_name'] ?? null,
            'role_title' => $data['role_title'],
            'role_category' => $roleCategory,
            'start_date' => $data['start_date'] ?? Carbon::today(),
            'status' => 'active',
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $user->id,
        ]);

        // Sync to unit table columns if matching key roles
        if ($roleCategory === 'secretary') {
            $unit->update(['secretary_member_id' => $officeBearer->member_id]);
        } elseif ($roleCategory === 'treasurer') {
            $unit->update(['treasurer_member_id' => $officeBearer->member_id]);
        } elseif ($roleCategory === 'coordinator') {
            $unit->update(['coordinator_member_id' => $officeBearer->member_id]);
        } elseif ($roleCategory === 'president' && $officeBearer->priest_id) {
            $unit->update(['president_priest_id' => $officeBearer->priest_id]);
        }

        AuditLogService::log(
            'ministry',
            'office_bearer_assigned',
            "Assigned {$officeBearer->role_title} ({$roleCategory}) in unit {$unit->unit_name}",
            null,
            $officeBearer->toArray(),
            $officeBearer,
            $unit->church_id,
            $unit->diocese_id
        );

        return $officeBearer;
    }

    /**
     * End the term of an office bearer.
     */
    public function endTerm(int $id, User $user, string $endDate = null): MinistryOfficeBearer
    {
        $bearer = MinistryOfficeBearer::findOrFail($id);

        if ($bearer->status !== 'active') {
            throw new Exception("Office bearer is not active.");
        }

        $bearer->update([
            'status' => 'ended',
            'end_date' => $endDate ?? Carbon::today(),
            'updated_by' => $user->id,
        ]);

        $unit = $bearer->unit;
        // Reset matching key role in unit table columns
        if ($bearer->role_category === 'secretary' && $unit->secretary_member_id === $bearer->member_id) {
            $unit->update(['secretary_member_id' => null]);
        } elseif ($bearer->role_category === 'treasurer' && $unit->treasurer_member_id === $bearer->member_id) {
            $unit->update(['treasurer_member_id' => null]);
        } elseif ($bearer->role_category === 'coordinator' && $unit->coordinator_member_id === $bearer->member_id) {
            $unit->update(['coordinator_member_id' => null]);
        } elseif ($bearer->role_category === 'president' && $unit->president_priest_id === $bearer->priest_id) {
            $unit->update(['president_priest_id' => null]);
        }

        AuditLogService::log(
            'ministry',
            'office_bearer_term_ended',
            "Ended term for {$bearer->role_title} in unit {$unit->unit_name}",
            null,
            $bearer->toArray(),
            $bearer,
            $unit->church_id,
            $unit->diocese_id
        );

        return $bearer;
    }
}
