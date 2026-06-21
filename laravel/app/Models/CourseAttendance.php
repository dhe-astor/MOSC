<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAttendance extends Model
{
    protected $table = 'course_attendance';

    protected $fillable = [
        'course_batch_id',
        'course_session_id',
        'course_registration_id',
        'member_id',
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

    public function batch()
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id');
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function registration()
    {
        return $this->belongsTo(CourseRegistration::class, 'course_registration_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
