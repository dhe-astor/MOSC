<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\AuditLogService;
use App\Services\EmailService;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class SecurityController extends Controller
{
    use ApiResponse;

    public function status(Request $request)
    {
        $user = $request->user();
        return $this->successResponse([
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
            'two_factor_last_verified_at' => $user->two_factor_last_verified_at,
            'requires_2fa' => $user->requires2Fa(),
        ]);
    }

    public function enable(Request $request)
    {
        $user = $request->user();

        $otp = app()->environment('production') ? sprintf("%06d", mt_rand(0, 999999)) : '123456';
        $user->two_factor_otp_hash = Hash::make($otp);
        $user->two_factor_otp_expires_at = now()->addMinutes(10);
        $user->save();

        try {
            EmailService::sendEmail(
                $user->email,
                "Confirm your MSOC 2FA Setup",
                "Your 2FA verification code is: {$otp}. Enter this code to confirm enabling two-factor authentication."
            );
        } catch (\Exception $e) {
            logger()->error("Failed sending enable 2FA email: " . $e->getMessage());
        }

        AuditLogService::log(
            'security',
            '2fa_enable_requested',
            "User {$user->email} requested 2FA activation",
            null, null, $user
        );

        return $this->successResponse([], 'Verification code sent to your email.');
    }

    public function verify(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        if (!$user->two_factor_otp_expires_at || $user->two_factor_otp_expires_at->isPast()) {
            return $this->errorResponse('Verification code has expired', 422);
        }

        if (!Hash::check($request->code, $user->two_factor_otp_hash)) {
            AuditLogService::log(
                'security',
                '2fa_verify_failed',
                "User {$user->email} failed 2FA confirmation: invalid code",
                null, null, $user
            );
            return $this->errorResponse('Invalid verification code', 422);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->two_factor_last_verified_at = now();
        $user->two_factor_otp_hash = null;
        $user->two_factor_otp_expires_at = null;
        $user->save();

        AuditLogService::log(
            'security',
            '2fa_enabled',
            "User {$user->email} successfully enabled 2FA",
            null, null, $user
        );

        return $this->successResponse([], 'Two-factor authentication enabled successfully.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        // Check if user holds roles/permissions that require 2FA
        // Temporarily set two_factor_enabled to false to check if they still require it due to roles/permissions
        $user->two_factor_enabled = false;
        if ($user->requires2Fa()) {
            $user->two_factor_enabled = true;
            $user->save();
            return $this->errorResponse('2FA is mandatory for your role or permissions and cannot be disabled.', 403);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_otp_hash = null;
        $user->two_factor_otp_expires_at = null;
        $user->save();

        AuditLogService::log(
            'security',
            '2fa_disabled',
            "User {$user->email} disabled 2FA",
            null, null, $user
        );

        return $this->successResponse([], 'Two-factor authentication disabled successfully.');
    }

    public function loginAudit(Request $request)
    {
        $user = $request->user();
        
        $query = AuditLog::whereIn('action', ['login', 'failed_login', 'login_2fa_success', 'failed_2fa']);

        // Non-admins only view their own logs
        if (!$user->hasPermissionTo('view_audit_logs')) {
            $query->where('user_id', $user->id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return $this->successResponse($logs);
    }

    public function sensitivePermissions(Request $request)
    {
        $sensitivePermissions = [
            'manage_roles',
            'manage_permissions',
            'export_member_reports',
            'export_child_reports',
            'export_finance_reports',
            'export_gdpr_reports',
            'export_audit_reports',
            'view_unmasked_report_contacts',
            'view_unmasked_notification_recipients',
            'download_report_exports',
            'cancel_receipts'
        ];

        $result = [];

        foreach ($sensitivePermissions as $permName) {
            $permission = Permission::findByName($permName, 'web');
            if ($permission) {
                // Find users holding it directly or via roles
                $users = User::permission($permName)->get(['id', 'name', 'email']);
                $result[] = [
                    'permission' => $permName,
                    'users_count' => $users->count(),
                    'users' => $users
                ];
            }
        }

        return $this->successResponse($result);
    }
}
