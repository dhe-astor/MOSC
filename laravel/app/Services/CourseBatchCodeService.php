<?php

namespace App\Services;

use App\Models\CourseBatch;
use Illuminate\Support\Facades\DB;

class CourseBatchCodeService
{
    public static function generateCode(string $courseType, int $year): string
    {
        $prefix = self::getTypePrefix($courseType);

        return DB::transaction(function () use ($prefix, $year) {
            // Locking the rows matching the pattern to prevent concurrent generation of the same code
            $latestBatch = CourseBatch::where('batch_code', 'like', "MSOC-COURSE-{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderBy('batch_code', 'desc')
                ->first();

            $nextNumber = 1;
            if ($latestBatch && preg_match('/-(\d+)$/', $latestBatch->batch_code, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }

            $sequenceStr = str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);

            return "MSOC-COURSE-{$prefix}-{$year}-{$sequenceStr}";
        });
    }

    protected static function getTypePrefix(string $courseType): string
    {
        switch ($courseType) {
            case 'pre_marriage':
                return 'PREMAR';
            case 'post_marriage':
                return 'POSTMAR';
            case 'syriac_language':
                return 'SYRIAC';
            case 'bible_course':
                return 'BIBLE';
            case 'liturgical_course':
                return 'LITURG';
            case 'altar_assistants':
                return 'ALTAR';
            default:
                return 'OTHER';
        }
    }
}
