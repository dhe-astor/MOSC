<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Event;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EventController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Event::with(['church', 'country']);

        // Scoping
        $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
        if ($accessibleIds !== null) {
            $query->where(function ($q) use ($accessibleIds) {
                $q->whereNull('church_id')
                  ->orWhereIn('church_id', $accessibleIds);
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $events = $query->orderBy('start_datetime', 'desc')->get();

        return $this->successResponse($events, 'Events retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|integer|exists:churches,id',
            'title' => 'required|string|max:255',
            'event_type' => 'required|string|in:qurbana,retreat,conference,youth_meeting,family_conference,sunday_school,charity,feast,meeting,course_related,other',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string',
            'location_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'country_id' => 'nullable|integer|exists:countries,id',
            'mode' => 'required|string|in:online,offline,hybrid',
            'meeting_link' => 'nullable|string|url',
            'registration_required' => 'nullable|boolean',
            'registration_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'max_participants' => 'nullable|integer|min:1',
            'poster_path' => 'nullable|string',
            'banner_path' => 'nullable|string',
            'visibility' => 'required|string|in:public,members_only,admins_only',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = $request->input('church_id');
        $user = $request->user();

        // Scope validation
        if ($churchId === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to create diocesan-level events', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $churchId)) {
                return $this->errorResponse('You do not have access to manage events in this parish', 403);
            }
        }

        $slug = Str::slug($request->input('title'));
        if (Event::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        $event = Event::create(array_merge($validator->validated(), [
            'diocese_id' => $user->default_diocese_id ?? 1,
            'slug' => $slug,
            'status' => 'draft',
            'created_by' => $user->id,
        ]));

        AuditLogService::log(
            'events',
            'event_created',
            "Event '{$event->title}' created",
            null,
            $event->toArray(),
            $event,
            $event->church_id,
            $event->diocese_id
        );

        return $this->successResponse($event, 'Event created successfully', 201);
    }

    public function show($id)
    {
        $event = Event::with(['church', 'country'])->findOrFail($id);
        return $this->successResponse($event, 'Event retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($event->church_id === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to update diocesan event', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $event->church_id)) {
                return $this->errorResponse('Unauthorized to update event in this parish', 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'event_type' => 'required|string|in:qurbana,retreat,conference,youth_meeting,family_conference,sunday_school,charity,feast,meeting,course_related,other',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string',
            'location_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'country_id' => 'nullable|integer|exists:countries,id',
            'mode' => 'required|string|in:online,offline,hybrid',
            'meeting_link' => 'nullable|string|url',
            'registration_required' => 'nullable|boolean',
            'registration_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'max_participants' => 'nullable|integer|min:1',
            'poster_path' => 'nullable|string',
            'banner_path' => 'nullable|string',
            'visibility' => 'required|string|in:public,members_only,admins_only',
            'status' => 'nullable|string|in:draft,published,registration_open,registration_closed,completed,cancelled,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $event->toArray();

        $slug = Str::slug($request->input('title'));
        if ($slug !== $event->slug && Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        $event->update(array_merge($validator->validated(), [
            'slug' => $slug,
        ]));

        AuditLogService::log(
            'events',
            'event_updated',
            "Event '{$event->title}' updated",
            $oldValues,
            $event->toArray(),
            $event,
            $event->church_id,
            $event->diocese_id
        );

        return $this->successResponse($event, 'Event updated successfully');
    }

    public function publish(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'published', 'event_published');
    }

    public function openRegistration(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'registration_open', 'event_registration_opened');
    }

    public function closeRegistration(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'registration_closed', 'event_registration_closed');
    }

    public function complete(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'completed', 'event_completed');
    }

    public function cancel(Request $request, $id)
    {
        return $this->updateStatus($request, $id, 'cancelled', 'event_cancelled');
    }

    protected function updateStatus(Request $request, int $id, string $status, string $logAction)
    {
        $event = Event::findOrFail($id);
        $user = $request->user();

        // Scope check
        if ($event->church_id === null) {
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Unauthorized to manage diocesan event', 403);
            }
        } else {
            if (!ChurchAccessService::canAccessChurch($user, $event->church_id)) {
                return $this->errorResponse('Unauthorized to manage event in this parish', 403);
            }
        }

        $oldValues = $event->toArray();
        $event->update([
            'status' => $status,
            'approved_by' => $status === 'published' ? $user->id : $event->approved_by,
            'approved_at' => $status === 'published' ? now() : $event->approved_at,
        ]);

        AuditLogService::log(
            'events',
            $logAction,
            "Event '{$event->title}' marked as {$status}",
            $oldValues,
            $event->toArray(),
            $event,
            $event->church_id,
            $event->diocese_id
        );

        return $this->successResponse($event, "Event marked as {$status} successfully");
    }

    public function publicEvents(Request $request)
    {
        $events = Event::with(['church', 'country'])
            ->where('visibility', 'public')
            ->whereIn('status', ['published', 'registration_open', 'registration_closed', 'completed'])
            ->orderBy('start_datetime', 'desc')
            ->get();

        return $this->successResponse($events, 'Public events retrieved successfully');
    }
}
