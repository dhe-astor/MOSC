<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class EventAttendanceService
{
    /**
     * Perform QR check-in for an event registration.
     */
    public function qrCheckIn(int $eventId, string $qrCode, User $marker): EventAttendance
    {
        $event = Event::findOrFail($eventId);

        // Find registration
        $registration = EventRegistration::where('event_id', $event->id)
            ->where('qr_code', $qrCode)
            ->first();

        if (!$registration) {
            throw new Exception('Invalid registration QR code for this event.');
        }

        if ($registration->registration_status === 'cancelled') {
            throw new Exception('This registration has been cancelled.');
        }

        return $this->performCheckIn($registration, $event, $marker, 'QR Check-in');
    }

    /**
     * Perform manual check-in for a registration.
     */
    public function manualCheckIn(int $registrationId, User $marker, ?string $remarks = null): EventAttendance
    {
        $registration = EventRegistration::with('event')->findOrFail($registrationId);
        $event = $registration->event;

        if ($registration->registration_status === 'cancelled') {
            throw new Exception('This registration has been cancelled.');
        }

        return $this->performCheckIn($registration, $event, $marker, $remarks ?? 'Manual Check-in');
    }

    /**
     * Shared logic to perform check-in.
     */
    protected function performCheckIn(EventRegistration $registration, Event $event, User $marker, string $remarks): EventAttendance
    {
        // Check if already checked in
        $exists = EventAttendance::where('event_registration_id', $registration->id)->exists();
        if ($exists) {
            throw new Exception('Participant is already checked in for this event.');
        }

        return DB::transaction(function () use ($registration, $event, $marker, $remarks) {
            // Update registration record
            $registration->update([
                'registration_status' => 'checked_in',
                'checked_in_at' => Carbon::now(),
                'checked_in_by' => $marker->id
            ]);

            // Create attendance record
            $attendance = EventAttendance::create([
                'event_id' => $event->id,
                'event_registration_id' => $registration->id,
                'member_id' => $registration->member_id,
                'attendance_date' => Carbon::today(),
                'status' => 'checked_in',
                'marked_by' => $marker->id,
                'marked_at' => Carbon::now(),
                'remarks' => $remarks
            ]);

            AuditLogService::log(
                'events',
                'event_checkin_completed',
                "Checked in registration ID: {$registration->id} for event: {$event->title}",
                null,
                $attendance->toArray(),
                $registration,
                $event->church_id,
                $event->diocese_id
            );

            return $attendance;
        });
    }
}
