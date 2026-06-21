<?php

namespace App\Services;

use App\Models\ReceiptSequence;
use Illuminate\Support\Facades\DB;

class ReceiptNumberService
{
    /**
     * Generate next receipt number using database row locking.
     */
    public static function generateNextNumber(int $dioceseId, ?int $churchId = null, ?int $year = null): string
    {
        $year = $year ?? (int)date('Y');
        $targetChurchId = $churchId;

        $prefix = 'DIO';
        if ($targetChurchId) {
            $church = DB::table('churches')->where('id', $targetChurchId)->first();
            if ($church) {
                $prefix = $church->membership_code_prefix ?: strtoupper(substr($church->short_name, 0, 3));
            }
        }

        return DB::transaction(function () use ($dioceseId, $targetChurchId, $year, $prefix) {
            // Find or create sequence under row lock
            $sequence = ReceiptSequence::where('diocese_id', $dioceseId)
                ->where('church_id', $targetChurchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = ReceiptSequence::create([
                    'diocese_id' => $dioceseId,
                    'church_id' => $targetChurchId,
                    'year' => $year,
                    'last_number' => 0
                ]);
            }

            $nextNumber = $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            $paddedNumber = str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);

            return "{$prefix}-{$year}-{$paddedNumber}";
        });
    }
}
