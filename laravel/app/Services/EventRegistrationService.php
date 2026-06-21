<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\QrCodeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class EventRegistrationService
{
    /**
     * Register a member, family, or external participant for an event.
     */
    public function register(array $data, User $registrar): EventRegistration
    {
        $eventId = $data['event_id'];
        $event = Event::findOrFail($eventId);

        // 1. Validate event status for registration
        if (!in_array($event->status, ['published', 'registration_open'])) {
            throw new Exception('Registration is not open for this event.');
        }

        // Validate event time has not passed
        if ($event->start_datetime->lt(Carbon::now())) {
            throw new Exception('Cannot register for a past event.');
        }

        $participantCount = $data['participant_count'] ?? 1;

        // 2. Validate Capacity
        if ($event->max_participants) {
            $currentCount = EventRegistration::where('event_id', $event->id)
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->sum('participant_count');

            if ($currentCount + $participantCount > $event->max_participants) {
                throw new Exception('This event is fully booked. Cannot register.');
            }
        }

        // 3. Prevent Duplicates
        if ($data['registration_type'] === 'member' && !empty($data['member_id'])) {
            $exists = EventRegistration::where('event_id', $event->id)
                ->where('member_id', $data['member_id'])
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->exists();
            if ($exists) {
                throw new Exception('This member is already registered for this event.');
            }
        } elseif ($data['registration_type'] === 'family' && !empty($data['family_id'])) {
            $exists = EventRegistration::where('event_id', $event->id)
                ->where('family_id', $data['family_id'])
                ->whereNotIn('registration_status', ['cancelled', 'rejected'])
                ->exists();
            if ($exists) {
                throw new Exception('This family is already registered for this event.');
            }
        }

        // 4. Determine Payment Status
        $fee = $event->registration_fee ?? 0;
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

        return DB::transaction(function () use ($data, $event, $paymentStatus, $regStatus, $qrCode, $registrar) {
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'diocese_id' => $event->diocese_id,
                'church_id' => $event->church_id,
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
                'events',
                'event_registration_created',
                "Registered for event: {$event->title} (Reg ID: {$registration->id})",
                null,
                $registration->toArray(),
                $registration,
                $event->church_id,
                $event->diocese_id
            );

            if ($registration->registration_status === 'confirmed') {
                \App\Services\NotificationTriggerService::triggerEventRegistrationConfirmed($registration);
            }

            return $registration;
        });
    }

    /**
     * Confirm event registration manually.
     */
    public function confirm(int $id, User $admin, ?string $paymentRef = null): EventRegistration
    {
        $registration = EventRegistration::findOrFail($id);
        
        if ($registration->registration_status === 'confirmed') {
            return $registration;
        }

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
                'events',
                'event_registration_confirmed',
                "Event registration ID {$registration->id} confirmed",
                null,
                $registration->toArray(),
                $registration,
                $registration->church_id,
                $registration->diocese_id
            );
        });

        \App\Services\NotificationTriggerService::triggerEventRegistrationConfirmed($registration);

        return $registration;
    }

    /**
     * Cancel event registration.
     */
    public function cancel(int $id, User $admin): EventRegistration
    {
        $registration = EventRegistration::findOrFail($id);
        
        if ($registration->registration_status === 'cancelled') {
            return $registration;
        }

        DB::transaction(function () use ($registration, $admin) {
            $registration->update([
                'registration_status' => 'cancelled'
            ]);

            AuditLogService::log(
                'events',
                'event_registration_cancelled',
                "Event registration ID {$registration->id} cancelled",
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
