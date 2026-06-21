<?php

namespace App\Services;

use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAttendance;
use App\Models\SundaySchoolTeacher;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolAttendanceService
{
    /**
     * Verify if a user has access to manage/view class details or attendance.
     */
    public function checkTeacherAccess(User $user, SundaySchoolClass $class): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return true;
        }

        // Parish scoped roles
        if ($user->hasRole(['Parish Admin', 'Priest / Vicar', 'Parish Secretary'])) {
            $userChurchId = $user->active_church_id ?? $user->default_church_id;
            return $class->church_id === $userChurchId;
        }

        // Teacher check
        $teacher = SundaySchoolTeacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return false;
        }

        // Verify active assignment to the class
        return DB::table('sunday_school_class_teacher_assignments')
            ->where('class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Mark attendance for a list of students in a class.
     */
    public function markAttendance(int $classId, array $records, string $date, User $marker): array
    {
        $class = SundaySchoolClass::findOrFail($classId);

        if (!$this->checkTeacherAccess($marker, $class)) {
            throw new Exception('Access Denied: You do not have permission to mark attendance for this class.');
        }

        $formattedDate = Carbon::parse($date)->toDateString();
        $results = [];

        DB::transaction(function () use ($class, $records, $formattedDate, $marker, &$results) {
            foreach ($records as $record) {
                $studentId = $record['student_id'];
                $status = $record['status']; // present, absent, late, excused
                $remarks = $record['remarks'] ?? null;

                // Validate student belongs to class
                $student = SundaySchoolStudent::where('id', $studentId)
                    ->where('class_id', $class->id)
                    ->firstOrFail();

                $attendance = SundaySchoolAttendance::updateOrCreate(
                    [
                        'class_id' => $class->id,
                        'student_id' => $student->id,
                        'attendance_date' => $formattedDate,
                    ],
                    [
                        'status' => $status,
                        'marked_by' => $marker->id,
                        'marked_at' => Carbon::now(),
                        'remarks' => $remarks,
                    ]
                );

                $results[] = $attendance;
            }

            AuditLogService::log(
                'sunday_school',
                'attendance_marked',
                "Marked attendance for class {$class->class_name} on date {$formattedDate}",
                null,
                ['records_count' => count($records)],
                $class,
                $class->church_id,
                $class->diocese_id
            );
        });

        return $results;
    }

    /**
     * Calculate attendance percentage for a student.
     */
    public function calculateAttendancePercentage(int $studentId): float
    {
        $records = SundaySchoolAttendance::where('student_id', $studentId)->get();

        if ($records->isEmpty()) {
            return 100.00; // Default if no sessions held
        }

        $totalSessions = $records->count();
        $attendedSessions = $records->filter(function ($record) {
            return in_array($record->status, ['present', 'late']);
        })->count();

        return round(($attendedSessions / $totalSessions) * 100, 2);
    }
}
