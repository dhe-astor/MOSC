<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\CourseRegistration;
use App\Models\CourseAttendance;
use App\Models\CourseFeedback;
use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Services\CourseRegistrationService;
use App\Services\CourseAttendanceService;
use App\Services\CourseCompletionService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class CourseRegistrationController extends Controller
{
    use ApiResponse;

    protected CourseRegistrationService $regService;
    protected CourseAttendanceService $attService;
    protected CourseCompletionService $compService;

    public function __construct(
        CourseRegistrationService $regService,
        CourseAttendanceService $attService,
        CourseCompletionService $compService
    ) {
        $this->regService = $regService;
        $this->attService = $attService;
        $this->compService = $compService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = CourseRegistration::with(['batch.course', 'church', 'member', 'family', 'certificate']);

        // Scoping
        $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
        if ($accessibleIds !== null) {
            $query->where(function ($q) use ($accessibleIds) {
                // Scoped by registration church_id OR the member's church_id
                $q->whereIn('church_id', $accessibleIds)
                  ->orWhereIn('member_id', function ($sub) use ($accessibleIds) {
                      $sub->select('id')->from('members')->whereIn('church_id', $accessibleIds);
                  });
            });
        }

        if ($request->has('course_batch_id')) {
            $query->where('course_batch_id', $request->input('course_batch_id'));
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

        return $this->paginatedResponse($registrations, 'Registrations retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_batch_id' => 'required|integer|exists:course_batches,id',
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

        $batch = CourseBatch::findOrFail($request->input('course_batch_id'));
        $user = $request->user();

        // Scope validation: Parish Admin can only register members for batches/churches they have access to.
        // Wait, if it is a diocese-level batch, they can register members from their own parish only.
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
            // External: check batch church_id access
            if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
                return $this->errorResponse('Unauthorized to register for this parish batch', 403);
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
        $registration = CourseRegistration::with(['batch.course', 'church', 'member', 'family', 'certificate'])->findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            // Check if registrant member belongs to accessible church
            if ($registration->member && !ChurchAccessService::canAccessChurch($user, $registration->member->church_id)) {
                return $this->errorResponse('Unauthorized to view this registration', 403);
            }
        }

        return $this->successResponse($registration, 'Registration retrieved successfully');
    }

    public function confirm(Request $request, $id)
    {
        $registration = CourseRegistration::findOrFail($id);
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
        $registration = CourseRegistration::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            return $this->errorResponse('Unauthorized to cancel registration', 403);
        }

        $registration = $this->regService->cancel($registration->id, $user);

        return $this->successResponse($registration, 'Registration cancelled successfully');
    }

    public function markCompleted(Request $request, $id)
    {
        $registration = CourseRegistration::findOrFail($id);
        $user = $request->user();

        // Scope check: Only Vicar or Admin can mark as completed
        if (!$user->hasAnyRole(['Super Admin', 'Diocese Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Unauthorized to mark course completion', 403);
        }

        if ($registration->church_id !== null && !ChurchAccessService::canAccessChurch($user, $registration->church_id)) {
            return $this->errorResponse('Unauthorized to modify registrations in this parish', 403);
        }

        try {
            $registration = $this->compService->complete($registration->id, $user);
            return $this->successResponse($registration, 'Course registration marked as completed');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function issueCertificate(Request $request, $id)
    {
        // Re-use markCompleted to handle auto-issuance, or force trigger.
        return $this->markCompleted($request, $id);
    }

    public function getAttendance(Request $request, $batchId)
    {
        $batch = CourseBatch::findOrFail($batchId);
        $user = $request->user();

        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
            return $this->errorResponse('Unauthorized to view attendance for this batch', 403);
        }

        $attendance = CourseAttendance::with(['session', 'registration.member'])
            ->where('course_batch_id', $batch->id)
            ->get();

        return $this->successResponse($attendance, 'Attendance retrieved successfully');
    }

    public function markSessionAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_session_id' => 'required|integer|exists:course_sessions,id',
            'attendance' => 'required|array',
            'attendance.*.course_registration_id' => 'required|integer|exists:course_registrations,id',
            'attendance.*.status' => 'required|string|in:present,absent,late,excused',
            'attendance.*.remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $session = CourseSession::with('batch')->findOrFail($request->input('course_session_id'));
        $batch = $session->batch;
        $user = $request->user();

        // Scope check
        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
            return $this->errorResponse('Unauthorized to mark attendance in this parish', 403);
        }

        $marked = $this->attService->markAttendance(
            $session->id,
            $request->input('attendance'),
            $user
        );

        return $this->successResponse($marked, 'Attendance marked successfully');
    }

    public function qrCheckIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_session_id' => 'required|integer|exists:course_sessions,id',
            'qr_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $session = CourseSession::with('batch')->findOrFail($request->input('course_session_id'));
        $batch = $session->batch;
        $user = $request->user();

        // Scope check
        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
            return $this->errorResponse('Unauthorized to scan QR codes for this parish batch', 403);
        }

        try {
            $attendance = $this->attService->qrCheckIn(
                $session->id,
                $request->input('qr_code'),
                $user
            );
            return $this->successResponse($attendance, 'QR check-in completed successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function getFeedback($batchId, Request $request)
    {
        $batch = CourseBatch::findOrFail($batchId);
        $user = $request->user();

        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
            return $this->errorResponse('Unauthorized to view feedback for this batch', 403);
        }

        $feedback = CourseFeedback::with('member')
            ->where('course_batch_id', $batch->id)
            ->get();

        return $this->successResponse($feedback, 'Feedback retrieved successfully');
    }

    public function submitFeedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_registration_id' => 'required|integer|exists:course_registrations,id',
            'rating' => 'required|integer|min:1|max:5',
            'feedback_text' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $registration = CourseRegistration::with('batch')->findOrFail($request->input('course_registration_id'));
        $batch = $registration->batch;
        $user = $request->user();

        // Prevent duplicate feedback
        $exists = CourseFeedback::where('course_registration_id', $registration->id)->exists();
        if ($exists) {
            return $this->errorResponse('Feedback has already been submitted for this registration.', 400);
        }

        $feedback = CourseFeedback::create([
            'course_batch_id' => $batch->id,
            'course_registration_id' => $registration->id,
            'member_id' => $registration->member_id,
            'rating' => $request->input('rating'),
            'feedback_text' => $request->input('feedback_text'),
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        $registration->update(['feedback_completed' => true]);

        AuditLogService::log(
            'courses',
            'course_feedback_submitted',
            "Feedback submitted for registration ID {$registration->id}",
            null,
            $feedback->toArray(),
            $registration,
            $registration->church_id,
            $registration->diocese_id
        );

        return $this->successResponse($feedback, 'Feedback submitted successfully', 201);
    }
}
