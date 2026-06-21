<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SundaySchoolClassTeacherAssignment extends Model
{
    protected $table = 'sunday_school_class_teacher_assignments';

    protected $fillable = [
        'class_id',
        'teacher_id',
        'role',
        'assigned_from',
        'assigned_to',
        'status',
        'created_by',
    ];

    protected $casts = [
        'assigned_from' => 'date',
        'assigned_to' => 'date',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolTeacher::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
