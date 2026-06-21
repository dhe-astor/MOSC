<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinistryActivityAttendance extends Model
{
    protected $table = 'ministry_activity_attendance';

    protected $fillable = [
        'ministry_activity_id',
        'ministry_membership_id',
        'member_id',
        'status',
        'marked_by',
        'marked_at',
        'remarks',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(MinistryActivity::class, 'ministry_activity_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MinistryMembership::class, 'ministry_membership_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function markedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
