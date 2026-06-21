<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberResponsibilityAssignment extends Model
{
    protected $table = 'member_responsibility_assignments';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'member_id',
        'user_id',
        'responsibility_type',
        'designation',
        'organization_type',
        'organization_id',
        'programme_account_id',
        'start_date',
        'end_date',
        'status',
        'is_primary',
        'assigned_by',
        'assignment_reference',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function programmeAccount()
    {
        return $this->belongsTo(FinanceProgrammeAccount::class, 'programme_account_id');
    }
}
