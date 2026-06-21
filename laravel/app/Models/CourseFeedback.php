<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseFeedback extends Model
{
    protected $table = 'course_feedback';

    protected $fillable = [
        'course_batch_id',
        'course_registration_id',
        'member_id',
        'rating',
        'feedback_text',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id');
    }

    public function registration()
    {
        return $this->belongsTo(CourseRegistration::class, 'course_registration_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
