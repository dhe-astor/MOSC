<?php

namespace App\Services;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class FinanceAuditService
{
    /**
     * Log a financial transaction event.
     */
    public static function logEvent(string $action, string $details, ?Model $model = null, ?int $churchId = null, ?int $dioceseId = null): void
    {
        AuditLogService::log(
            'Finance',
            $action,
            $details,
            null,
            $model ? $model->toArray() : null,
            $model,
            $churchId,
            $dioceseId ?? 1
        );
    }
}
