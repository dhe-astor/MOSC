<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Church;
use Illuminate\Support\Facades\DB;

class FamilyCodeService
{
    /**
     * Generate and save a unique family code for an approved family.
     */
    public function generateCode(Family $family): string
    {
        if ($family->family_code) {
            return $family->family_code;
        }

        $church = Church::findOrFail($family->church_id);
        $prefixCode = $church->membership_code_prefix ?: strtoupper(substr($church->short_name, 0, 3));
        $prefix = 'MSOC-' . $prefixCode . '-F-';

        return DB::transaction(function () use ($family, $prefix) {
            // Lock records matching the prefix to prevent concurrent duplicate allocation
            $maxCode = Family::where('family_code', 'like', $prefix . '%')
                ->lockForUpdate()
                ->max('family_code');

            $nextNumber = 1;
            if ($maxCode) {
                $parts = explode('-', $maxCode);
                $lastPart = end($parts);
                $nextNumber = intval($lastPart) + 1;
            }

            $newCode = $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $family->family_code = $newCode;
            $family->save();

            return $newCode;
        });
    }
}
