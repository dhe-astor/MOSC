<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    protected $fillable = [
        'course_batch_id',
        'diocese_id',
        'church_id',
        'family_id',
        'member_id',
        'external_name',
        'external_email',
        'external_phone',
        'registration_type',
        'participant_count',
        'payment_status',
        'payment_reference',
        'registration_status',
        'qr_code',
        'feedback_completed',
        'certificate_issued',
        'certificate_id',
        'registered_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'feedback_completed' => 'boolean',
        'certificate_issued' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id');
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function registrar()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendances()
    {
        return $this->hasMany(CourseAttendance::class);
    }

    public function feedback()
    {
        return $this->hasOne(CourseFeedback::class);
    }
}
