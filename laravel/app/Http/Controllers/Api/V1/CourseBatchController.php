<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Services\CourseBatchCodeService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class CourseBatchController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = CourseBatch::with(['course', 'church']);

        // Scoping
        $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
        if ($accessibleIds !== null) {
            $query->where(function ($q) use ($accessibleIds) {
                $q->whereNull('church_id')
                  ->orWhereIn('church_id', $accessibleIds);
            });
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        $batches = $query->orderBy('start_datetime', 'desc')->get();

        return $this->successResponse($batches, 'Course batches retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
            'church_id' => 'nullable|integer|exists:churches,id',
            'batch_name' => 'required|string|max:255',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string',
            'mode' => 'required|string|in:online,offline,hybrid',
            'venue' => 'nullable|string',
            'meeting_link' => 'nullable|string|url',
            'registration_open_at' => 'nullable|date',
            'registration_close_at' => 'nullable|date|after:registration_open_at',
            'max_participants' => 'nullable|integer|min:1',
            'fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'certificate_enabled' => 'nullable|boolean',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = $request->input('church_id');
        $user = $request->user();

        // 1. Validate Scopes
        if ($churchId === null) {
            // Diocesan level: Only Diocese Admin / Super Admin can create
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to create diocesan-level course batches', 403);
            }
        } else {
            // Parish level: Must have access to that parish
            if (!ChurchAccessService::canAccessChurch($user, $churchId)) {
                return $this->errorResponse('You do not have access to manage batches in this parish', 403);
            }
        }

        $course = Course::findOrFail($request->input('course_id'));
        $startYear = Carbon::parse($request->input('start_datetime'))->year;

        // Generate locked unique batch code
        $batchCode = CourseBatchCodeService::generateCode($course->course_type, $startYear);

        $batch = CourseBatch::create(array_merge($validator->validated(), [
            'diocese_id' => $course->diocese_id,
            'batch_code' => $batchCode,
            'status' => 'draft',
            'created_by' => $user->id,
        ]));

        AuditLogService::log(
            'courses',
            'course_batch_created',
            "Batch {$batch->batch_code} created for course {$course->name}",
            null,
            $batch->toArray(),
            $batch,
            $batch->church_id,
            $batch->diocese_id
        );

        return $this->successResponse($batch, 'Course batch created successfully', 201);
    }

    public function show($id)
    {
        $batch = CourseBatch::with(['course', 'church', 'sessions'])->findOrFail($id);
        return $this->successResponse($batch, 'Course batch retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $batch = CourseBatch::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($batch->church_id === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to update diocesan batch', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
                return $this->errorResponse('Unauthorized to update batch in this parish', 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'batch_name' => 'required|string|max:255',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string',
            'mode' => 'required|string|in:online,offline,hybrid',
            'venue' => 'nullable|string',
            'meeting_link' => 'nullable|string|url',
            'registration_open_at' => 'nullable|date',
            'registration_close_at' => 'nullable|date|after:registration_open_at',
            'max_participants' => 'nullable|integer|min:1',
            'fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'certificate_enabled' => 'nullable|boolean',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'status' => 'nullable|string|in:draft,open,closed,ongoing,completed,cancelled,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $batch->toArray();
        $batch->update(array_merge($validator->validated(), [
            'updated_by' => $user->id,
        ]));

        AuditLogService::log(
            'courses',
            'course_batch_updated',
            "Batch {$batch->batch_code} updated",
            $oldValues,
            $batch->toArray(),
            $batch,
            $batch->church_id,
            $batch->diocese_id
        );

        return $this->successResponse($batch, 'Course batch updated successfully');
    }

    public function open(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'open', 'course_batch_opened');
    }

    public function close(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'closed', 'course_batch_closed');
    }

    public function complete(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'completed', 'course_batch_completed');
    }

    public function cancel(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'cancelled', 'course_batch_cancelled');
    }

    protected function updateStatus(Request $request, int $id, string $status, string $logAction)
    {
        $batch = CourseBatch::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($batch->church_id === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to manage diocesan batch', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
                return $this->errorResponse('Unauthorized to manage batch in this parish', 403);
            }
        }

        $oldValues = $batch->toArray();
        $batch->update(['status' => $status, 'updated_by' => $user->id]);

        if ($status === 'open') {
            \App\Services\NotificationTriggerService::triggerCourseBatchOpened($batch);
        }

        AuditLogService::log(
            'courses',
            $logAction,
            "Batch {$batch->batch_code} status updated to {$status}",
            $oldValues,
            $batch->toArray(),
            $batch,
            $batch->church_id,
            $batch->diocese_id
        );

        return $this->successResponse($batch, "Course batch marked as {$status} successfully");
    }

    public function sessions($id)
    {
        $batch = CourseBatch::findOrFail($id);
        $sessions = CourseSession::where('course_batch_id', $batch->id)->orderBy('session_order')->get();
        return $this->successResponse($sessions, 'Sessions retrieved successfully');
    }

    public function storeSession(Request $request, $id)
    {
        $batch = CourseBatch::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($batch->church_id === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to add sessions to diocesan batch', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $batch->church_id)) {
                return $this->errorResponse('Unauthorized to add sessions in this parish', 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'timezone' => 'nullable|string',
            'speaker_name' => 'nullable|string|max:255',
            'speaker_profile' => 'nullable|string',
            'meeting_link' => 'nullable|string|url',
            'session_order' => 'required|integer|min:1',
            'attendance_required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $session = CourseSession::create(array_merge($validator->validated(), [
            'course_batch_id' => $batch->id,
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]));

        AuditLogService::log(
            'courses',
            'course_session_created',
            "Session '{$session->title}' created in batch {$batch->batch_code}",
            null,
            $session->toArray(),
            $session,
            $batch->church_id,
            $batch->diocese_id
        );

        return $this->successResponse($session, 'Session created successfully', 201);
    }
}
