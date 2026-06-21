<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'access_token',
        'secret',
        'api_token'
    ];

    public static function log(
        string $module,
        string $action,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Model $auditable = null,
        ?int $churchId = null,
        ?int $dioceseId = null
    ): AuditLog {
        $user = Auth::user();
        
        $maskedOld = $oldValues ? self::maskSensitive($oldValues) : null;
        $maskedNew = $newValues ? self::maskSensitive($newValues) : null;

        return AuditLog::create([
            'user_id' => $user?->id,
            'diocese_id' => $dioceseId ?? $user?->default_diocese_id,
            'church_id' => $churchId ?? $user?->active_church_id ?? $user?->default_church_id,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->id,
            'module' => $module,
            'action' => $action,
            'event' => $event,
            'old_values' => $maskedOld,
            'new_values' => $maskedNew,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'request_id' => Request::header('X-Request-ID') ?? uniqid('req_'),
        ]);
    }

    protected static function maskSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::$sensitiveKeys)) {
                $data[$key] = '[MASKED]';
            } elseif (is_array($value)) {
                $data[$key] = self::maskSensitive($value);
            }
        }
        return $data;
    }
}
