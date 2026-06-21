<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSetting extends Model
{
    protected $fillable = [
        'diocese_id',
        'key',
        'value',
        'group',
        'updated_by'
    ];

    protected $casts = [
        'value' => 'array'
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
