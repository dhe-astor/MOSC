<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Models\Family;
use App\Models\CourseRegistration;
use App\Models\EventRegistration;
use App\Models\SundaySchoolStudent;
use App\Models\MinistryMembership;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use Spatie\Permission\Models\Role;
use Exception;

class RecipientResolverService
{
    public static function resolveAnnouncementRecipients(Announcement $announcement, $actingUser = null): array
    {
        $recipients = [];

        foreach ($announcement->targets as $target) {
            $resolved = [];
            switch ($target->target_type) {
                case 'all_members':
                    $resolved = self::resolveAllMembers($announcement->diocese_id, $actingUser);
                    break;
                case 'church':
                    $resolved = self::resolveChurchMembers($target->target_id, $actingUser);
                    break;
                case 'role':
                    $resolved = self::resolveRoleUsers($target->target_id, $actingUser);
                    break;
                case 'family':
                    $resolved = self::resolveFamilyMembers($target->target_id, $actingUser);
                    break;
                case 'member':
                    $resolved = self::resolveSingleMember($target->target_id, $actingUser);
                    break;
                case 'course_batch':
                    $resolved = self::resolveCourseBatchParticipants($target->target_id, $actingUser);
                    break;
                case 'event':
                    $resolved = self::resolveEventParticipants($target->target_id, $actingUser);
                    break;
                case 'sunday_school_class':
                    $resolved = self::resolveSundaySchoolParents($target->target_id, $actingUser);
                    break;
                case 'ministry_unit':
                    $resolved = self::resolveMinistryUnitMembers($target->target_id, $actingUser);
                    break;
            }
            $recipients = array_merge($recipients, $resolved);
        }

        return self::removeDuplicateRecipients($recipients);
    }

    public static function resolveAllMembers(int $dioceseId, $actingUser = null): array
    {
        // Enforce church scoping if the user is scoped
        $query = Member::where('diocese_id', $dioceseId)->where('membership_status', 'active');
        if ($actingUser) {
            $query = ChurchAccessService::scopeQuery($actingUser, $query);
        }

        return $query->get()->map(fn($m) => self::formatMember($m))->toArray();
    }

    public static function resolveChurchMembers(int $churchId, $actingUser = null): array
    {
        if ($actingUser && !ChurchAccessService::canAccessChurch($actingUser, $churchId)) {
            return []; // Scoping mismatch
        }

        return Member::where('church_id', $churchId)
            ->where('membership_status', 'active')
            ->get()
            ->map(fn($m) => self::formatMember($m))
            ->toArray();
    }

    public static function resolveRoleUsers($targetId, $actingUser = null): array
    {
        $role = is_numeric($targetId) ? Role::find($targetId) : Role::where('name', $targetId)->first();
        if (!$role) {
            return [];
        }

        $query = User::role($role->name);
        
        // If scoped, only fetch users within accessible church IDs
        if ($actingUser) {
            $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($actingUser);
            if ($accessibleChurchIds !== null) {
                $query->whereIn('default_church_id', $accessibleChurchIds);
            }
        }

        return $query->get()->map(fn($u) => self::formatUser($u))->toArray();
    }

    public static function resolveFamilyMembers(int $familyId, $actingUser = null): array
    {
        $family = Family::find($familyId);
        if (!$family) {
            return [];
        }

        if ($actingUser && !ChurchAccessService::canAccessChurch($actingUser, $family->church_id)) {
            return [];
        }

        return Member::where('family_id', $familyId)
            ->where('membership_status', 'active')
            ->get()
            ->map(fn($m) => self::formatMember($m))
            ->toArray();
    }

    public static function resolveSingleMember(int $memberId, $actingUser = null): array
    {
        $member = Member::find($memberId);
        if (!$member) {
            return [];
        }

        if ($actingUser && !ChurchAccessService::canAccessChurch($actingUser, $member->church_id)) {
            return [];
        }

        return [self::formatMember($member)];
    }

    public static function resolveCourseBatchParticipants(int $batchId, $actingUser = null): array
    {
        $query = CourseRegistration::where('course_batch_id', $batchId)
            ->where('registration_status', 'approved');

        if ($actingUser) {
            $query = ChurchAccessService::scopeQuery($actingUser, $query);
        }

        $recipients = [];
        foreach ($query->get() as $reg) {
            if ($reg->member_id) {
                $recipients[] = self::formatMember($reg->member);
            } else {
                $recipients[] = self::formatExternal($reg);
            }
        }
        return $recipients;
    }

    public static function resolveEventParticipants(int $eventId, $actingUser = null): array
    {
        $query = EventRegistration::where('event_id', $eventId)
            ->where('registration_status', 'approved');

        if ($actingUser) {
            $query = ChurchAccessService::scopeQuery($actingUser, $query);
        }

        $recipients = [];
        foreach ($query->get() as $reg) {
            if ($reg->member_id) {
                $recipients[] = self::formatMember($reg->member);
            } else {
                $recipients[] = self::formatExternal($reg);
            }
        }
        return $recipients;
    }

    public static function resolveSundaySchoolParents(int $classId, $actingUser = null): array
    {
        // If teacher is scoping, check class assignment
        // Enforce class teacher assignment if relevant
        $query = SundaySchoolStudent::where('class_id', $classId)
            ->where('enrollment_status', 'approved');

        if ($actingUser) {
            $query = ChurchAccessService::scopeQuery($actingUser, $query);
        }

        $recipients = [];
        foreach ($query->get() as $student) {
            if ($student->parent_member_id) {
                $parent = Member::find($student->parent_member_id);
                if ($parent) {
                    $recipients[] = self::formatMember($parent);
                }
            } else {
                // Fallback to family head
                $head = Member::where('family_id', $student->family_id)
                    ->where('relationship_to_head', 'head')
                    ->first();
                if ($head) {
                    $recipients[] = self::formatMember($head);
                }
            }
        }
        return $recipients;
    }

    public static function resolveMinistryUnitMembers(int $unitId, $actingUser = null): array
    {
        $query = MinistryMembership::where('ministry_unit_id', $unitId)
            ->where('status', 'active');

        if ($actingUser) {
            $query = ChurchAccessService::scopeQuery($actingUser, $query);
        }

        return $query->get()->map(fn($mm) => self::formatMember($mm->member))->toArray();
    }

    public static function removeDuplicateRecipients(array $recipients): array
    {
        $unique = [];
        $seen = [];

        foreach ($recipients as $r) {
            $key = $r['recipient_type'] . '_' . ($r['recipient_id'] ?? 'ext') . '_' . ($r['email'] ?? '') . '_' . ($r['phone'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }

        return $unique;
    }

    private static function formatMember(Member $m): array
    {
        return [
            'recipient_type' => 'member',
            'recipient_id' => $m->id,
            'name' => $m->full_name,
            'email' => $m->email,
            'phone' => $m->phone,
            'church_id' => $m->church_id,
            'user_id' => $m->user_id
        ];
    }

    private static function formatUser(User $u): array
    {
        return [
            'recipient_type' => 'user',
            'recipient_id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => null,
            'church_id' => $u->default_church_id,
            'user_id' => $u->id
        ];
    }

    private static function formatExternal($reg): array
    {
        return [
            'recipient_type' => 'external',
            'recipient_id' => null,
            'name' => $reg->external_name,
            'email' => $reg->external_email,
            'phone' => $reg->external_phone,
            'church_id' => $reg->church_id,
            'user_id' => null
        ];
    }
}
