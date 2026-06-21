<?php

namespace App\Services;

use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Models\CourseRegistration;
use App\Models\CourseAttendance;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CourseAttendanceService
{
    /**
     * Mark manual attendance for a list of registrants in a session.
     */
    public function markAttendance(int $sessionId, array $attendanceData, User $marker): array
    {
        $session = CourseSession::with('batch')->findOrFail($sessionId);
        $batch = $session->batch;
        
        $marked = [];

        DB::transaction(function () use ($session, $batch, $attendanceData, $marker, &$marked) {
            foreach ($attendanceData as $data) {
                $regId = $data['course_registration_id'];
                $status = $data['status']; // present, absent, late, excused
                $remarks = $data['remarks'] ?? null;

                $registration = CourseRegistration::where('course_batch_id', $batch->id)
                    ->findOrFail($regId);

                // Update or create attendance
                $attendance = CourseAttendance::updateOrCreate(
                    [
                        'course_session_id' => $session->id,
                        'course_registration_id' => $registration->id,
                    ],
                    [
                        'course_batch_id' => $batch->id,
                        'member_id' => $registration->member_id,
                        'attendance_date' => $session->session_date,
                        'status' => $status,
                        'marked_by' => $marker->id,
                        'marked_at' => Carbon::now(),
                        'remarks' => $remarks
                    ]
                );

                $marked[] = $attendance;
            }

            AuditLogService::log(
                'courses',
                'course_attendance_marked',
                "Attendance marked for session {$session->id} in batch {$batch->batch_code}",
                null,
                null,
                $session,
                $batch->church_id,
                $batch->diocese_id
            );
        });

        return $marked;
    }

    /**
     * Mark attendance via QR check-in for a single registration.
     */
    public function qrCheckIn(int $sessionId, string $qrCode, User $marker): CourseAttendance
    {
        $session = CourseSession::with('batch')->findOrFail($sessionId);
        $batch = $session->batch;

        // Resolve registration
        $registration = CourseRegistration::where('course_batch_id', $batch->id)
            ->where('qr_code', $qrCode)
            ->first();

        if (!$registration) {
            throw new Exception('Invalid registration QR code for this batch.');
        }

        if (in_array($registration->registration_status, ['cancelled', 'rejected'])) {
            throw new Exception('This registration is inactive or cancelled.');
        }

        $existing = CourseAttendance::where('course_session_id', $session->id)
            ->where('course_registration_id', $registration->id)
            ->first();

        if ($existing) {
            throw new Exception('Attendance has already been marked present for this session.');
        }

        return DB::transaction(function () use ($session, $batch, $registration, $marker) {
            $attendance = CourseAttendance::updateOrCreate(
                [
                    'course_session_id' => $session->id,
                    'course_registration_id' => $registration->id,
                ],
                [
                    'course_batch_id' => $batch->id,
                    'member_id' => $registration->member_id,
                    'attendance_date' => $session->session_date,
                    'status' => 'present',
                    'marked_by' => $marker->id,
                    'marked_at' => Carbon::now(),
                    'remarks' => 'QR Check-in'
                ]
            );

            // Log check-in
            AuditLogService::log(
                'courses',
                'course_attendance_qr_checkin',
                "QR check-in successful for registration ID: {$registration->id} in session: {$session->id}",
                null,
                $attendance->toArray(),
                $registration,
                $batch->church_id,
                $batch->diocese_id
            );

            return $attendance;
        });
    }

    /**
     * Calculate attendance percentage for a registration.
     */
    public function calculateAttendancePercentage(int $registrationId): float
    {
        $registration = CourseRegistration::with('batch.sessions')->findOrFail($registrationId);
        $batch = $registration->batch;

        // Get count of required sessions
        $totalRequired = $batch->sessions()
            ->where('attendance_required', true)
            ->count();

        if ($totalRequired === 0) {
            return 100.0;
        }

        // Get count of present / late attendances for this registration
        $present = CourseAttendance::where('course_registration_id', $registrationId)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('session', function ($q) {
                $q->where('attendance_required', true);
            })
            ->count();

        return round(($present / $totalRequired) * 100, 2);
    }
}
