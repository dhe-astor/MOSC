<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SundaySchoolClass extends Model
{
    use SoftDeletes;

    protected $table = 'sunday_school_classes';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'academic_year_id',
        'level_id',
        'class_name',
        'mode',
        'meeting_link',
        'recording_folder_link',
        'class_day',
        'start_time',
        'end_time',
        'timezone',
        'primary_teacher_id',
        'assistant_teacher_id',
        'max_students',
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolAcademicYear::class, 'academic_year_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolLevel::class, 'level_id');
    }

    public function primaryTeacher(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolTeacher::class, 'primary_teacher_id');
    }

    public function assistantTeacher(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolTeacher::class, 'assistant_teacher_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(SundaySchoolClassTeacherAssignment::class, 'class_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(SundaySchoolTeacher::class, 'sunday_school_class_teacher_assignments', 'class_id', 'teacher_id')
            ->withPivot(['role', 'assigned_from', 'assigned_to', 'status'])
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(SundaySchoolStudent::class, 'class_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SundaySchoolAttendance::class, 'class_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(SundaySchoolExam::class, 'class_id');
    }
}
