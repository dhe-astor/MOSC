<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberChangeRequest extends Model
{
    protected $fillable = [
        'member_id',
        'family_id',
        'church_id',
        'requested_by',
        'change_type',
        'old_data',
        'new_data',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User, 'reviewed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
