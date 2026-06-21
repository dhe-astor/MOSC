<?php

namespace App\Services;

use App\Models\User;
use App\Models\MemberPortalAccess;
use App\Models\Family;
use App\Models\Member;
use App\Models\SundaySchoolStudent;
use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\Receipt;
use App\Models\Donation;
use App\Models\MemberPortalDocument;
use App\Models\FamilyTransferRequest;
use App\Models\EventRegistration;
use App\Models\CourseRegistration;

class MemberPortalSecurity
{
    /**
     * Resolve all active access mappings for the user.
     */
    public static function getActiveAccess($user)
    {
        return MemberPortalAccess::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get list of authorized family IDs for the user.
     */
    public static function getAuthorizedFamilyIds($user): array
    {
        $accesses = self::getActiveAccess($user);
        $familyIds = [];

        foreach ($accesses as $access) {
            if ($access->family_id) {
                $familyIds[] = $access->family_id;
            }
            if ($access->member_id) {
                $member = Member::find($access->member_id);
                if ($member && $member->family_id) {
                    $familyIds[] = $member->family_id;
                }
            }
        }

        return array_unique(array_filter($familyIds));
    }

    /**
     * Get list of authorized member IDs for the user.
     */
    public static function getAuthorizedMemberIds($user): array
    {
        $accesses = self::getActiveAccess($user);
        $memberIds = [];

        foreach ($accesses as $access) {
            if ($access->member_id) {
                $memberIds[] = $access->member_id;
            }
            if ($access->access_type === 'family_head' && $access->family_id) {
                $mIds = Member::where('family_id', $access->family_id)->pluck('id')->toArray();
                $memberIds = array_merge($memberIds, $mIds);
            }
            if ($access->access_type === 'parent_guardian') {
                if ($access->member_id) {
                    $childIds = SundaySchoolStudent::where('parent_member_id', $access->member_id)
                        ->pluck('member_id')
                        ->toArray();
                    $memberIds = array_merge($memberIds, $childIds);
                }
                if ($access->family_id) {
                    $childIds = Member::where('family_id', $access->family_id)
                        ->whereIn('relationship_to_head', ['son', 'daughter', 'relative'])
                        ->pluck('id')
                        ->toArray();
                    $memberIds = array_merge($memberIds, $childIds);
                }
            }
        }

        return array_unique(array_filter($memberIds));
    }

    /**
     * Get list of authorized child member IDs specifically for parent/guardian view.
     */
    public static function getAuthorizedChildIds($user): array
    {
        $accesses = self::getActiveAccess($user);
        $childIds = [];

        foreach ($accesses as $access) {
            if ($access->access_type === 'parent_guardian') {
                if ($access->member_id) {
                    $cIds = SundaySchoolStudent::where('parent_member_id', $access->member_id)
                        ->pluck('member_id')
                        ->toArray();
                    $childIds = array_merge($childIds, $cIds);
                }
                if ($access->family_id) {
                    $cIds = Member::where('family_id', $access->family_id)
                        ->whereIn('relationship_to_head', ['son', 'daughter', 'relative'])
                        ->pluck('id')
                        ->toArray();
                    $childIds = array_merge($childIds, $cIds);
                }
            }
            if ($access->access_type === 'family_head' && $access->family_id) {
                $cIds = Member::where('family_id', $access->family_id)
                    ->whereIn('relationship_to_head', ['son', 'daughter', 'relative'])
                    ->pluck('id')
                    ->toArray();
                $childIds = array_merge($childIds, $cIds);
            }
        }

        return array_unique(array_filter($childIds));
    }

    public static function validateFamilyAccess($user, $familyId): bool
    {
        return in_array($familyId, self::getAuthorizedFamilyIds($user));
    }

    public static function validateMemberAccess($user, $memberId): bool
    {
        return in_array($memberId, self::getAuthorizedMemberIds($user));
    }

    public static function validateChildAccess($user, $childId): bool
    {
        return in_array($childId, self::getAuthorizedChildIds($user));
    }

    public static function validateCertificateRequestAccess($user, $requestId): bool
    {
        $request = CertificateRequest::find($requestId);
        if (!$request) return false;
        return self::validateMemberAccess($user, $request->member_id);
    }

    public static function validateCertificateAccess($user, $certificateId): bool
    {
        $certificate = Certificate::find($certificateId);
        if (!$certificate) return false;
        return self::validateMemberAccess($user, $certificate->member_id);
    }

    public static function validateReceiptAccess($user, $receiptId): bool
    {
        $receipt = Receipt::find($receiptId);
        if (!$receipt) return false;
        if ($receipt->family_id) {
            return self::validateFamilyAccess($user, $receipt->family_id);
        }
        if ($receipt->member_id) {
            return self::validateMemberAccess($user, $receipt->member_id);
        }
        return false;
    }

    public static function validateDonationAccess($user, $donationId): bool
    {
        $donation = Donation::find($donationId);
        if (!$donation) return false;
        if ($donation->member_id) {
            return self::validateMemberAccess($user, $donation->member_id);
        }
        if (isset($donation->family_id) && $donation->family_id) {
            return self::validateFamilyAccess($user, $donation->family_id);
        }
        return false;
    }

    public static function validateDocumentAccess($user, $documentId): bool
    {
        $doc = MemberPortalDocument::find($documentId);
        if (!$doc) return false;
        if ($doc->uploaded_by === $user->id) return true;
        if ($doc->member_id && self::validateMemberAccess($user, $doc->member_id)) return true;
        if ($doc->family_id && self::validateFamilyAccess($user, $doc->family_id)) return true;
        return false;
    }

    public static function validateTransferRequestAccess($user, $transferId): bool
    {
        $tr = FamilyTransferRequest::find($transferId);
        if (!$tr) return false;
        return self::validateFamilyAccess($user, $tr->family_id);
    }

    public static function validateEventRegistrationAccess($user, $registrationId): bool
    {
        $reg = EventRegistration::find($registrationId);
        if (!$reg) return false;
        return self::validateMemberAccess($user, $reg->member_id);
    }

    public static function validateCourseRegistrationAccess($user, $registrationId): bool
    {
        $reg = CourseRegistration::find($registrationId);
        if (!$reg) return false;
        return self::validateMemberAccess($user, $reg->member_id);
    }
}
