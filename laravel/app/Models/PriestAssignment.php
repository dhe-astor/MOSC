<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriestAssignment extends Model
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
        'notes',
        
        // Legacy fields for seeder/tests
        'priest_id',
        'role',
        'assignment_start_date',
        'assignment_end_date',
        'remarks',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public function newEloquentBuilder($query)
    {
        return (new \App\Models\Builders\CompatibilityBuilder($query))->setMappings([
            'priest_id' => 'priest_profile_id',
            'role' => 'assignment_role',
            'assignment_start_date' => 'start_date',
            'assignment_end_date' => 'end_date',
            'remarks' => 'notes',
        ]);
    }

    protected static function booted()
    {
        static::saving(function ($assignment) {
            // Map legacy attributes to new attributes on save
            if (isset($assignment->attributes['priest_id']) && !isset($assignment->attributes['priest_profile_id'])) {
                $assignment->attributes['priest_profile_id'] = $assignment->attributes['priest_id'];
            }
            if (isset($assignment->attributes['role']) && !isset($assignment->attributes['assignment_role'])) {
                $assignment->attributes['assignment_role'] = $assignment->attributes['role'];
            }
            if (isset($assignment->attributes['assignment_start_date']) && !isset($assignment->attributes['start_date'])) {
                $assignment->attributes['start_date'] = $assignment->attributes['assignment_start_date'];
            }
            if (isset($assignment->attributes['assignment_end_date']) && !isset($assignment->attributes['end_date'])) {
                $assignment->attributes['end_date'] = $assignment->attributes['assignment_end_date'];
            }
            if (isset($assignment->attributes['remarks']) && !isset($assignment->attributes['notes'])) {
                $assignment->attributes['notes'] = $assignment->attributes['remarks'];
            }

            if (!$assignment->member_id && $assignment->priest_profile_id) {
                $assignment->member_id = PriestProfile::find($assignment->priest_profile_id)?->member_id ?? 1;
            }
            if (!$assignment->diocese_id) {
                $assignment->diocese_id = 1;
            }
        });
    }

    // Accessors and Mutators for legacy compatibility
    public function getPriestIdAttribute()
    {
        return $this->priest_profile_id;
    }

    public function setPriestIdAttribute($value)
    {
        $this->attributes['priest_profile_id'] = $value;
    }

    public function getRoleAttribute()
    {
        return $this->assignment_role;
    }

    public function setRoleAttribute($value)
    {
        $this->attributes['assignment_role'] = $value;
    }

    public function getAssignmentStartDateAttribute()
    {
        return $this->start_date;
    }

    public function setAssignmentStartDateAttribute($value)
    {
        $this->attributes['start_date'] = $value;
    }

    public function getAssignmentEndDateAttribute()
    {
        return $this->end_date;
    }

    public function setAssignmentEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $value;
    }

    public function getRemarksAttribute()
    {
        return $this->notes;
    }

    public function setRemarksAttribute($value)
    {
        $this->attributes['notes'] = $value;
    }

    public function priest()
    {
        return $this->belongsTo(Priest::class, 'priest_profile_id');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
