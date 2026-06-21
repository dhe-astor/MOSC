<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends Model
{
    protected $fillable = [
        'diocese_id',
        'report_key',
        'name',
        'description',
        'report_category',
        'default_filters',
        'allowed_roles',
        'required_permissions',
        'status',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_filters' => 'array',
        'allowed_roles' => 'array',
        'required_permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function savedReports(): HasMany
    {
        return $this->hasMany(SavedReport::class);
    }

    public function reportRuns(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }

    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }
}
