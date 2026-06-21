<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberChangeRequest;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use App\Services\MemberApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class MemberController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if (Gate::denies('viewAny', Member::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = Member::with(['diocese', 'church', 'family']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('baptism_name', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('church_id')) {
            $query->where('church_id', $request->input('church_id'));
        }

        if ($request->has('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        if ($request->has('membership_status')) {
            $query->where('membership_status', $request->input('membership_status'));
        }

        if ($request->has('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->has('marital_status')) {
            $query->where('marital_status', $request->input('marital_status'));
        }

        if ($request->has('student_status')) {
            $query->where('student_status', $request->input('student_status'));
        }

        // Age group filters
        if ($request->has('age_group')) {
            $group = $request->input('age_group');
            $today = Carbon::today();
            if ($group === 'youth') {
                // Youth is typically 15 to 35
                $query->whereBetween('date_of_birth', [
                    $today->copy()->subYears(35)->toDateString(),
                    $today->copy()->subYears(15)->toDateString()
                ]);
            } elseif ($group === 'sunday_school') {
                // Sunday school age typically < 15
                $query->where('date_of_birth', '>', $today->copy()->subYears(15)->toDateString());
            }
        }

        // GDPR Directory filter (excludes children and requires active + opt-in)
        if ($request->has('directory') && filter_var($request->input('directory'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('show_in_directory', true)
                  ->where('membership_status', 'active')
                  ->where(function($q) {
                      $eighteenYearsAgo = Carbon::now()->subYears(18)->toDateString();
                      $q->whereNotNull('date_of_birth')
                        ->where('date_of_birth', '<=', $eighteenYearsAgo);
                  });
        }

        $perPage = $request->input('per_page', 50);
        $members = $query->orderBy('first_name')->paginate($perPage);

        return $this->paginatedResponse($members, 'Members retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $member = Member::with(['diocese', 'church', 'family', 'documents', 'changeRequests.requester'])->find($id);

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        if (Gate::denies('view', $member)) {
            return $this->errorResponse('You do not have access to this member', 403);
        }

        return $this->successResponse($member, 'Member details retrieved successfully');
    }

    public function store(Request $request)
    {
        if (Gate::denies('create', Member::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'family_id' => 'required|exists:families,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'baptism_name' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other,prefer_not_to_say',
            'date_of_birth' => 'required|date',
            'relationship_to_head' => 'required|in:head,spouse,son,daughter,father,mother,brother,sister,relative,other',
            'phone' => 'nullable|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'employer_or_school' => 'nullable|string|max:255',
            'student_status' => 'required|boolean',
            'marital_status' => 'required|in:single,married,widowed,divorced,separated,not_applicable',
            'address_same_as_family' => 'required|boolean',
            'individual_address' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'gdpr_consent' => 'required|boolean',
            'communication_consent' => 'required|boolean',
            'show_in_directory' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $family = Family::findOrFail($data['family_id']);

        if (!ChurchAccessService::canAccessChurch($request->user(), $family->church_id)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        $data['diocese_id'] = $family->diocese_id;
        $data['church_id'] = $family->church_id;
        $data['membership_status'] = 'pending';
        $middleNamePart = !empty($data['middle_name']) ? $data['middle_name'] . ' ' : '';
        $data['full_name'] = trim($data['first_name'] . ' ' . $middleNamePart . $data['last_name']);
        $data['created_by'] = $request->user()->id;

        $member = Member::create($data);

        AuditLogService::log(
            'members',
            'member_created',
            "Member '{$member->full_name}' created in pending approval status",
            null,
            $member->toArray(),
            $member,
            $member->church_id
        );

        return $this->successResponse($member, 'Member created successfully in pending approval status', 201);
    }

    public function update(Request $request, $id)
    {
        $member = Member::find($id);

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        if (Gate::denies('update', $member)) {
            return $this->errorResponse('You do not have access to this member', 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'baptism_name' => 'nullable|string|max:255',
            'gender' => 'sometimes|required|in:male,female,other,prefer_not_to_say',
            'date_of_birth' => 'sometimes|required|date',
            'relationship_to_head' => 'sometimes|required|in:head,spouse,son,daughter,father,mother,brother,sister,relative,other',
            'phone' => 'nullable|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'employer_or_school' => 'nullable|string|max:255',
            'student_status' => 'sometimes|required|boolean',
            'marital_status' => 'sometimes|required|in:single,married,widowed,divorced,separated,not_applicable',
            'address_same_as_family' => 'sometimes|required|boolean',
            'individual_address' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'gdpr_consent' => 'sometimes|required|boolean',
            'communication_consent' => 'sometimes|required|boolean',
            'show_in_directory' => 'sometimes|required|boolean',
            'membership_status' => 'sometimes|required|in:pending,active,inactive,transferred,deceased,suspended,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $input = $validator->validated();

        // Separate sensitive fields
        $sensitiveFields = [
            'first_name',
            'middle_name',
            'last_name',
            'baptism_name',
            'gender',
            'date_of_birth',
            'relationship_to_head',
            'membership_status',
        ];

        $sensitiveChanges = [];
        $nonSensitiveChanges = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                // Only consider changed values
                if ($member->$key != $value) {
                    $sensitiveChanges[$key] = [
                        'old' => $member->$key,
                        'new' => $value
                    ];
                }
            } else {
                if ($member->$key != $value) {
                    $nonSensitiveChanges[$key] = $value;
                }
            }
        }

        // Apply non-sensitive changes directly if any exist
        if (!empty($nonSensitiveChanges)) {
            $oldValues = $member->toArray();
            $nonSensitiveChanges['updated_by'] = $request->user()->id;
            
            // If changing first_name, middle_name, last_name, we update full_name, but those are sensitive anyway.
            $member->update($nonSensitiveChanges);

            AuditLogService::log(
                'members',
                'member_updated_direct',
                "Member '{$member->full_name}' updated directly (non-sensitive fields)",
                $oldValues,
                $member->toArray(),
                $member,
                $member->church_id
            );
        }

        // If there are sensitive changes, save them as a MemberChangeRequest
        if (!empty($sensitiveChanges)) {
            $changeRequest = MemberChangeRequest::create([
                'member_id' => $member->id,
                'family_id' => $member->family_id,
                'church_id' => $member->church_id,
                'requested_by' => $request->user()->id,
                'change_type' => 'profile_update',
                'old_data' => collect($sensitiveChanges)->mapWithKeys(fn($item, $key) => [$key => $item['old']])->toArray(),
                'new_data' => collect($sensitiveChanges)->mapWithKeys(fn($item, $key) => [$key => $item['new']])->toArray(),
                'status' => 'submitted',
            ]);

            AuditLogService::log(
                'members',
                'member_change_requested',
                "Sensitive changes requested for Member '{$member->full_name}'",
                null,
                $changeRequest->toArray(),
                $member,
                $member->church_id
            );

            return $this->successResponse([
                'member' => $member,
                'change_request' => $changeRequest
            ], 'Non-sensitive updates applied directly. Sensitive updates submitted for review.');
        }

        return $this->successResponse($member, 'Member updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $member = Member::find($id);

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        if (Gate::denies('delete', $member)) {
            return $this->errorResponse('You do not have access to this member', 403);
        }

        $oldValues = $member->toArray();

        // Mark inactive first before soft deleting
        $member->membership_status = 'inactive';
        $member->save();
        $member->delete();

        AuditLogService::log(
            'members',
            'member_deactivated',
            "Member '{$member->full_name}' deactivated and soft-deleted successfully",
            $oldValues,
            $member->toArray(),
            $member,
            $member->church_id
        );

        return $this->successResponse([], 'Member soft-deleted successfully');
    }

    public function markDeceased(Request $request, $id)
    {
        $member = Member::find($id);

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        // Scoped access control check
        if (!ChurchAccessService::canAccessChurch($request->user(), $member->church_id)) {
            return $this->errorResponse('You do not have access to this member', 403);
        }

        // Mark as deceased requires approval workflow via change request
        $changeRequest = MemberChangeRequest::create([
            'member_id' => $member->id,
            'family_id' => $member->family_id,
            'church_id' => $member->church_id,
            'requested_by' => $request->user()->id,
            'change_type' => 'deceased_update',
            'old_data' => ['membership_status' => $member->membership_status],
            'new_data' => ['membership_status' => 'deceased'],
            'status' => 'submitted',
        ]);

        AuditLogService::log(
            'members',
            'member_deceased_requested',
            "Requested marking Member '{$member->full_name}' as deceased",
            null,
            $changeRequest->toArray(),
            $member,
            $member->church_id
        );

        return $this->successResponse($changeRequest, 'Deceased status request submitted for review');
    }

    public function approve(Request $request, MemberApprovalService $approvalService, $id)
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        if ($request->user()->cannot('approve', $member)) {
            return $this->errorResponse('Unauthorized to approve this member', 403);
        }

        $code = $approvalService->approve($member, $request->user());

        return $this->successResponse($member, "Member approved successfully. Assigned code: {$code}");
    }

    public function reject(Request $request, MemberApprovalService $approvalService, $id)
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        if ($request->user()->cannot('approve', $member)) {
            return $this->errorResponse('Unauthorized to reject this member', 403);
        }

        $approvalService->reject($member, $request->user());

        return $this->successResponse([], 'Member rejected and soft-deleted successfully');
    }
}
