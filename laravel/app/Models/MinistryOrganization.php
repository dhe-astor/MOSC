<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinistryOrganization extends Model
{
    protected $fillable = [
        'diocese_id',
        'name',
        'slug',
        'organization_type',
        'description',
        'eligibility_rules',
        'status',
        'show_on_portal',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'eligibility_rules' => 'array',
        'show_on_portal' => 'boolean',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(MinistryUnit::class);
    }
}
