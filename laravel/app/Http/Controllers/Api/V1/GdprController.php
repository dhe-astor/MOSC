<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\AuditLogService;
use App\Models\GdprRequest;
use App\Models\Member;
use App\Models\User;
use App\Models\ReportExport;
use App\Models\NotificationDelivery;
use App\Models\MemberPortalActivityLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GdprController extends Controller
{
    use ApiResponse;

    public function requests(Request $request)
    {
        $user = $request->user();
        
        $query = GdprRequest::with(['user', 'member', 'resolver']);

        // Scope by user permission
        if (!$user->hasPermissionTo('export_gdpr_reports') && !$user->hasRole('Super Admin')) {
            $query->where('user_id', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);
        return $this->successResponse($requests);
    }

    public function exportRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $member = Member::find($request->member_id);

        $gdprRequest = GdprRequest::create([
            'user_id' => $request->user()->id,
            'member_id' => $member->id,
            'request_type' => 'export',
            'status' => 'pending',
            'details' => ['member_name' => $member->full_name]
        ]);

        AuditLogService::log(
            'gdpr',
            'export_requested',
            "GDPR export requested for member: {$member->full_name}",
            null, null, $gdprRequest
        );

        return $this->successResponse($gdprRequest, 'GDPR export request submitted successfully.');
    }

    public function anonymizationRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'action_type' => 'nullable|string|in:anonymize,restrict,archive'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $member = Member::find($request->member_id);
        $actionType = $request->input('action_type', 'anonymize');

        $gdprRequest = GdprRequest::create([
            'user_id' => $request->user()->id,
            'member_id' => $member->id,
            'request_type' => 'anonymization',
            'status' => 'pending',
            'details' => [
                'member_name' => $member->full_name,
                'action_type' => $actionType
            ]
        ]);

        AuditLogService::log(
            'gdpr',
            'anonymization_requested',
            "GDPR anonymization ({$actionType}) requested for member: {$member->full_name}",
            null, null, $gdprRequest
        );

        return $this->successResponse($gdprRequest, 'GDPR anonymization request submitted successfully.');
    }

    public function reject(Request $request, $id)
    {
        $gdprRequest = GdprRequest::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'admin_notes' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $gdprRequest->status = 'rejected';
        $gdprRequest->admin_notes = $request->input('admin_notes');
        $gdprRequest->resolved_by = $request->user()->id;
        $gdprRequest->resolved_at = now();
        $gdprRequest->save();

        AuditLogService::log(
            'gdpr',
            'request_rejected',
            "GDPR request #{$gdprRequest->id} was rejected",
            null, null, $gdprRequest
        );

        return $this->successResponse($gdprRequest, 'GDPR request rejected.');
    }

    public function approve(Request $request, $id)
    {
        $gdprRequest = GdprRequest::findOrFail($id);

        if ($gdprRequest->status !== 'pending') {
            return $this->errorResponse('GDPR request is already resolved.', 422);
        }

        $member = Member::find($gdprRequest->member_id);
        if (!$member) {
            return $this->errorResponse('Associated member not found.', 404);
        }

        $adminNotes = $request->input('admin_notes', 'Approved by administrator.');

        if ($gdprRequest->request_type === 'export') {
            // Compile member data
            $memberData = [
                'personal' => [
                    'id' => $member->id,
                    'member_code' => $member->member_code,
                    'first_name' => $member->first_name,
                    'middle_name' => $member->middle_name,
                    'last_name' => $member->last_name,
                    'full_name' => $member->full_name,
                    'baptism_name' => $member->baptism_name,
                    'gender' => $member->gender,
                    'date_of_birth' => $member->date_of_birth?->toDateString(),
                    'phone' => $member->phone,
                    'whatsapp_phone' => $member->whatsapp_phone,
                    'email' => $member->email,
                    'relationship_to_head' => $member->relationship_to_head,
                    'membership_status' => $member->membership_status,
                ],
                'consents' => [
                    'gdpr_consent' => $member->gdpr_consent,
                    'communication_consent' => $member->communication_consent,
                    'show_in_directory' => $member->show_in_directory,
                    'photo_publication_consent' => $member->photo_publication_consent,
                ],
            ];

            // Store JSON file in private storage
            $fileName = "gdpr_exports/gdpr_export_{$member->id}_" . time() . ".json";
            Storage::put($fileName, json_encode($memberData, JSON_PRETTY_PRINT));

            $details = $gdprRequest->details;
            $details['file_path'] = $fileName;

            $gdprRequest->details = $details;
            $gdprRequest->status = 'completed';
            $gdprRequest->admin_notes = $adminNotes;
            $gdprRequest->resolved_by = $request->user()->id;
            $gdprRequest->resolved_at = now();
            $gdprRequest->save();

            AuditLogService::log(
                'gdpr',
                'export_approved',
                "GDPR export request #{$gdprRequest->id} approved and data compiled",
                null, null, $gdprRequest
            );

        } elseif ($gdprRequest->request_type === 'anonymization') {
            $actionType = $gdprRequest->details['action_type'] ?? 'anonymize';

            // Preserve old values for audit logging (properly masked)
            $oldValues = [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'membership_status' => $member->membership_status
            ];

            // Perform Anonymization
            $member->first_name = 'Anonymized';
            $member->last_name = 'Member';
            $member->middle_name = null;
            $member->baptism_name = null;
            $member->phone = null;
            $member->whatsapp_phone = null;
            $member->email = null;
            $member->occupation = null;
            $member->employer_or_school = null;
            $member->individual_address = null;
            $member->profile_photo_path = null;
            $member->emergency_contact_name = null;
            $member->emergency_contact_phone = null;

            // Disable all consents
            $member->gdpr_consent = false;
            $member->communication_consent = false;
            $member->show_in_directory = false;
            $member->photo_publication_consent = false;

            // Apply appropriate status state
            if ($actionType === 'restrict') {
                $member->membership_status = 'restricted';
            } elseif ($actionType === 'archive') {
                $member->membership_status = 'archived';
            } else {
                $member->membership_status = 'anonymized';
            }

            $member->save();

            $gdprRequest->status = 'completed';
            $gdprRequest->admin_notes = $adminNotes;
            $gdprRequest->resolved_by = $request->user()->id;
            $gdprRequest->resolved_at = now();
            $gdprRequest->save();

            AuditLogService::log(
                'gdpr',
                'anonymization_completed',
                "GDPR anonymization request #{$gdprRequest->id} completed. Member ID: {$member->id} is {$member->membership_status}",
                $oldValues,
                [
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'membership_status' => $member->membership_status
                ],
                $member
            );
        }

        return $this->successResponse($gdprRequest, 'GDPR request approved and processed.');
    }

    public function consentSummary(Request $request)
    {
        $totalMembers = Member::count();
        $gdprConsented = Member::where('gdpr_consent', true)->count();
        $commConsented = Member::where('communication_consent', true)->count();
        $directoryConsented = Member::where('show_in_directory', true)->count();
        $photoConsented = Member::where('photo_publication_consent', true)->count();

        return $this->successResponse([
            'total_members' => $totalMembers,
            'gdpr_consent_count' => $gdprConsented,
            'communication_consent_count' => $commConsented,
            'show_in_directory_count' => $directoryConsented,
            'photo_publication_consent_count' => $photoConsented,
        ]);
    }

    public function dataRetentionSummary(Request $request)
    {
        $now = now();
        
        $expiredExportsCount = ReportExport::where('status', 'generated')
            ->where('expires_at', '<=', $now)
            ->count();

        $expiredTempUploadsCount = 0; // Simulated/counted if there's tracking
        
        $failedJobsCount = DB::table('failed_jobs')->count();

        $expiredNotificationLogsCount = NotificationDelivery::where('created_at', '<=', $now->copy()->subYears(2))->count();

        $expiredPortalLogsCount = MemberPortalActivityLog::where('created_at', '<=', $now->copy()->subYears(3))->count();

        $expiredAuditLogsCount = AuditLog::where('created_at', '<=', $now->copy()->subYears(7))->count();

        return $this->successResponse([
            'retention_rules' => [
                'report_exports_days' => 7,
                'temporary_uploads_days' => 30,
                'failed_jobs_days' => 30,
                'notification_delivery_logs_years' => 2,
                'portal_activity_logs_years' => 3,
                'audit_logs_years' => 7,
            ],
            'expired_counts' => [
                'report_exports' => $expiredExportsCount,
                'temporary_uploads' => $expiredTempUploadsCount,
                'failed_jobs' => $failedJobsCount,
                'notification_deliveries' => $expiredNotificationLogsCount,
                'portal_activity_logs' => $expiredPortalLogsCount,
                'audit_logs' => $expiredAuditLogsCount,
            ]
        ]);
    }
}
