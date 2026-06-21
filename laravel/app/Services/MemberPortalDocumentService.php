<?php

namespace App\Services;

use App\Models\MemberPortalDocument;
use App\Models\MemberPortalActivityLog;
use App\Models\Member;
use App\Models\Family;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class MemberPortalDocumentService
{
    public static function upload($file, array $data, $user)
    {
        // 1. Validate size (5MB = 5 * 1024 * 1024 bytes)
        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new Exception("File size exceeds the maximum limit of 5MB.");
        }

        // 2. Validate MIME type
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new Exception("Invalid file type. Only PDF, JPEG, and PNG are allowed.");
        }

        $memberId = $data['member_id'] ?? null;
        $familyId = $data['family_id'] ?? null;
        $documentType = $data['document_type'];

        $dioceseId = null;
        $churchId = null;

        if ($memberId) {
            if (!MemberPortalSecurity::validateMemberAccess($user, $memberId)) {
                throw new Exception("Access Denied to this member context.");
            }
            $member = Member::findOrFail($memberId);
            $dioceseId = $member->diocese_id;
            $churchId = $member->church_id;
            $familyId = $member->family_id;
        } elseif ($familyId) {
            if (!MemberPortalSecurity::validateFamilyAccess($user, $familyId)) {
                throw new Exception("Access Denied to this family context.");
            }
            $family = Family::findOrFail($familyId);
            $dioceseId = $family->diocese_id;
            $churchId = $family->church_id;
        } else {
            throw new Exception("Either member_id or family_id must be provided.");
        }

        // 3. Store file in private path
        $safeFilename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $folderPath = "private/portal_documents/{$churchId}/{$familyId}";
        $filePath = Storage::disk('local')->putFileAs($folderPath, $file, $safeFilename);

        $doc = MemberPortalDocument::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'uploaded_by' => $user->id,
            'document_type' => $documentType,
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'file_path' => $filePath,
            'original_file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded'
        ]);

        self::logActivity($dioceseId, $churchId, $user->id, $familyId, $memberId, 'document_uploaded', "Uploaded document: {$doc->original_file_name}");

        return $doc;
    }

    public static function download(MemberPortalDocument $doc, $user)
    {
        // Check authorization
        $isAuthorized = false;

        // Admin check:
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $isAuthorized = true;
        } elseif ($user->hasRole(['Priest / Vicar', 'Parish Admin'])) {
            // Check church scope match
            if (ChurchAccessService::canAccessChurch($user, $doc->church_id)) {
                $isAuthorized = true;
            }
        } else {
            // Portal user check
            $isAuthorized = MemberPortalSecurity::validateDocumentAccess($user, $doc->id);
        }

        if (!$isAuthorized) {
            throw new Exception("Access Denied: You are not authorized to download this document.");
        }

        if (!Storage::disk('local')->exists($doc->file_path)) {
            throw new Exception("Document file not found in storage.");
        }

        self::logActivity($doc->diocese_id, $doc->church_id, $user->id, $doc->family_id, $doc->member_id, 'document_downloaded', "Downloaded document: {$doc->original_file_name}");

        return Storage::disk('local')->download($doc->file_path, $doc->original_file_name);
    }

    public static function accept(MemberPortalDocument $doc, $reviewer)
    {
        if ($doc->status !== 'uploaded') {
            throw new Exception("Document cannot be accepted from its current status.");
        }

        $doc->update([
            'status' => 'accepted',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        self::logActivity($doc->diocese_id, $doc->church_id, $reviewer->id, $doc->family_id, $doc->member_id, 'document_accepted', "Accepted document: {$doc->original_file_name}");

        return $doc;
    }

    public static function reject(MemberPortalDocument $doc, string $reason, $reviewer)
    {
        if ($doc->status !== 'uploaded') {
            throw new Exception("Document cannot be rejected from its current status.");
        }

        $doc->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason
        ]);

        self::logActivity($doc->diocese_id, $doc->church_id, $reviewer->id, $doc->family_id, $doc->member_id, 'document_rejected', "Rejected document: {$doc->original_file_name}. Reason: {$reason}");

        return $doc;
    }

    public static function archive(MemberPortalDocument $doc, $updater)
    {
        $doc->update(['status' => 'archived']);

        self::logActivity($doc->diocese_id, $doc->church_id, $updater->id, $doc->family_id, $doc->member_id, 'document_archived', "Archived document: {$doc->original_file_name}");

        return $doc;
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
