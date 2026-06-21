<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteImportSource extends Model
{
    protected $table = 'website_import_sources';

    protected $fillable = [
        'diocese_id',
        'source_type',
        'source_url',
        'status',
        'last_synced_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function runs()
    {
        return $this->hasMany(WebsiteImportRun::class, 'source_id');
    }
}
