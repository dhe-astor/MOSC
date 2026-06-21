<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\AuditLog;
use App\Services\ChurchAccessService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('view_audit_logs')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = AuditLog::with(['user', 'church']);

        // Scope by accessible churches if not diocese-wide
        if (!ChurchAccessService::hasDioceseAccess($user)) {
            $accessible = ChurchAccessService::getAccessibleChurchIds($user);
            if (empty($accessible)) {
                return $this->errorResponse('You do not have access to any church logs', 403);
            }
            $query->whereIn('church_id', $accessible);
        }

        // Filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('church_id')) {
            $churchId = $request->input('church_id');
            // Enforce access checking for specific church filter
            if (!ChurchAccessService::canAccessChurch($user, $churchId)) {
                return $this->errorResponse('You do not have access to this church logs', 403);
            }
            $query->where('church_id', $churchId);
        }

        if ($request->has('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $perPage = $request->input('per_page', 15);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($logs, 'Audit logs retrieved successfully');
    }
}
