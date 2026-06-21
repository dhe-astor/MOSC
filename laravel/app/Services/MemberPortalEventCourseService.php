<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseRegistration;
use App\Models\MemberPortalActivityLog;
use App\Services\EventRegistrationService;
use App\Services\CourseRegistrationService;
use Exception;

class MemberPortalEventCourseService
{
    public static function getEvents($user)
    {
        return Event::whereIn('status', ['published', 'registration_open'])->get();
    }

    public static function getEventRegistrations($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        return EventRegistration::whereIn('member_id', $memberIds)
            ->with(['event', 'member'])
            ->get();
    }

    public static function registerEvent(array $data, $user)
    {
        $memberId = $data['member_id'];
        if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
            throw new Exception("Access Denied to register for this member.");
        }

        $existing = EventRegistration::where('event_id', $data['event_id'])
            ->where('member_id', $memberId)
            ->whereNotIn('registration_status', ['cancelled', 'rejected'])
            ->first();

        if ($existing) {
            throw new Exception("You are already registered for this event.");
        }

        $data['registration_type'] = 'member';
        $data['payment_status'] = $data['payment_status'] ?? 'pending';

        $registration = (new EventRegistrationService())->register($data, $user);

        self::logActivity($registration->diocese_id, $registration->church_id, $user->id, null, $registration->member_id, 'event_registered', "Registered for event: {$registration->event?->title}");

        return $registration;
    }

    public static function getCourses($user)
    {
        return CourseBatch::where('status', 'open')
            ->with('course')
            ->get();
    }

    public static function getCourseRegistrations($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        return CourseRegistration::whereIn('member_id', $memberIds)
            ->with(['batch.course', 'member'])
            ->get();
    }

    public static function registerCourseBatch(array $data, $user)
    {
        $memberId = $data['member_id'];
        if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
            throw new Exception("Access Denied to register for this member.");
        }

        $existing = CourseRegistration::where('course_batch_id', $data['course_batch_id'])
            ->where('member_id', $memberId)
            ->whereNotIn('registration_status', ['cancelled', 'rejected'])
            ->first();

        if ($existing) {
            throw new Exception("You are already registered for this course batch.");
        }

        $data['registration_type'] = 'member';
        $data['payment_status'] = $data['payment_status'] ?? 'pending';

        $registration = (new CourseRegistrationService())->register($data, $user);

        self::logActivity($registration->diocese_id, $registration->church_id, $user->id, null, $registration->member_id, 'course_registered', "Registered for course batch: {$registration->batch?->batch_name}");

        return $registration;
    }

    private static function logActivity($dioceseId, $churchId, $userId, $familyId, $memberId, string $action, string $description)
    {
        MemberPortalActivityLog::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'user_id' => $userId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
