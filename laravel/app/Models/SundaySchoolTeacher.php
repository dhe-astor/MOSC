<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SundaySchoolTeacher extends Model
{
    protected $table = 'sunday_school_teachers';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'member_id',
        'user_id',
        'full_name',
        'phone',
        'email',
        'qualification',
        'experience_notes',
        'status',
        'created_by',
        'updated_by',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SundaySchoolClassTeacherAssignment::class, 'teacher_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SundaySchoolClass::class, 'sunday_school_class_teacher_assignments', 'teacher_id', 'class_id')
            ->withPivot(['role', 'assigned_from', 'assigned_to', 'status'])
            ->withTimestamps();
    }
}
