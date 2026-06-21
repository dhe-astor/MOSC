<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\CertificateRequest;
use App\Models\Church;
use App\Services\PriestAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class PriestPortalController extends Controller
{
    /**
     * Get dashboard details for the logged-in priest.
     */
    public function getDashboard(Request $request)
    {
        $user = $request->user();
        $profile = PriestProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'No priest profile found for this user.'], 404);
        }

        $activeAssignments = PriestAssignmentService::getActiveAssignmentsForPriest($profile->id);
        $churchIds = $activeAssignments->pluck('church_id')->toArray();

        // Count pending certificate requests in these churches
        $pendingCertificates = CertificateRequest::whereIn('church_id', $churchIds)
            ->where('status', 'pending')
            ->count();

        // Fetch priest payments scoped to this profile
        $payments = \DB::table('finance_priest_payments')
            ->where('priest_profile_id', $profile->id)
            ->orderBy('payment_date', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'profile' => $profile,
                'active_assignments' => $activeAssignments->load('church'),
                'pending_certificates_count' => $pendingCertificates,
                'recent_payments' => $payments,
            ]
        ]);
    }

    /**
     * Get active assigned churches for switcher.
     */
    public function getAssignedChurches(Request $request)
    {
        $user = $request->user();
        $profile = PriestProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'No priest profile found.'], 404);
        }

        $assignments = PriestAssignmentService::getActiveAssignmentsForPriest($profile->id);
        $churches = Church::whereIn('id', $assignments->pluck('church_id'))->get();

        return response()->json(['status' => 'success', 'data' => $churches]);
    }

    /**
     * Switch active church context.
     */
    public function switchChurch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'church_id' => 'required|integer|exists:churches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $churchId = $request->input('church_id');

        if (!PriestAssignmentService::canAccessChurch($user, $churchId)) {
            return response()->json(['status' => 'error', 'message' => 'Access denied to this church.'], 403);
        }

        $user->update(['active_church_id' => $churchId]);

        return response()->json([
            'status' => 'success',
            'message' => 'Church context switched successfully.',
            'data' => ['active_church_id' => $churchId]
        ]);
    }

    /**
     * Get priest assignments history.
     */
    public function getAssignments(Request $request)
    {
        $user = $request->user();
        $profile = PriestProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'No priest profile found.'], 404);
        }

        $assignments = PriestChurchAssignment::where('priest_profile_id', $profile->id)
            ->with('church')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $assignments]);
    }
}
