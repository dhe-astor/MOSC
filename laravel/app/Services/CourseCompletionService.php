<?php

namespace App\Services;

use App\Models\CourseRegistration;
use App\Models\CertificateRequest;
use App\Models\User;
use App\Services\CourseAttendanceService;
use App\Services\CertificateGenerationService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class CourseCompletionService
{
    protected CourseAttendanceService $attendanceService;
    protected CertificateGenerationService $certificateService;

    public function __construct(
        CourseAttendanceService $attendanceService,
        CertificateGenerationService $certificateService
    ) {
        $this->attendanceService = $attendanceService;
        $this->certificateService = $certificateService;
    }

    /**
     * Mark a course registration as completed if all requirements are met.
     */
    public function complete(int $registrationId, User $admin): CourseRegistration
    {
        $registration = CourseRegistration::with(['batch.course', 'member'])->findOrFail($registrationId);
        $batch = $registration->batch;
        $course = $batch->course;

        if ($registration->registration_status === 'completed') {
            return $registration;
        }

        // 1. Verify Attendance Percentage
        $requiredPct = $batch->attendance_required_percentage ?? $course->attendance_required_percentage ?? 75;
        $actualPct = $this->attendanceService->calculateAttendancePercentage($registrationId);

        if ($actualPct < $requiredPct) {
            throw new Exception("Cannot complete course. Attendance is {$actualPct}%, which is below the required {$requiredPct}%.");
        }

        // 2. Verify Feedback (if required)
        $feedbackRequired = $batch->feedback_required ?? $course->feedback_required ?? false;
        if ($feedbackRequired && !$registration->feedback_completed) {
            // Also check if a record actually exists in feedback table just in case flag is out of sync
            $feedbackExists = $registration->feedback()->exists();
            if (!$feedbackExists) {
                throw new Exception("Cannot complete course. Participant feedback is required.");
            }
        }

        // 3. Mark as completed and generate certificate (if enabled)
        DB::transaction(function () use ($registration, $batch, $course, $admin) {
            $registration->update([
                'registration_status' => 'completed'
            ]);

            $certEnabled = $batch->certificate_enabled ?? $course->certificate_enabled ?? false;
            $templateId = $batch->certificate_template_id ?? $course->certificate_template_id;

            if ($certEnabled && $templateId) {
                // Ensure we don't issue duplicate certificates
                if (!$registration->certificate_issued) {
                    // Create internal approved CertificateRequest
                    $certRequest = CertificateRequest::create([
                        'diocese_id' => $registration->diocese_id,
                        'church_id' => $registration->church_id ?? $admin->default_church_id ?? 1,
                        'requested_by' => $admin->id,
                        'family_id' => $registration->family_id,
                        'member_id' => $registration->member_id,
                        'certificate_type' => 'course_completion',
                        'purpose' => "Course completion for Batch: {$batch->batch_name}",
                        'status' => 'approved',
                        'priest_approved_by' => $admin->id,
                        'priest_approved_at' => now(),
                    ]);

                    // Generate PDF and certificate record
                    $certificate = $this->certificateService->generate($certRequest, $templateId);

                    // Update registration
                    $registration->update([
                        'certificate_issued' => true,
                        'certificate_id' => $certificate->id
                    ]);
                }
            }

            AuditLogService::log(
                'courses',
                'course_registration_completed',
                "Course registration ID {$registration->id} marked completed and certificate issued (if enabled)",
                null,
                $registration->toArray(),
                $registration,
                $registration->church_id,
                $registration->diocese_id
            );
        });

        if ($registration->certificate_issued) {
            \App\Services\NotificationTriggerService::triggerCourseCertificateIssued($registration);
        }

        return $registration->fresh(['certificate']);
    }
}
