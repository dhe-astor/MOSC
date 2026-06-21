<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyTransferRequest extends Model
{
    protected $fillable = [
        'family_id',
        'from_church_id',
        'to_church_id',
        'requested_by',
        'source_approved_by',
        'source_approved_at',
        'diocese_approved_by',
        'diocese_approved_at',
        'target_accepted_by',
        'target_accepted_at',
        'status',
        'reason',
        'remarks',
    ];

    protected $casts = [
        'source_approved_at' => 'datetime',
        'diocese_approved_at' => 'datetime',
        'target_accepted_at' => 'datetime',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function fromChurch()
    {
        return $this->belongsTo(Church::class, 'from_church_id');
    }

    public function toChurch()
    {
        return $this->belongsTo(Church::class, 'to_church_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sourceApprover()
    {
        return $this->belongsTo(User::class, 'source_approved_by');
    }

    public function dioceseApprover()
    {
        return $this->belongsTo(User::class, 'diocese_approved_by');
    }

    public function targetAccepter()
    {
        return $this->belongsTo(User::class, 'target_accepted_by');
    }
}
