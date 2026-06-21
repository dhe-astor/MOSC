<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinistryUnit extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'ministry_organization_id',
        'unit_name',
        'unit_level',
        'president_priest_id',
        'coordinator_member_id',
        'secretary_member_id',
        'treasurer_member_id',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(MinistryOrganization::class, 'ministry_organization_id');
    }

    public function president(): BelongsTo
    {
        return $this->belongsTo(Priest::class, 'president_priest_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'coordinator_member_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'secretary_member_id');
    }

    public function treasurer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'treasurer_member_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MinistryMembership::class);
    }

    public function officeBearers(): HasMany
    {
        return $this->hasMany(MinistryOfficeBearer::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(MinistryActivity::class);
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(MinistryServiceLog::class);
    }
}
