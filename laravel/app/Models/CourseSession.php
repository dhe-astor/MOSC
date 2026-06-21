<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSession extends Model
{
    protected $fillable = [
        'course_batch_id',
        'title',
        'description',
        'session_date',
        'start_time',
        'end_time',
        'timezone',
        'speaker_name',
        'speaker_profile',
        'meeting_link',
        'session_order',
        'attendance_required',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'attendance_required' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attendances()
    {
        return $this->hasMany(CourseAttendance::class);
    }
}
