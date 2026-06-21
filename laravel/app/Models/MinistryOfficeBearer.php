<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinistryOfficeBearer extends Model
{
    protected $fillable = [
        'ministry_unit_id',
        'member_id',
        'priest_id',
        'external_name',
        'role_title',
        'role_category',
        'start_date',
        'end_date',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MinistryUnit::class, 'ministry_unit_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function priest(): BelongsTo
    {
        return $this->belongsTo(Priest::class);
    }
}
