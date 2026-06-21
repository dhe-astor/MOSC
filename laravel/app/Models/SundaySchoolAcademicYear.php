<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SundaySchoolAcademicYear extends Model
{
    protected $table = 'sunday_school_academic_years';

    protected $fillable = [
        'diocese_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SundaySchoolClass::class, 'academic_year_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SundaySchoolStudent::class, 'academic_year_id');
    }
}
