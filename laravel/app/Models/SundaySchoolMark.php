<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SundaySchoolMark extends Model
{
    protected $table = 'sunday_school_marks';

    protected $fillable = [
        'exam_id',
        'student_id',
        'marks_obtained',
        'grade',
        'result_status',
        'remarks',
        'entered_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolExam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolStudent::class, 'student_id');
    }

    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
