<?php

namespace App\Services;

use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAcademicYear;
use App\Models\SundaySchoolClass;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolEnrollmentService
{
    /**
     * Enroll a student in a Sunday School class.
     */
    public function enroll(array $data, User $creator): SundaySchoolStudent
    {
        $memberId = $data['member_id'];
        $classId = $data['class_id'];
        $academicYearId = $data['academic_year_id'];

        $member = Member::findOrFail($memberId);
        $class = SundaySchoolClass::findOrFail($classId);
        $academicYear = SundaySchoolAcademicYear::findOrFail($academicYearId);

        // 1. Validate that the member is approved
        if (!$member->approved_at || $member->membership_status !== 'active') {
            throw new Exception('Only approved members can be enrolled in Sunday School.');
        }

        // 2. Prevent duplicate active/pending enrollments
        $exists = SundaySchoolStudent::where('member_id', $memberId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('enrollment_status', ['pending', 'active'])
            ->exists();

        if ($exists) {
            throw new Exception('This student already has an active or pending enrollment for the selected academic year.');
        }

        // 3. Age validation based on Level thresholds
        $level = $class->level;
        $age = $member->age;
        if ($level->minimum_age && $age < $level->minimum_age) {
            throw new Exception("Student age ({$age}) is below the minimum age ({$level->minimum_age}) for this class level.");
        }
        if ($level->maximum_age && $age > $level->maximum_age) {
            throw new Exception("Student age ({$age}) exceeds the maximum age ({$level->maximum_age}) for this class level.");
        }

        // 4. Capacity validation
        if ($class->max_students) {
            $currentCount = SundaySchoolStudent::where('class_id', $class->id)
                ->whereIn('enrollment_status', ['pending', 'active'])
                ->count();
            if ($currentCount >= $class->max_students) {
                throw new Exception('This class has reached its maximum enrollment capacity.');
            }
        }

        // 5. Parent-child mapping lookup
        $parentMemberId = $data['parent_member_id'] ?? $this->determineParentMemberId($member);

        return DB::transaction(function () use ($data, $member, $class, $academicYear, $parentMemberId, $creator) {
            $student = SundaySchoolStudent::create([
                'diocese_id' => $class->diocese_id,
                'church_id' => $class->church_id,
                'academic_year_id' => $academicYear->id,
                'class_id' => $class->id,
                'member_id' => $member->id,
                'family_id' => $member->family_id,
                'parent_member_id' => $parentMemberId,
                'enrollment_date' => $data['enrollment_date'] ?? Carbon::today(),
                'enrollment_status' => 'pending',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $creator->id,
            ]);

            AuditLogService::log(
                'sunday_school',
                'student_enrolled',
                "Enrolled student {$member->full_name} in class {$class->class_name} (Academic Year: {$academicYear->name})",
                null,
                $student->toArray(),
                $student,
                $student->church_id,
                $student->diocese_id
            );

            return $student;
        });
    }

    /**
     * Approve a pending enrollment.
     */
    public function approve(int $id, User $approver): SundaySchoolStudent
    {
        $student = SundaySchoolStudent::findOrFail($id);

        if ($student->enrollment_status !== 'pending') {
            throw new Exception('Enrollment is not in pending status.');
        }

        DB::transaction(function () use ($student, $approver) {
            $student->update([
                'enrollment_status' => 'active',
                'approved_by' => $approver->id,
                'approved_at' => Carbon::now(),
            ]);

            AuditLogService::log(
                'sunday_school',
                'student_approved',
                "Approved enrollment for student ID {$student->id}",
                null,
                $student->toArray(),
                $student,
                $student->church_id,
                $student->diocese_id
            );
        });

        return $student;
    }

    /**
     * Discontinue an active enrollment.
     */
    public function discontinue(int $id, User $updater, ?string $remarks = null): SundaySchoolStudent
    {
        $student = SundaySchoolStudent::findOrFail($id);

        DB::transaction(function () use ($student, $updater, $remarks) {
            $student->update([
                'enrollment_status' => 'discontinued',
                'remarks' => $remarks ?? $student->remarks,
            ]);

            AuditLogService::log(
                'sunday_school',
                'student_discontinued',
                "Discontinued enrollment for student ID {$student->id}",
                null,
                $student->toArray(),
                $student,
                $student->church_id,
                $student->diocese_id
            );
        });

        return $student;
    }

    /**
     * Determine default parent member ID for a child.
     */
    public function determineParentMemberId(Member $student): ?int
    {
        $family = $student->family;
        if (!$family) {
            return null;
        }

        // 1. Check family head if child relationship to head suggests parent/child structure
        $head = $family->headMember;
        if ($head && $head->id !== $student->id) {
            if (in_array(strtolower($student->relationship_to_head), ['son', 'daughter', 'child'])) {
                return $head->id;
            }
        }

        // 2. Look for any adult member (age >= 18) in the same family marked as parent/guardian
        $parents = $family->members()
            ->where('id', '!=', $student->id)
            ->get()
            ->filter(function ($m) {
                return $m->age >= 18 && in_array(strtolower($m->relationship_to_head), ['head', 'spouse', 'mother', 'father', 'guardian']);
            });

        if ($parents->isNotEmpty()) {
            return $parents->first()->id;
        }

        return null;
    }

    /**
     * Verify if a parent is allowed to access a child's records.
     */
    public function verifyParentChildRelationship(Member $parent, Member $child): bool
    {
        // Check if direct parent field is mapped
        $studentRecords = SundaySchoolStudent::where('member_id', $child->id)
            ->where('parent_member_id', $parent->id)
            ->exists();
        if ($studentRecords) {
            return true;
        }

        // Check if they belong to the same family and parent has parental role
        if ($child->family_id && $child->family_id === $parent->family_id) {
            if (in_array(strtolower($parent->relationship_to_head), ['head', 'spouse', 'mother', 'father', 'guardian'])) {
                return true;
            }
        }

        return false;
    }
}
