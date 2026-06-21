<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAttendance;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolProgressReport;
use App\Models\SundaySchoolCertificate;
use App\Models\MemberPortalActivityLog;
use Exception;

class MemberPortalSundaySchoolService
{
    public static function getChildren($user)
    {
        $childIds = MemberPortalSecurity::getAuthorizedChildIds($user);
        return Member::whereIn('id', $childIds)->get();
    }

    public static function getStudentRecords($childId, $user)
    {
        if (!MemberPortalSecurity::validateChildAccess($user, $childId)) {
            throw new Exception("Access Denied: You do not have access to this child's records.");
        }

        $records = SundaySchoolStudent::where('member_id', $childId)
            ->with(['class.level', 'academicYear', 'church'])
            ->get();

        self::logActivity($childId, $user, 'child_sunday_school_viewed', "Viewed child Sunday School enrollment records");

        return $records;
    }

    public static function getAttendance($childId, $user)
    {
        if (!MemberPortalSecurity::validateChildAccess($user, $childId)) {
            throw new Exception("Access Denied: You do not have access to this child's records.");
        }

        $studentIds = SundaySchoolStudent::where('member_id', $childId)->pluck('id')->toArray();
        $attendance = SundaySchoolAttendance::whereIn('student_id', $studentIds)
            ->orderBy('attendance_date', 'desc')
            ->get();

        self::logActivity($childId, $user, 'child_sunday_school_viewed', "Viewed child Sunday School attendance");

        return $attendance;
    }

    public static function getMarks($childId, $user)
    {
        if (!MemberPortalSecurity::validateChildAccess($user, $childId)) {
            throw new Exception("Access Denied: You do not have access to this child's records.");
        }

        $studentIds = SundaySchoolStudent::where('member_id', $childId)->pluck('id')->toArray();
        $marks = SundaySchoolMark::whereIn('student_id', $studentIds)
            ->with('exam')
            ->get();

        self::logActivity($childId, $user, 'child_sunday_school_viewed', "Viewed child Sunday School marks");

        return $marks;
    }

    public static function getProgressReports($childId, $user)
    {
        if (!MemberPortalSecurity::validateChildAccess($user, $childId)) {
            throw new Exception("Access Denied: You do not have access to this child's records.");
        }

        $studentIds = SundaySchoolStudent::where('member_id', $childId)->pluck('id')->toArray();
        $reports = SundaySchoolProgressReport::whereIn('student_id', $studentIds)
            ->with(['academicYear', 'class'])
            ->get();

        self::logActivity($childId, $user, 'child_sunday_school_viewed', "Viewed child Sunday School progress reports");

        return $reports;
    }

    public static function getCertificates($childId, $user)
    {
        if (!MemberPortalSecurity::validateChildAccess($user, $childId)) {
            throw new Exception("Access Denied: You do not have access to this child's records.");
        }

        $studentIds = SundaySchoolStudent::where('member_id', $childId)->pluck('id')->toArray();
        $certificates = SundaySchoolCertificate::whereIn('student_id', $studentIds)
            ->get();

        self::logActivity($childId, $user, 'child_sunday_school_viewed', "Viewed child Sunday School certificates");

        return $certificates;
    }

    private static function logActivity($childId, $user, string $action, string $description)
    {
        $child = Member::find($childId);
        if ($child) {
            MemberPortalActivityLog::create([
                'diocese_id' => $child->diocese_id,
                'church_id' => $child->church_id,
                'user_id' => $user->id,
                'family_id' => $child->family_id,
                'member_id' => $child->id,
                'action' => $action,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        }
    }
}
