<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriestTransferRequest extends Model
{
    protected $table = 'priest_transfer_requests';

    protected $fillable = [
        'diocese_id',
        'priest_profile_id',
        'from_church_id',
        'to_church_id',
        'from_assignment_id',
        'new_assignment_role',
        'effective_date',
        'transfer_type',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'completed_by',
        'completed_at',
        'appointment_reference',
        'appointment_document_path',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function priestProfile()
    {
        return $this->belongsTo(PriestProfile::class, 'priest_profile_id');
    }

    public function fromChurch()
    {
        return $this->belongsTo(Church::class, 'from_church_id');
    }

    public function toChurch()
    {
        return $this->belongsTo(Church::class, 'to_church_id');
    }

    public function fromAssignment()
    {
        return $this->belongsTo(PriestChurchAssignment::class, 'from_assignment_id');
    }
}
