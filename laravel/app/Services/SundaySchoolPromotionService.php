<?php

namespace App\Services;

use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolAcademicYear;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolPromotionService
{
    /**
     * Promote a student to a new class level in a different academic year.
     */
    public function promote(int $studentId, int $targetClassId, int $targetAcademicYearId, User $updater): SundaySchoolStudent
    {
        $student = SundaySchoolStudent::findOrFail($studentId);
        $targetClass = SundaySchoolClass::findOrFail($targetClassId);
        $targetAcademicYear = SundaySchoolAcademicYear::findOrFail($targetAcademicYearId);

        // 1. Verify old enrollment is active or completed (not already promoted)
        if (in_array($student->enrollment_status, ['promoted', 'completed'])) {
            throw new Exception('Student has already been promoted or completed their course for this academic year.');
        }

        // 2. Prevent duplicate active/pending enrollment in the target academic year
        $exists = SundaySchoolStudent::where('member_id', $student->member_id)
            ->where('academic_year_id', $targetAcademicYearId)
            ->whereIn('enrollment_status', ['pending', 'active'])
            ->exists();

        if ($exists) {
            throw new Exception('Student already has an active or pending enrollment in the target academic year.');
        }

        return DB::transaction(function () use ($student, $targetClass, $targetAcademicYear, $updater) {
            $oldStatus = $student->enrollment_status;
            
            // Update old enrollment status to 'promoted'
            $student->update([
                'enrollment_status' => 'promoted',
            ]);

            // Create new enrollment for the next academic year and class
            $newStudent = SundaySchoolStudent::create([
                'diocese_id' => $targetClass->diocese_id,
                'church_id' => $targetClass->church_id,
                'academic_year_id' => $targetAcademicYear->id,
                'class_id' => $targetClass->id,
                'member_id' => $student->member_id,
                'family_id' => $student->family_id,
                'parent_member_id' => $student->parent_member_id,
                'enrollment_date' => Carbon::today(),
                'enrollment_status' => 'active', // Auto-approved on admin-led promotion
                'remarks' => "Promoted from class ID {$student->class_id} (Academic Year ID {$student->academic_year_id})",
                'created_by' => $updater->id,
                'approved_by' => $updater->id,
                'approved_at' => Carbon::now(),
            ]);

            AuditLogService::log(
                'sunday_school',
                'student_promoted',
                "Promoted student ID {$student->id} to class {$targetClass->class_name} (AY: {$targetAcademicYear->name})",
                ['old_student_record' => $student->toArray()],
                $newStudent->toArray(),
                $newStudent,
                $newStudent->church_id,
                $newStudent->diocese_id
            );

            return $newStudent;
        });
    }
}
