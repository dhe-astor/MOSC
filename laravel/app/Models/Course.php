<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'name',
        'slug',
        'course_type',
        'description',
        'eligibility',
        'default_fee_amount',
        'currency',
        'certificate_enabled',
        'certificate_template_id',
        'feedback_required',
        'attendance_required_percentage',
        'status',
        'show_on_portal',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'certificate_enabled' => 'boolean',
        'feedback_required' => 'boolean',
        'show_on_portal' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
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

    public function batches()
    {
        return $this->hasMany(CourseBatch::class);
    }
}
