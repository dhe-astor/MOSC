<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SystemController extends Controller
{
    use ApiResponse;

    public function health(Request $request)
    {
        // Require at least view_dashboard permission
        if (!$request->user() || !$request->user()->hasPermissionTo('view_dashboard')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // 1. DB Connection Check
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected: ' . $e->getMessage();
        }

        // 2. Storage Writable Check
        $storageWritable = is_writable(storage_path('app'));
        
        // 3. Cache Working Check
        Cache::put('health_check_test', true, 10);
        $cacheStatus = Cache::get('health_check_test') === true ? 'working' : 'failed';

        // 4. Queue Connection Check
        try {
            $queueDriver = config('queue.default');
            $queueStatus = 'active (' . $queueDriver . ')';
        } catch (\Exception $e) {
            $queueStatus = 'failed: ' . $e->getMessage();
        }

        // 5. Scheduler Last Run Check
        $lastRun = Cache::get('scheduler_last_run');
        if ($lastRun) {
            $diffSeconds = now()->timestamp - $lastRun;
            $schedulerStatus = $diffSeconds <= 300 ? 'running' : 'inactive (last run ' . $diffSeconds . 's ago)';
        } else {
            $schedulerStatus = 'inactive (no record)';
        }

        // 6. Mail Config Present Check
        $mailHost = config('mail.mailers.smtp.host') ?: config('mail.default');
        $mailConfigStatus = !empty($mailHost) ? 'configured' : 'missing';

        // 7. Disk Free Space
        $freeSpace = @disk_free_space(storage_path());
        $freeSpaceGB = $freeSpace ? round($freeSpace / (1024 * 1024 * 1024), 2) . ' GB' : 'unknown';

        $overallStatus = ($dbStatus === 'connected' && $storageWritable && $cacheStatus === 'working') ? 'healthy' : 'unhealthy';

        return $this->successResponse([
            'status' => $overallStatus,
            'database' => $dbStatus,
            'storage_writable' => $storageWritable,
            'cache' => $cacheStatus,
            'queue' => $queueStatus,
            'scheduler' => $schedulerStatus,
            'mail_config' => $mailConfigStatus,
            'disk_free_space' => $freeSpaceGB,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    public function securitySummary(Request $request)
    {
        // Require view_audit_logs or manage_roles
        if (!$request->user() || (!$request->user()->hasPermissionTo('view_audit_logs') && !$request->user()->hasPermissionTo('manage_roles'))) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $expirySetting = \App\Models\WebsiteSetting::where('key', 'admin_password_expiry_days')->first();
        $expiryDays = $expirySetting ? $expirySetting->value : null;

        return $this->successResponse([
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'https_enforced' => config('app.env') === 'production',
            'secure_cookies' => config('session.secure', false),
            'same_site' => config('session.same_site', 'lax'),
            'admin_password_expiry_days' => $expiryDays,
            'password_policy' => [
                'min_characters' => 10,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_special' => true
            ]
        ]);
    }

    public function rolePermissionAudit(Request $request)
    {
        // Require manage_roles permission
        if (!$request->user() || !$request->user()->hasPermissionTo('manage_roles')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $roles = Role::with('permissions')->get();
        $auditData = [];

        foreach ($roles as $role) {
            $auditData[] = [
                'role_id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name'),
            ];
        }

        return $this->successResponse($auditData);
    }

    public function storageCheck(Request $request)
    {
        // Require manage_roles or view_audit_logs
        if (!$request->user() || (!$request->user()->hasPermissionTo('view_audit_logs') && !$request->user()->hasPermissionTo('manage_roles'))) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $privatePath = storage_path('app/private');
        $publicCmsPath = storage_path('app/public');

        return $this->successResponse([
            'private_storage' => [
                'exists' => file_exists($privatePath),
                'writable' => is_writable($privatePath),
                'path' => $privatePath,
            ],
            'public_storage' => [
                'exists' => file_exists($publicCmsPath),
                'writable' => is_writable($publicCmsPath),
                'path' => $publicCmsPath,
            ]
        ]);
    }

    public function queueStatus(Request $request)
    {
        // Require view_audit_logs or manage_roles
        if (!$request->user() || (!$request->user()->hasPermissionTo('view_audit_logs') && !$request->user()->hasPermissionTo('manage_roles'))) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $queueDriver = config('queue.default');

        return $this->successResponse([
            'driver' => $queueDriver,
            'failed_jobs_count' => $failedJobs,
            'status' => 'online'
        ]);
    }

    public function schedulerStatus(Request $request)
    {
        // Require view_audit_logs or manage_roles
        if (!$request->user() || (!$request->user()->hasPermissionTo('view_audit_logs') && !$request->user()->hasPermissionTo('manage_roles'))) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $lastRun = Cache::get('scheduler_last_run');
        return $this->successResponse([
            'last_run_timestamp' => $lastRun,
            'last_run_time' => $lastRun ? date('Y-m-d H:i:s', $lastRun) : null,
            'status' => $lastRun && (time() - $lastRun <= 300) ? 'active' : 'inactive'
        ]);
    }
}
