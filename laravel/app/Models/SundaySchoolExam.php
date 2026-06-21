<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SundaySchoolExam extends Model
{
    protected $table = 'sunday_school_exams';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'academic_year_id',
        'class_id',
        'level_id',
        'exam_name',
        'exam_type',
        'exam_date',
        'max_marks',
        'pass_marks',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
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

    public function class(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolClass::class, 'class_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolLevel::class, 'level_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(SundaySchoolMark::class, 'exam_id');
    }
}
