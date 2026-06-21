<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileCorrectionRequest extends Model
{
    protected $table = 'profile_correction_requests';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'family_id',
        'member_id',
        'requested_by',
        'request_type',
        'current_data',
        'requested_data',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'applied_at'
    ];

    protected $casts = [
        'current_data' => 'array',
        'requested_data' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime'
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
