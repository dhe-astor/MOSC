<?php

namespace App\Services;

use App\Models\SundaySchoolExam;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use App\Models\User;
use App\Services\SundaySchoolAttendanceService;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolExamService
{
    protected SundaySchoolAttendanceService $attendanceService;

    public function __construct(SundaySchoolAttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Enter or edit marks for an exam.
     */
    public function enterMarks(int $examId, array $records, User $user): array
    {
        $exam = SundaySchoolExam::findOrFail($examId);
        $class = SundaySchoolClass::findOrFail($exam->class_id);

        // Verify if teacher has access
        if (!$this->attendanceService->checkTeacherAccess($user, $class)) {
            throw new Exception('Access Denied: You do not have permission to enter marks for this exam.');
        }

        $isAdmin = $user->hasRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin']);
        $results = [];

        DB::transaction(function () use ($exam, $records, $user, $isAdmin, &$results) {
            foreach ($records as $record) {
                $studentId = $record['student_id'];
                $marksObtained = $record['marks_obtained'];
                $remarks = $record['remarks'] ?? null;

                // Validate student belongs to the class
                $student = SundaySchoolStudent::where('id', $studentId)
                    ->where('class_id', $exam->class_id)
                    ->firstOrFail();

                if ($marksObtained > $exam->max_marks) {
                    throw new Exception("Marks obtained ({$marksObtained}) cannot exceed max marks ({$exam->max_marks}).");
                }

                // Calculate pass/fail status
                $resultStatus = 'pending';
                if ($exam->pass_marks !== null) {
                    $resultStatus = $marksObtained >= $exam->pass_marks ? 'pass' : 'fail';
                }

                // Calculate grade (A: 90+, B: 80+, C: 70+, D: 50+, F: <50)
                $percentage = ($marksObtained / $exam->max_marks) * 100;
                $grade = $this->calculateGrade($percentage);

                // Check for existing marks and lock checks
                $existingMark = SundaySchoolMark::where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->first();

                if ($existingMark && $existingMark->verified_at !== null) {
                    // Mark is verified. Only admins can edit/correct.
                    if (!$isAdmin) {
                        throw new Exception("Access Denied: Verified marks for student ID {$student->id} are locked and cannot be edited.");
                    }

                    // Admin correction - log correction
                    $oldValues = $existingMark->toArray();
                    $existingMark->update([
                        'marks_obtained' => $marksObtained,
                        'grade' => $grade,
                        'result_status' => $resultStatus,
                        'remarks' => $remarks,
                        'entered_by' => $user->id,
                    ]);

                    AuditLogService::log(
                        'sunday_school',
                        'marks_corrected',
                        "Admin corrected verified marks for student ID {$student->id} in exam ID {$exam->id}",
                        $oldValues,
                        $existingMark->toArray(),
                        $existingMark,
                        $exam->church_id,
                        $exam->diocese_id
                    );

                    $results[] = $existingMark;
                } else {
                    // Create or update unverified mark
                    $mark = SundaySchoolMark::updateOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'marks_obtained' => $marksObtained,
                            'grade' => $grade,
                            'result_status' => $resultStatus,
                            'remarks' => $remarks,
                            'entered_by' => $user->id,
                        ]
                    );

                    $results[] = $mark;
                }
            }
        });

        return $results;
    }

    /**
     * Verify marks for a given exam.
     */
    public function verifyMarks(int $examId, User $verifier): void
    {
        // Only Sunday School Admin, Diocese Admin, Super Admin, and Priest can verify marks
        if (!$verifier->hasRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            throw new Exception('Access Denied: You do not have permission to verify marks.');
        }

        $exam = SundaySchoolExam::findOrFail($examId);

        DB::transaction(function () use ($exam, $verifier) {
            SundaySchoolMark::where('exam_id', $exam->id)
                ->whereNull('verified_at')
                ->update([
                    'verified_by' => $verifier->id,
                    'verified_at' => Carbon::now(),
                ]);

            // Fetch students to notify
            $marks = SundaySchoolMark::where('exam_id', $exam->id)->with('student.member')->get();
            foreach ($marks as $mark) {
                if ($mark->student) {
                    \App\Services\NotificationTriggerService::triggerSundaySchoolMarksPublished($mark->student, $exam);
                }
            }

            AuditLogService::log(
                'sunday_school',
                'marks_verified',
                "Verified all marks for exam ID {$exam->id}",
                null,
                ['exam_id' => $exam->id],
                $exam,
                $exam->church_id,
                $exam->diocese_id
            );
        });
    }

    /**
     * Helper to compute grades from percentages.
     */
    public function calculateGrade(float $percentage): string
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }
}
