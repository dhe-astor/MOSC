<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Require2Fa
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user requires 2FA
        if ($user->requires2Fa()) {
            if (!$user->two_factor_enabled) {
                return response()->json([
                    'message' => 'Two-factor authentication is required. Please enable 2FA in your settings.',
                    'code' => '2fa_required'
                ], 403);
            }

            // Check if Sanctum token is 2FA-verified
            if (!$request->user()->tokenCan('2fa_verified')) {
                return response()->json([
                    'message' => 'Two-factor verification required.',
                    'code' => '2fa_unverified'
                ], 403);
            }

            // For very sensitive actions, require recent verification within 15 minutes
            if ($mode === 'recent') {
                $lastVerified = $user->two_factor_last_verified_at;
                if (!$lastVerified || $lastVerified->diffInMinutes(now()) > 15) {
                    return response()->json([
                        'message' => 'Recent 2FA verification required.',
                        'code' => '2fa_reverification_required'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
