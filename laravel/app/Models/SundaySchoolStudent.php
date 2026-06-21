<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SundaySchoolStudent extends Model
{
    protected $table = 'sunday_school_students';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'academic_year_id',
        'class_id',
        'member_id',
        'family_id',
        'parent_member_id',
        'enrollment_date',
        'enrollment_status',
        'remarks',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolAcademicYear::class, 'academic_year_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SundaySchoolClass::class, 'class_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function parentMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SundaySchoolAttendance::class, 'student_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(SundaySchoolMark::class, 'student_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(SundaySchoolProgressReport::class, 'student_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(SundaySchoolCertificate::class, 'student_id');
    }
}
