<?php

namespace App\Services;

use App\Models\CertificateSequence;
use Illuminate\Support\Facades\DB;

class CertificateNumberService
{
    protected static array $typeMap = [
        'membership' => 'MEM',
        'baptism' => 'BAP',
        'marriage' => 'MAR',
        'death' => 'DTH',
        'recommendation' => 'REC',
        'no_objection' => 'NOC',
        'course_completion' => 'CRS',
        'custom' => 'CST',
    ];

    public function generate(int $dioceseId, string $certificateType, ?int $year = null): string
    {
        $year = $year ?? (int)date('Y');
        $typeCode = self::$typeMap[strtolower($certificateType)] ?? 'CST';

        return DB::transaction(function () use ($dioceseId, $typeCode, $year) {
            $sequence = CertificateSequence::where('diocese_id', $dioceseId)
                ->where('certificate_type', $typeCode)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = CertificateSequence::create([
                    'diocese_id' => $dioceseId,
                    'certificate_type' => $typeCode,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $nextNumber = $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            $paddedNumber = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            return "MSOC-EU-{$typeCode}-{$year}-{$paddedNumber}";
        });
    }
}
