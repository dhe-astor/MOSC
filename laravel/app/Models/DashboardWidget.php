<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'widget_key',
        'title',
        'widget_type',
        'data_source',
        'filters',
        'allowed_roles',
        'required_permissions',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'allowed_roles' => 'array',
        'required_permissions' => 'array',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
