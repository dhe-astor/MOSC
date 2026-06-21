<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPortalAccess extends Model
{
    protected $table = 'member_portal_access';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'family_id',
        'member_id',
        'user_id',
        'access_type',
        'status',
        'invited_by',
        'invited_at',
        'activated_at',
        'suspended_by',
        'suspended_at',
        'suspension_reason',
        'revoked_by',
        'revoked_at'
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'revoked_at' => 'datetime'
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
