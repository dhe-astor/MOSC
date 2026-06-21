<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\MemberPortalAccessService;
use App\Services\MemberPortalProfileService;
use App\Services\MemberPortalDocumentService;
use App\Services\MemberPortalCertificateService;
use App\Services\MemberPortalEventCourseService;
use App\Services\MemberPortalSundaySchoolService;
use App\Services\MemberPortalFinanceService;
use App\Services\MemberPortalTransferService;
use App\Services\MemberPortalSecurity;
use App\Models\MemberPortalAccess;
use App\Models\MemberPortalDocument;
use App\Models\ProfileCorrectionRequest;
use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\Receipt;
use App\Models\FamilyTransferRequest;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\MemberPortalActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class MemberPortalController extends Controller
{
    use ApiResponse;

    public function me(Request $request)
    {
        $user = $request->user();
        $contexts = MemberPortalAccessService::getPortalContexts($user);

        return $this->successResponse([
            'user' => $user,
            'contexts' => $contexts
        ], 'Portal user context retrieved successfully');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Ensure user has at least one active portal access
        $accesses = MemberPortalAccess::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        if ($accesses->isEmpty() && !$user->hasAnyRole(['Super Admin', 'Diocese Admin', 'Diocese Secretary', 'Priest Secretary', 'Parish Admin', 'Priest / Vicar'])) {
            return $this->errorResponse('Portal access not active.', 403);
        }

        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        $unreadNotificationsCount = Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $recentLogs = MemberPortalActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $this->successResponse([
            'unread_notifications_count' => $unreadNotificationsCount,
            'recent_activity' => $recentLogs,
            'stats' => [
                'members_count' => count($memberIds),
                'families_count' => count($familyIds),
            ]
        ], 'Dashboard data retrieved successfully');
    }

    public function familyProfile(Request $request)
    {
        $user = $request->user();
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        if (empty($familyIds)) {
            return $this->errorResponse('No bound family profile found.', 404);
        }

        try {
            $familyId = $request->input('family_id', $familyIds[0]);
            $family = MemberPortalProfileService::getFamilyProfile($familyId, $user);
            return $this->successResponse($family, 'Family profile retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function familyMembers(Request $request)
    {
        $user = $request->user();
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        if (empty($familyIds)) {
            return $this->errorResponse('No bound family found.', 404);
        }

        try {
            $familyId = $request->input('family_id', $familyIds[0]);
            if (!MemberPortalSecurity::validateFamilyAccess($user, $familyId)) {
                return $this->errorResponse('Unauthorized to access family members.', 403);
            }
            $members = \App\Models\Member::where('family_id', $familyId)->get()
                ->makeHidden(['created_by', 'updated_by', 'approved_by', 'approved_at']);
            return $this->successResponse($members, 'Family members retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function memberProfile(Request $request, $id)
    {
        try {
            $member = MemberPortalProfileService::getMemberProfile($id, $request->user());
            return $this->successResponse($member, 'Member profile retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function listCorrectionRequests(Request $request)
    {
        $user = $request->user();
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        $requests = ProfileCorrectionRequest::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', $familyIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($requests, 'Correction requests retrieved successfully');
    }

    public function storeCorrectionRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'family_id' => 'nullable|integer|exists:families,id',
            'member_id' => 'nullable|integer|exists:members,id',
            'request_type' => 'required|string|in:family_profile,member_profile,contact_details,address,relationship,other',
            'requested_data' => 'required|array',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $correction = MemberPortalProfileService::createCorrectionRequest($validator->validated(), $request->user());
            return $this->successResponse($correction, 'Profile correction request submitted successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function showCorrectionRequest(Request $request, $id)
    {
        $user = $request->user();
        $correction = ProfileCorrectionRequest::findOrFail($id);

        $authorized = false;
        if ($correction->member_id && MemberPortalSecurity::validateMemberAccess($user, $correction->member_id)) {
            $authorized = true;
        } elseif ($correction->family_id && MemberPortalSecurity::validateFamilyAccess($user, $correction->family_id)) {
            $authorized = true;
        }

        if (!$authorized) {
            return $this->errorResponse('Unauthorized to view this request.', 403);
        }

        return $this->successResponse($correction, 'Correction request retrieved successfully');
    }

    public function cancelCorrectionRequest(Request $request, $id)
    {
        $correction = ProfileCorrectionRequest::findOrFail($id);
        try {
            $cancelled = MemberPortalProfileService::cancelCorrectionRequest($correction, $request->user());
            return $this->successResponse($cancelled, 'Correction request cancelled successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listDocuments(Request $request)
    {
        $user = $request->user();
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        $documents = MemberPortalDocument::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', $familyIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($documents, 'Documents retrieved successfully');
    }

    public function storeDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:5120|mimes:pdf,jpeg,jpg,png',
            'member_id' => 'nullable|integer|exists:members,id',
            'family_id' => 'nullable|integer|exists:families,id',
            'document_type' => 'required|string|in:id_proof,baptism_certificate,marriage_certificate,address_proof,transfer_letter,consent_form,other',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $doc = MemberPortalDocumentService::upload(
                $request->file('file'),
                $validator->validated(),
                $request->user()
            );
            return $this->successResponse($doc, 'Document uploaded successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function downloadDocument(Request $request, $id)
    {
        $doc = MemberPortalDocument::findOrFail($id);
        try {
            return MemberPortalDocumentService::download($doc, $request->user());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function archiveDocument(Request $request, $id)
    {
        $doc = MemberPortalDocument::findOrFail($id);
        if (!MemberPortalSecurity::validateDocumentAccess($request->user(), $doc->id)) {
            return $this->errorResponse('Unauthorized to archive this document.', 403);
        }

        try {
            $archived = MemberPortalDocumentService::archive($doc, $request->user());
            return $this->successResponse($archived, 'Document archived successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listCertificateRequests(Request $request)
    {
        return $this->successResponse(
            MemberPortalCertificateService::listRequests($request->user()),
            'Certificate requests retrieved successfully'
        );
    }

    public function storeCertificateRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'nullable|integer|exists:members,id',
            'family_id' => 'nullable|integer|exists:families,id',
            'certificate_type' => 'required|string|max:100',
            'purpose' => 'required|string|max:255',
            'sacrament_id' => 'nullable|integer|exists:sacraments,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $certReq = MemberPortalCertificateService::createRequest($validator->validated(), $request->user());
            return $this->successResponse($certReq, 'Certificate request submitted successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function showCertificateRequest(Request $request, $id)
    {
        try {
            $certReq = MemberPortalCertificateService::getRequest($id, $request->user());
            return $this->successResponse($certReq, 'Certificate request retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function listCertificates(Request $request)
    {
        return $this->successResponse(
            MemberPortalCertificateService::listCertificates($request->user()),
            'Certificates retrieved successfully'
        );
    }

    public function downloadCertificate(Request $request, $id)
    {
        $cert = Certificate::findOrFail($id);
        try {
            return MemberPortalCertificateService::download($cert, $request->user());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function listEvents(Request $request)
    {
        return $this->successResponse(
            MemberPortalEventCourseService::getEvents($request->user()),
            'Events retrieved successfully'
        );
    }

    public function registerEvent(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
            'participant_count' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['event_id'] = $id;

        try {
            $reg = MemberPortalEventCourseService::registerEvent($data, $request->user());
            return $this->successResponse($reg, 'Registered for event successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listEventRegistrations(Request $request)
    {
        return $this->successResponse(
            MemberPortalEventCourseService::getEventRegistrations($request->user()),
            'Event registrations retrieved successfully'
        );
    }

    public function listCourses(Request $request)
    {
        return $this->successResponse(
            MemberPortalEventCourseService::getCourses($request->user()),
            'Courses retrieved successfully'
        );
    }

    public function registerCourseBatch(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['course_batch_id'] = $id;

        try {
            $reg = MemberPortalEventCourseService::registerCourseBatch($data, $request->user());
            return $this->successResponse($reg, 'Registered for course batch successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listCourseRegistrations(Request $request)
    {
        return $this->successResponse(
            MemberPortalEventCourseService::getCourseRegistrations($request->user()),
            'Course registrations retrieved successfully'
        );
    }

    public function listChildren(Request $request)
    {
        return $this->successResponse(
            MemberPortalSundaySchoolService::getChildren($request->user()),
            'Children retrieved successfully'
        );
    }

    public function childSundaySchool(Request $request, $id)
    {
        try {
            return $this->successResponse(
                MemberPortalSundaySchoolService::getStudentRecords($id, $request->user()),
                'Child Sunday School records retrieved successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function childAttendance(Request $request, $id)
    {
        try {
            return $this->successResponse(
                MemberPortalSundaySchoolService::getAttendance($id, $request->user()),
                'Child attendance retrieved successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function childMarks(Request $request, $id)
    {
        try {
            return $this->successResponse(
                MemberPortalSundaySchoolService::getMarks($id, $request->user()),
                'Child marks retrieved successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function childProgressReports(Request $request, $id)
    {
        try {
            return $this->successResponse(
                MemberPortalSundaySchoolService::getProgressReports($id, $request->user()),
                'Child progress reports retrieved successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function childCertificates(Request $request, $id)
    {
        try {
            return $this->successResponse(
                MemberPortalSundaySchoolService::getCertificates($id, $request->user()),
                'Child certificates retrieved successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function listDonations(Request $request)
    {
        return $this->successResponse(
            MemberPortalFinanceService::getDonations($request->user()),
            'Donations retrieved successfully'
        );
    }

    public function listReceipts(Request $request)
    {
        return $this->successResponse(
            MemberPortalFinanceService::getReceipts($request->user()),
            'Receipts retrieved successfully'
        );
    }

    public function downloadReceipt(Request $request, $id)
    {
        $type = $request->query('type', 'legacy');
        try {
            return MemberPortalFinanceService::downloadReceipt($type, $id, $request->user());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function listTransferRequests(Request $request)
    {
        return $this->successResponse(
            MemberPortalTransferService::listRequests($request->user()),
            'Transfer requests retrieved successfully'
        );
    }

    public function storeTransferRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'family_id' => 'required|integer|exists:families,id',
            'to_church_id' => 'required|integer|exists:churches,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $transfer = MemberPortalTransferService::createRequest($validator->validated(), $request->user());
            return $this->successResponse($transfer, 'Transfer request submitted successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function showTransferRequest(Request $request, $id)
    {
        try {
            $transfer = MemberPortalTransferService::getRequest($id, $request->user());
            return $this->successResponse($transfer, 'Transfer request retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function cancelTransferRequest(Request $request, $id)
    {
        $transfer = FamilyTransferRequest::findOrFail($id);
        try {
            $cancelled = MemberPortalTransferService::cancel($transfer, $request->user());
            return $this->successResponse($cancelled, 'Transfer request cancelled successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function listNotifications(Request $request)
    {
        $notifications = Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($notifications, 'Notifications retrieved successfully');
    }

    public function markNotificationRead(Request $request, $id)
    {
        $notification = Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id)
            ->findOrFail($id);
        $notification->update(['read_at' => now()]);

        // Write activity log
        MemberPortalActivityLog::create([
            'diocese_id' => $notification->diocese_id ?? 1,
            'church_id' => null,
            'user_id' => $request->user()->id,
            'action' => 'notification_marked_read',
            'description' => "Marked notification #{$notification->id} as read"
        ]);

        return $this->successResponse($notification, 'Notification marked as read');
    }

    public function getPreferences(Request $request)
    {
        $pref = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'email_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'in_app_enabled' => true,
                'critical_bypass' => true,
            ]
        );

        return $this->successResponse($pref, 'Notification preferences retrieved successfully');
    }

    public function updatePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_enabled' => 'required|boolean',
            'sms_enabled' => 'required|boolean',
            'whatsapp_enabled' => 'required|boolean',
            'in_app_enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $pref = NotificationPreference::where('user_id', $request->user()->id)->first();
        if ($pref) {
            $pref->update($validator->validated());
        } else {
            $pref = NotificationPreference::create(array_merge(
                ['user_id' => $request->user()->id, 'critical_bypass' => true],
                $validator->validated()
            ));
        }

        // Write activity log
        MemberPortalActivityLog::create([
            'diocese_id' => 1,
            'church_id' => null,
            'user_id' => $request->user()->id,
            'action' => 'preferences_updated',
            'description' => "Updated notification preferences"
        ]);

        return $this->successResponse($pref, 'Notification preferences updated successfully');
    }
}
