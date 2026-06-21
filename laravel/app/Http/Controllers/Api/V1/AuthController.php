<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use App\Services\EmailService;
use App\Models\User;
use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLogService::log(
                'auth',
                'failed_login',
                "Failed login attempt for email: {$request->email}"
            );
            return $this->errorResponse('Invalid email or password', 401);
        }

        if (!$user->is_active) {
            AuditLogService::log(
                'auth',
                'failed_login',
                "Attempted login by inactive user: {$user->email}"
            );
            return $this->errorResponse('Your account is deactivated. Please contact support.', 403);
        }

        // Check if 2FA is required
        if ($user->requires2Fa()) {
            $otp = app()->environment('production') ? sprintf("%06d", mt_rand(0, 999999)) : '123456';
            $user->two_factor_otp_hash = Hash::make($otp);
            $user->two_factor_otp_expires_at = now()->addMinutes(10);
            $user->save();

            $tempToken = Str::random(60);
            Cache::put("login_2fa_{$tempToken}", $user->id, now()->addMinutes(10));

            try {
                EmailService::sendEmail(
                    $user->email,
                    "Your MSOC Portal 2FA Code",
                    "Your secure verification code is: {$otp}. This code expires in 10 minutes."
                );
            } catch (\Exception $e) {
                // If mail config is missing in dev/tests, we still want to log or proceed in tests
                logger()->error("Failed sending 2FA email: " . $e->getMessage());
            }

            AuditLogService::log(
                'auth',
                '2fa_otp_sent',
                "2FA OTP generated and sent to {$user->email}",
                null, null, $user
            );

            return $this->successResponse([
                'requires_2fa' => true,
                '2fa_token' => $tempToken
            ], 'Two-factor authentication code sent to your email.');
        }

        // Generate normal token (without 2fa_verified since they don't need 2FA)
        $token = $user->createToken('auth_token')->plainTextToken;

        // Auto-select active church
        $accessibleChurches = ChurchAccessService::getAccessibleChurchIds($user);
        $hasDiocese = ChurchAccessService::hasDioceseAccess($user);

        if (!$hasDiocese && !empty($accessibleChurches)) {
            if (count($accessibleChurches) === 1) {
                $user->active_church_id = $accessibleChurches[0];
            } elseif ($user->default_church_id && in_array($user->default_church_id, $accessibleChurches)) {
                $user->active_church_id = $user->default_church_id;
            } else {
                $user->active_church_id = $accessibleChurches[0];
            }
            $user->save();
        } elseif ($hasDiocese) {
            // Diocese wide admin can default to null (All Churches) or their default
            if ($user->default_church_id) {
                $user->active_church_id = $user->default_church_id;
                $user->save();
            }
        }

        $user->last_login_at = now();
        $user->save();

        Auth::login($user);

        AuditLogService::log(
            'auth',
            'login',
            "User {$user->email} logged in successfully"
        );

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->getUserData($user)
        ], 'Logged in successfully');
    }

    public function login2fa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            '2fa_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $tempToken = $request->input('2fa_token');
        $code = $request->input('code');

        $userId = Cache::get("login_2fa_{$tempToken}");
        if (!$userId) {
            AuditLogService::log(
                'auth',
                'failed_2fa',
                "Failed 2FA attempt: temporary token expired or invalid."
            );
            return $this->errorResponse('Invalid or expired 2FA token', 422);
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Check expiry
        if (!$user->two_factor_otp_expires_at || $user->two_factor_otp_expires_at->isPast()) {
            AuditLogService::log(
                'auth',
                'failed_2fa',
                "Failed 2FA attempt: OTP code has expired.",
                null, null, $user
            );
            return $this->errorResponse('Two-factor code has expired', 422);
        }

        // Verify OTP
        if (!Hash::check($code, $user->two_factor_otp_hash)) {
            AuditLogService::log(
                'auth',
                'failed_2fa',
                "Failed 2FA attempt: Invalid OTP code provided.",
                null, null, $user
            );
            return $this->errorResponse('Invalid two-factor code', 422);
        }

        // Clear OTP fields upon successful verification
        $user->two_factor_otp_hash = null;
        $user->two_factor_otp_expires_at = null;
        $user->two_factor_last_verified_at = now();
        if (!$user->two_factor_confirmed_at) {
            $user->two_factor_confirmed_at = now();
        }
        $user->save();

        Cache::forget("login_2fa_{$tempToken}");

        // Generate full token with 2fa_verified ability
        $token = $user->createToken('auth_token', ['2fa_verified'])->plainTextToken;

        // Auto-select active church
        $accessibleChurches = ChurchAccessService::getAccessibleChurchIds($user);
        $hasDiocese = ChurchAccessService::hasDioceseAccess($user);

        if (!$hasDiocese && !empty($accessibleChurches)) {
            if (count($accessibleChurches) === 1) {
                $user->active_church_id = $accessibleChurches[0];
            } elseif ($user->default_church_id && in_array($user->default_church_id, $accessibleChurches)) {
                $user->active_church_id = $user->default_church_id;
            } else {
                $user->active_church_id = $accessibleChurches[0];
            }
            $user->save();
        } elseif ($hasDiocese) {
            if ($user->default_church_id) {
                $user->active_church_id = $user->default_church_id;
                $user->save();
            }
        }

        $user->last_login_at = now();
        $user->save();

        Auth::login($user);

        AuditLogService::log(
            'auth',
            'login_2fa_success',
            "User {$user->email} completed 2FA and logged in successfully",
            null, null, $user
        );

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->getUserData($user)
        ], 'Logged in successfully');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
            AuditLogService::log(
                'auth',
                'logout',
                "User {$user->email} logged out successfully"
            );
        }

        return $this->successResponse([], 'Logged out successfully');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return $this->successResponse($this->getUserData($user));
    }

    public function activeChurch(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|integer|exists:churches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = $request->input('church_id');

        if ($churchId !== null) {
            if (!ChurchAccessService::canAccessChurch($user, $churchId)) {
                return $this->errorResponse('You do not have access to this church', 403);
            }
            $oldChurchId = $user->active_church_id;
            $user->active_church_id = $churchId;
            $user->save();

            AuditLogService::log(
                'auth',
                'active_church_changed',
                "Switched active church from ID " . ($oldChurchId ?? 'null') . " to {$churchId}",
                ['active_church_id' => $oldChurchId],
                ['active_church_id' => $churchId],
                $user
            );
        } else {
            // Switch to null (All Churches) - only allowed for diocese wide access
            if (!ChurchAccessService::hasDioceseAccess($user)) {
                return $this->errorResponse('Only users with diocese-wide access can select All Churches', 403);
            }
            $oldChurchId = $user->active_church_id;
            $user->active_church_id = null;
            $user->save();

            AuditLogService::log(
                'auth',
                'active_church_changed',
                "Switched active church from ID " . ($oldChurchId ?? 'null') . " to all churches",
                ['active_church_id' => $oldChurchId],
                ['active_church_id' => null],
                $user
            );
        }

        return $this->successResponse($this->getUserData($user), 'Active church updated successfully');
    }

    public function impersonate(Request $request)
    {
        if (app()->environment('production')) {
            return $this->errorResponse('Impersonation is blocked in production.', 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Generate token with 2fa_verified ability
        $token = $user->createToken('auth_token', ['2fa_verified'])->plainTextToken;

        // Auto-select active church
        $accessibleChurches = ChurchAccessService::getAccessibleChurchIds($user);
        $hasDiocese = ChurchAccessService::hasDioceseAccess($user);

        if (!$hasDiocese && !empty($accessibleChurches)) {
            if (count($accessibleChurches) === 1) {
                $user->active_church_id = $accessibleChurches[0];
            } elseif ($user->default_church_id && in_array($user->default_church_id, $accessibleChurches)) {
                $user->active_church_id = $user->default_church_id;
            } else {
                $user->active_church_id = $accessibleChurches[0];
            }
            $user->save();
        } elseif ($hasDiocese) {
            if ($user->default_church_id) {
                $user->active_church_id = $user->default_church_id;
                $user->save();
            }
        }

        $user->last_login_at = now();
        $user->save();

        // Authed using standard Auth manager
        if (method_exists(auth(), 'login')) {
            auth()->login($user);
        }

        AuditLogService::log(
            'auth',
            'impersonate_success',
            "Impersonated user {$user->email} successfully"
        );

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->getUserData($user)
        ], 'Impersonated successfully');
    }

    protected function getUserData(User $user): array
    {
        $user->load(['roles', 'permissions', 'defaultChurch', 'activeChurch']);

        // Fetch accessible churches detailed list
        $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
        if ($accessibleIds === null) {
            $churches = Church::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'short_name', 'canonical_status']);
        } else {
            $churches = Church::whereIn('id', $accessibleIds)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'short_name', 'canonical_status']);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_path' => $user->avatar_path,
            'preferred_language' => $user->preferred_language,
            'is_active' => $user->is_active,
            'default_diocese_id' => $user->default_diocese_id,
            'default_church_id' => $user->default_church_id,
            'active_church_id' => $user->active_church_id,
            'default_church' => $user->defaultChurch ? [
                'id' => $user->defaultChurch->id,
                'name' => $user->defaultChurch->name,
                'short_name' => $user->defaultChurch->short_name
            ] : null,
            'active_church' => $user->activeChurch ? [
                'id' => $user->activeChurch->id,
                'name' => $user->activeChurch->name,
                'short_name' => $user->activeChurch->short_name
            ] : null,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'has_diocese_access' => ChurchAccessService::hasDioceseAccess($user),
            'accessible_churches' => $churches
        ];
    }
}
