<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MinistryOrganization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use App\Services\AuditLogService;

class MinistryEligibilityService
{
    /**
     * Check if a member is eligible for a ministry organization.
     * Throws an exception if not eligible and override is not allowed/valid.
     */
    public function validateEligibility(Member $member, MinistryOrganization $org, User $user, bool $override = false): bool
    {
        $rules = $org->eligibility_rules ?? [];
        $errors = [];

        // 1. Age Check
        if ($member->date_of_birth) {
            $age = Carbon::parse($member->date_of_birth)->age;
            
            $minAge = $rules['min_age'] ?? null;
            $maxAge = $rules['max_age'] ?? null;

            if ($minAge !== null && $age < $minAge) {
                $errors[] = "Member age ({$age}) is less than the minimum required age of {$minAge}.";
            }

            if ($maxAge !== null && $age > $maxAge) {
                $errors[] = "Member age ({$age}) is greater than the maximum allowed age of {$maxAge}.";
            }
        } else {
            // If date of birth is missing but age limits exist
            if (!empty($rules['min_age']) || !empty($rules['max_age'])) {
                $errors[] = "Member date of birth is missing, cannot verify age eligibility.";
            }
        }

        // 2. Gender Check
        $genderRule = $rules['gender'] ?? null;
        if ($genderRule !== null) {
            $expectedGender = strtolower($genderRule) === 'f' ? 'female' : (strtolower($genderRule) === 'm' ? 'male' : strtolower($genderRule));
            if (strtolower($member->gender) !== $expectedGender) {
                $errors[] = "Member gender ({$member->gender}) does not match the organization requirement ({$expectedGender}).";
            }
        }

        if (empty($errors)) {
            return true;
        }

        // Handle Override
        if ($override) {
            $hasOverridePermission = $user->hasRole(['Super Admin', 'Diocese Admin']) || 
                ($org->organization_type === 'youth_association' && $user->hasPermissionTo('manage_youth')) ||
                ($org->organization_type === 'marthamariyam_samajam' && $user->hasPermissionTo('manage_marthamariyam'));

            if ($hasOverridePermission) {
                // Log audit override
                AuditLogService::log(
                    'ministry',
                    'eligibility_override',
                    "Eligibility check overridden for member {$member->full_name} in {$org->name} by {$user->name}",
                    null,
                    ['errors' => $errors, 'overridden_by' => $user->id],
                    $member,
                    $member->church_id,
                    $member->diocese_id
                );
                return true;
            }
        }

        throw new Exception(implode(' ', $errors));
    }
}
