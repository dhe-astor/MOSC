<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SundaySchoolProgressReport extends Model
{
    protected $table = 'sunday_school_progress_reports';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'attendance_percentage',
        'total_marks',
        'grade',
        'promotion_status',
        'teacher_remarks',
        'generated_by',
        'generated_at',
        'pdf_path',
    ];

    protected $casts = [
        'attendance_percentage' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolStudent::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolAcademicYear::class, 'academic_year_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolClass::class, 'class_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
