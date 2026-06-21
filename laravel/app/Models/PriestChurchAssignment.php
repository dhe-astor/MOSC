<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriestChurchAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'priest_church_assignments';

    protected $fillable = [
        'diocese_id',
        'priest_profile_id',
        'member_id',
        'user_id',
        'church_id',
        'assignment_role',
        'assignment_title',
        'start_date',
        'end_date',
        'status',
        'is_primary',
        'appointed_by',
        'appointment_reference',
        'appointment_document_path',
        'notes',
        'created_by',
        'updated_by',
        'ended_by',
        'ended_at',
        'end_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_primary' => 'boolean',
        'ended_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function priestProfile()
    {
        return $this->belongsTo(PriestProfile::class, 'priest_profile_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
