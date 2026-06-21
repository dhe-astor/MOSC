<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SundaySchoolAttendance extends Model
{
    protected $table = 'sunday_school_attendance';

    protected $fillable = [
        'class_id',
        'student_id',
        'attendance_date',
        'status',
        'marked_by',
        'marked_at',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'marked_at' => 'datetime',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolStudent::class, 'student_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
