<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinistryServiceLog extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'ministry_unit_id',
        'member_id',
        'activity_id',
        'service_type',
        'service_date',
        'hours_count',
        'description',
        'verified_by',
        'verified_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'service_date' => 'date',
        'verified_at' => 'datetime',
        'hours_count' => 'decimal:2',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(MinistryActivity::class, 'activity_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
