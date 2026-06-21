<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsiteImportSource;
use App\Models\WebsiteImportRun;
use App\Models\WebsiteImportRecord;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\PriestTransferRequest;
use App\Models\MemberResponsibilityAssignment;
use App\Models\Member;
use App\Models\User;
use App\Models\Church;
use App\Services\WebsiteClergyImportService;
use App\Services\PriestAssignmentService;
use App\Services\ClergyTransferService;
use App\Services\MemberResponsibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Exception;

class ClergyAdminController extends Controller
{
    // ==========================================
    // 1. Website Import Sources & Runs
    // ==========================================

    public function listImportSources(Request $request)
    {
        $sources = WebsiteImportSource::with('diocese')->get();
        return response()->json(['status' => 'success', 'data' => $sources]);
    }

    public function createImportSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'source_type' => 'required|in:priests,parishes,administration,other',
            'source_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $source = WebsiteImportSource::create($validator->validated());
        return response()->json(['status' => 'success', 'data' => $source], 21);
    }

    public function triggerFetch(Request $request, $id)
    {
        $source = WebsiteImportSource::findOrFail($id);
        try {
            $run = WebsiteClergyImportService::fetchAndParse($source, $request->user());
            return response()->json(['status' => 'success', 'data' => $run]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listImportRuns(Request $request)
    {
        $runs = WebsiteImportRun::with(['source'])->orderBy('started_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $runs]);
    }

    public function getImportRun(Request $request, $id)
    {
        $run = WebsiteImportRun::with(['source', 'records'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $run]);
    }

    public function getImportRecords(Request $request, $id)
    {
        $run = WebsiteImportRun::findOrFail($id);
        $records = WebsiteImportRecord::where('import_run_id', $run->id)->get();
        return response()->json(['status' => 'success', 'data' => $records]);
    }

    public function acceptImportRecord(Request $request, $id)
    {
        $record = WebsiteImportRecord::findOrFail($id);
        try {
            WebsiteClergyImportService::acceptRecord($record, $request->user());
            return response()->json(['status' => 'success', 'message' => 'Record imported successfully.']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function linkImportRecordMember(Request $request, $id)
    {
        $record = WebsiteImportRecord::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            WebsiteClergyImportService::linkMember($record, $request->input('member_id'), $request->user());
            return response()->json(['status' => 'success', 'message' => 'Record matched manually.']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function ignoreImportRecord(Request $request, $id)
    {
        $record = WebsiteImportRecord::findOrFail($id);
        try {
            WebsiteClergyImportService::ignoreRecord($record, $request->user());
            return response()->json(['status' => 'success', 'message' => 'Record ignored.']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. Priest Profiles
    // ==========================================

    public function listPriests(Request $request)
    {
        $priests = PriestProfile::with(['member', 'user', 'assignments.church'])->get();
        return response()->json(['status' => 'success', 'data' => $priests]);
    }

    public function createPriest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'member_id' => 'required|exists:members,id',
            'display_name' => 'required|string|max:255',
            'ordination_name' => 'nullable|string|max:255',
            'clergy_type' => 'required|string',
            'ordination_date' => 'nullable|date',
            'email_public' => 'nullable|email',
            'phone_public' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $profile = PriestProfile::create($validator->validated());
        return response()->json(['status' => 'success', 'data' => $profile], 201);
    }

    public function getPriest(Request $request, $id)
    {
        $priest = PriestProfile::with(['member', 'user', 'assignments.church'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $priest]);
    }

    public function updatePriest(Request $request, $id)
    {
        $priest = PriestProfile::findOrFail($id);
        $priest->update($request->all());
        return response()->json(['status' => 'success', 'data' => $priest]);
    }

    public function createPriestLogin(Request $request, $id)
    {
        $priest = PriestProfile::findOrFail($id);
        
        if ($priest->user_id) {
            return response()->json(['status' => 'error', 'message' => 'Priest already has a login account.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $priest->display_name,
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'default_diocese_id' => $priest->diocese_id,
            'is_active' => true,
        ]);
        $user->assignRole('Priest / Vicar');

        $priest->update(['user_id' => $user->id]);

        return response()->json(['status' => 'success', 'message' => 'Priest login created successfully.', 'data' => $user]);
    }

    public function archivePriest(Request $request, $id)
    {
        $priest = PriestProfile::findOrFail($id);
        $priest->update(['status' => 'retired']);
        return response()->json(['status' => 'success', 'message' => 'Priest profile archived.']);
    }

    // ==========================================
    // 3. Assignments
    // ==========================================

    public function listAssignments(Request $request)
    {
        $assignments = PriestChurchAssignment::with(['priestProfile', 'church'])->get();
        return response()->json(['status' => 'success', 'data' => $assignments]);
    }

    public function createAssignment(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_priest_assignments')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'priest_profile_id' => 'required|exists:priest_profiles,id',
            'church_id' => 'required|exists:churches,id',
            'assignment_role' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $assignment = PriestAssignmentService::assignPriest($validator->validated(), $request->user());
            return response()->json(['status' => 'success', 'data' => $assignment], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getAssignment(Request $request, $id)
    {
        $assignment = PriestChurchAssignment::with(['priestProfile', 'church'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $assignment]);
    }

    public function updateAssignment(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_priest_assignments')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $assignment = PriestChurchAssignment::findOrFail($id);
        $assignment->update($request->all());
        return response()->json(['status' => 'success', 'data' => $assignment]);
    }

    public function endAssignment(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('manage_priest_assignments')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $assignment = PriestChurchAssignment::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'end_date' => 'required|date',
            'end_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $assignment = PriestAssignmentService::endAssignment(
                $assignment, 
                $request->input('end_date'), 
                $request->input('end_reason'), 
                $request->user()
            );
            return response()->json(['status' => 'success', 'data' => $assignment]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getPriestAssignments(Request $request, $id)
    {
        $assignments = PriestChurchAssignment::where('priest_profile_id', $id)->with('church')->get();
        return response()->json(['status' => 'success', 'data' => $assignments]);
    }

    public function getChurchClergy(Request $request, $id)
    {
        $assignments = PriestChurchAssignment::where('church_id', $id)->where('status', 'active')->with('priestProfile')->get();
        return response()->json(['status' => 'success', 'data' => $assignments]);
    }

    // ==========================================
    // 4. Transfers
    // ==========================================

    public function listTransfers(Request $request)
    {
        $transfers = PriestTransferRequest::with(['priestProfile', 'fromChurch', 'toChurch'])->get();
        return response()->json(['status' => 'success', 'data' => $transfers]);
    }

    public function createTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'priest_profile_id' => 'required|exists:priest_profiles,id',
            'from_church_id' => 'nullable|exists:churches,id',
            'to_church_id' => 'required|exists:churches,id',
            'new_assignment_role' => 'required|string',
            'effective_date' => 'required|date',
            'transfer_type' => 'required|in:new_assignment,transfer,additional_charge,temporary_charge,end_assignment',
            'appointment_reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $transfer = ClergyTransferService::createTransferRequest($validator->validated(), $request->user());
        return response()->json(['status' => 'success', 'data' => $transfer], 201);
    }

    public function getTransfer(Request $request, $id)
    {
        $transfer = PriestTransferRequest::with(['priestProfile', 'fromChurch', 'toChurch'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $transfer]);
    }

    public function approveTransfer(Request $request, $id)
    {
        $transfer = PriestTransferRequest::findOrFail($id);
        try {
            $transfer = ClergyTransferService::approveTransferRequest($transfer, $request->user());
            return response()->json(['status' => 'success', 'data' => $transfer]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function completeTransfer(Request $request, $id)
    {
        $transfer = PriestTransferRequest::findOrFail($id);
        try {
            $transfer = ClergyTransferService::completeTransfer($transfer, $request->user());
            return response()->json(['status' => 'success', 'data' => $transfer]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelTransfer(Request $request, $id)
    {
        $transfer = PriestTransferRequest::findOrFail($id);
        try {
            $transfer = ClergyTransferService::cancelTransferRequest($transfer, $request->user());
            return response()->json(['status' => 'success', 'data' => $transfer]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // ==========================================
    // 5. Responsibilities
    // ==========================================

    public function listResponsibilities(Request $request)
    {
        $responsibilities = MemberResponsibilityAssignment::with(['member', 'church', 'programmeAccount'])->get();
        return response()->json(['status' => 'success', 'data' => $responsibilities]);
    }

    public function createResponsibility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'church_id' => 'nullable|exists:churches,id',
            'responsibility_type' => 'required|string',
            'designation' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'programme_account_id' => 'nullable|exists:finance_programme_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $responsibility = MemberResponsibilityService::assignResponsibility($validator->validated(), $request->user());
        return response()->json(['status' => 'success', 'data' => $responsibility], 201);
    }

    public function getResponsibility(Request $request, $id)
    {
        $responsibility = MemberResponsibilityAssignment::with(['member', 'church', 'programmeAccount'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $responsibility]);
    }

    public function updateResponsibility(Request $request, $id)
    {
        $responsibility = MemberResponsibilityAssignment::findOrFail($id);
        $responsibility->update($request->all());
        return response()->json(['status' => 'success', 'data' => $responsibility]);
    }

    public function endResponsibility(Request $request, $id)
    {
        $responsibility = MemberResponsibilityAssignment::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'end_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $responsibility = MemberResponsibilityService::endResponsibility($responsibility, $request->input('end_date'), $request->user());
        return response()->json(['status' => 'success', 'data' => $responsibility]);
    }

    public function getMemberResponsibilities(Request $request, $id)
    {
        $responsibilities = MemberResponsibilityAssignment::where('member_id', $id)->with('church')->get();
        return response()->json(['status' => 'success', 'data' => $responsibilities]);
    }

    public function getChurchOfficeBearers(Request $request, $id)
    {
        if (!\App\Services\ChurchAccessService::canAccessChurch($request->user(), $id)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $responsibilities = MemberResponsibilityAssignment::where('church_id', $id)->where('status', 'active')->with('member')->get();
        return response()->json(['status' => 'success', 'data' => $responsibilities]);
    }
}
