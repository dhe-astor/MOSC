<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SundaySchoolLevel extends Model
{
    protected $table = 'sunday_school_levels';

    protected $fillable = [
        'diocese_id',
        'level_name',
        'level_code',
        'sort_order',
        'minimum_age',
        'maximum_age',
        'description',
        'status',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SundaySchoolClass::class, 'level_id');
    }
}
