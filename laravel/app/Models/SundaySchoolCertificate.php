<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SundaySchoolCertificate extends Model
{
    protected $table = 'sunday_school_certificates';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'certificate_id',
        'certificate_type',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
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

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'certificate_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
