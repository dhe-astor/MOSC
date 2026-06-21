<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseBatch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'diocese_id',
        'church_id',
        'batch_name',
        'batch_code',
        'start_datetime',
        'end_datetime',
        'timezone',
        'mode',
        'venue',
        'meeting_link',
        'registration_open_at',
        'registration_close_at',
        'max_participants',
        'fee_amount',
        'currency',
        'certificate_enabled',
        'certificate_template_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'registration_open_at' => 'datetime',
        'registration_close_at' => 'datetime',
        'certificate_enabled' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function certificateTemplate()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function sessions()
    {
        return $this->hasMany(CourseSession::class);
    }

    public function registrations()
    {
        return $this->hasMany(CourseRegistration::class);
    }
}
