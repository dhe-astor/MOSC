<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchServiceTiming extends Model
{
    protected $table = 'church_service_timings';

    protected $fillable = [
        'church_id',
        'service_name',
        'day_of_week',
        'service_date',
        'start_time',
        'end_time',
        'language',
        'frequency',
        'notes',
        'is_public',
        'status'
    ];

    protected $casts = [
        'service_date' => 'date',
        'is_public' => 'boolean'
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }
}
