<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinistryActivity extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'ministry_unit_id',
        'title',
        'activity_type',
        'description',
        'start_datetime',
        'end_datetime',
        'timezone',
        'location_name',
        'mode',
        'meeting_link',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MinistryUnit::class, 'ministry_unit_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(MinistryActivityAttendance::class, 'ministry_activity_id');
    }
}
