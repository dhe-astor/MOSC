<?php

namespace App\Services;

use App\Models\CourseBatch;
use App\Models\CourseRegistration;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\QrCodeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseRegistrationService
{
    /**
     * Register a member, family, or external participant for a course batch.
     */
    public function register(array $data, User $registrar): CourseRegistration
    {
        $batchId = $data['course_batch_id'];
        $batch = CourseBatch::with('course')->findOrFail($batchId);

        // 1. Validate registration window
        $now = Carbon::now();
        if ($batch->registration_open_at && $batch->registration_open_at->gt($now)) {
            throw new Exception('Registration for this batch has not opened yet.');
        }
        if ($batch->registration_close_at && $batch->registration_close_at->lt($now)) {
            throw new Exception('Registration for this batch has closed.');
        }
        if ($batch->status !== 'open' && $batch->status !== 'ongoing') {
            throw new Exception('Registration is not active for this batch.');
        }

        $participantCount = $data['participant_count'] ?? 1;

        // 2. Validate Capacity
        if ($batch->max_participants) {
            $currentCount = CourseRegistration::where('course_batch_id', $batch->id)
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->sum('participant_count');

            if ($currentCount + $participantCount > $batch->max_participants) {
                throw new Exception('This batch is full. Cannot register.');
            }
        }

        // 3. Prevent Duplicates
        if ($data['registration_type'] === 'member' && !empty($data['member_id'])) {
            $exists = CourseRegistration::where('course_batch_id', $batch->id)
                ->where('member_id', $data['member_id'])
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->exists();
            if ($exists) {
                throw new Exception('This member is already registered for this course batch.');
            }
        } elseif ($data['registration_type'] === 'family' && !empty($data['family_id'])) {
            $exists = CourseRegistration::where('course_batch_id', $batch->id)
                ->where('family_id', $data['family_id'])
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->exists();
            if ($exists) {
                throw new Exception('This family is already registered for this course batch.');
            }
        }

        // 4. Determine Payment Status
        $fee = $batch->fee_amount ?? $batch->course->default_fee_amount ?? 0;
        $paymentStatus = 'not_required';
        if ($fee > 0) {
            $paymentStatus = $data['payment_status'] ?? 'pending';
        }

        // 5. Determine Registration Status
        $regStatus = 'pending';
        if ($paymentStatus === 'paid' || $paymentStatus === 'not_required') {
            $regStatus = 'confirmed';
        }

        // Generate QR code
        $qrCode = QrCodeService::generateToken();

        return DB::transaction(function () use ($data, $batch, $paymentStatus, $regStatus, $qrCode, $registrar) {
            $registration = CourseRegistration::create([
                'course_batch_id' => $batch->id,
                'diocese_id' => $batch->diocese_id,
                'church_id' => $batch->church_id,
                'family_id' => $data['family_id'] ?? null,
                'member_id' => $data['member_id'] ?? null,
                'external_name' => $data['external_name'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'external_phone' => $data['external_phone'] ?? null,
                'registration_type' => $data['registration_type'],
                'participant_count' => $data['participant_count'] ?? 1,
                'payment_status' => $paymentStatus,
                'payment_reference' => $data['payment_reference'] ?? null,
                'registration_status' => $regStatus,
                'qr_code' => $qrCode,
                'registered_by' => $registrar->id,
                'approved_by' => $regStatus === 'confirmed' ? $registrar->id : null,
                'approved_at' => $regStatus === 'confirmed' ? Carbon::now() : null,
            ]);

            AuditLogService::log(
                'courses',
                'course_registration_created',
                "Registered for batch {$batch->batch_code} (Reg ID: {$registration->id})",
                null,
                $registration->toArray(),
                $registration,
                $batch->church_id,
                $batch->diocese_id
            );

            if ($registration->registration_status === 'confirmed') {
                \App\Services\NotificationTriggerService::triggerCourseRegistrationConfirmed($registration);
            }

            return $registration;
        });
    }

    /**
     * Confirm a registration (e.g. after receiving manual payment).
     */
    public function confirm(int $id, User $admin, ?string $paymentRef = null): CourseRegistration
    {
        $registration = CourseRegistration::findOrFail($id);
        
        if ($registration->registration_status === 'confirmed') {
            return $registration;
        }

        $oldValues = $registration->toArray();

        DB::transaction(function () use ($registration, $admin, $paymentRef) {
            $updateData = [
                'registration_status' => 'confirmed',
                'approved_by' => $admin->id,
                'approved_at' => Carbon::now()
            ];

            if ($registration->payment_status === 'pending') {
                $updateData['payment_status'] = 'paid';
            }
            if ($paymentRef) {
                $updateData['payment_reference'] = $paymentRef;
            }

            $registration->update($updateData);

            AuditLogService::log(
                'courses',
                'course_registration_confirmed',
                "Registration ID {$registration->id} confirmed",
                null,
                $registration->toArray(),
                $registration,
                $registration->church_id,
                $registration->diocese_id
            );
        });

        \App\Services\NotificationTriggerService::triggerCourseRegistrationConfirmed($registration);

        return $registration;
    }

    /**
     * Cancel a registration.
     */
    public function cancel(int $id, User $admin): CourseRegistration
    {
        $registration = CourseRegistration::findOrFail($id);
        
        if ($registration->registration_status === 'cancelled') {
            return $registration;
        }

        $oldValues = $registration->toArray();

        DB::transaction(function () use ($registration, $admin) {
            $registration->update([
                'registration_status' => 'cancelled'
            ]);

            AuditLogService::log(
                'courses',
                'course_registration_cancelled',
                "Registration ID {$registration->id} cancelled",
                null,
                $registration->toArray(),
                $registration,
                $registration->church_id,
                $registration->diocese_id
            );
        });

        return $registration;
    }
}
