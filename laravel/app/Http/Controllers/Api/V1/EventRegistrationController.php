<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\EventRegistration;
use App\Models\EventAttendance;
use App\Models\Event;
use App\Services\EventRegistrationService;
use App\Services\EventAttendanceService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class EventRegistrationController extends Controller
{
    use ApiResponse;

    protected EventRegistrationService $regService;
    protected EventAttendanceService $attService;

    public function __construct(
        EventRegistrationService $regService,
        EventAttendanceService $attService
    ) {
        $this->regService = $regService;
        $this->attService = $attService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = EventRegistration::with(['event', 'church', 'member', 'family']);

        // Scoping
        $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
        if ($accessibleIds !== null) {
            $query->where(function ($q) use ($accessibleIds) {
                $q->whereIn('church_id', $accessibleIds)
                  ->orWhereIn('member_id', function ($sub) use ($accessibleIds) {
                      $sub->select('id')->from('members')->whereIn('church_id', $accessibleIds);
                  });
            });
        }

        if ($request->has('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->has('registration_status')) {
            $query->where('registration_status', $request->input('registration_status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('external_name', 'like', "%{$search}%")
                  ->orWhere('external_email', 'like', "%{$search}%")
                  ->orWhere('qr_code', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($m) use ($search) {
                      $m->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->input('per_page', 50);
        $registrations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($registrations, 'Event registrations retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'registration_type' => 'required|string|in:member,family,external',
            'member_id' => 'required_if:registration_type,member|nullable|integer|exists:members,id',
            'family_id' => 'required_if:registration_type,family|nullable|integer|exists:families,id',
            'external_name' => 'required_if:registration_type,external|nullable|string|max:255',
            'external_email' => 'required_if:registration_type,external|nullable|email|max:255',
            'external_phone' => 'required_if:registration_type,external|nullable|string|max:50',
            'participant_count' => 'nullable|integer|min:1',
            'payment_status' => 'nullable|string|in:pending,paid,waived',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $event = Event::findOrFail($request->input('event_id'));
        $user = $request->user();

        // Scope validation
        if ($request->input('registration_type') === 'member') {
            $member = \App\Models\Member::findOrFail($request->input('member_id'));
            if (!ChurchAccessService::canAccessChurch($user, $member->church_id)) {
                return $this->errorResponse('Cannot register a member from another parish', 403);
            }
        } elseif ($request->input('registration_type') === 'family') {
            $family = \App\Models\Family::findOrFail($request->input('family_id'));
            if (!ChurchAccessService::canAccessChurch($user, $family->church_id)) {
                return $this->errorResponse('Cannot register a family from another parish', 403);
            }
        } else {
            // External
            if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($user, $event->church_id)) {
                return $this->errorResponse('Unauthorized to register for this parish event', 403);
            }
        }

        try {
            $registration = $this->regService->register($validator->validated(), $user);
            return $this->successResponse($registration, 'Registration completed successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function show(Request $request, $id)
    {
        $registration = EventRegistration::with(['event', 'church', 'member', 'family'])->findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            if ($registration->member && !ChurchAccessService::canAccessChurch($user, $registration->member->church_id)) {
                return $this->errorResponse('Unauthorized to view this registration', 403);
            }
        }

        return $this->successResponse($registration, 'Registration retrieved successfully');
    }

    public function confirm(Request $request, $id)
    {
        $registration = EventRegistration::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            return $this->errorResponse('Unauthorized to confirm registration', 403);
        }

        $paymentRef = $request->input('payment_reference');
        $registration = $this->regService->confirm($registration->id, $user, $paymentRef);

        return $this->successResponse($registration, 'Registration confirmed successfully');
    }

    public function cancel(Request $request, $id)
    {
        $registration = EventRegistration::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            return $this->errorResponse('Unauthorized to cancel registration', 403);
        }

        $registration = $this->regService->cancel($registration->id, $user);

        return $this->successResponse($registration, 'Registration cancelled successfully');
    }

    public function getAttendance(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);
        $user = $request->user();

        if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($user, $event->church_id)) {
            return $this->errorResponse('Unauthorized to view attendance for this event', 403);
        }

        $attendance = EventAttendance::with(['registration.member'])
            ->where('event_id', $event->id)
            ->get();

        return $this->successResponse($attendance, 'Event attendance retrieved successfully');
    }

    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_registration_id' => 'required|integer|exists:event_registrations,id',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $registration = EventRegistration::with('event')->findOrFail($request->input('event_registration_id'));
        $event = $registration->event;
        $user = $request->user();

        // Scope check
        if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($user, $event->church_id)) {
            return $this->errorResponse('Unauthorized to mark attendance in this parish', 403);
        }

        try {
            $attendance = $this->attService->manualCheckIn(
                $registration->id,
                $user,
                $request->input('remarks')
            );
            return $this->successResponse($attendance, 'Checked in successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function qrCheckIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'qr_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $event = Event::findOrFail($request->input('event_id'));
        $user = $request->user();

        // Scope check
        if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($user, $event->church_id)) {
            return $this->errorResponse('Unauthorized to check in for this parish event', 403);
        }

        try {
            $attendance = $this->attService->qrCheckIn(
                $event->id,
                $request->input('qr_code'),
                $user
            );
            return $this->successResponse($attendance, 'QR check-in completed successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
