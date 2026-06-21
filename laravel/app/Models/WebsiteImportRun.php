<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteImportRun extends Model
{
    protected $table = 'website_import_runs';

    protected $fillable = [
        'diocese_id',
        'source_id',
        'run_type',
        'status',
        'records_found',
        'records_created',
        'records_updated',
        'records_skipped',
        'error_message',
        'started_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function source()
    {
        return $this->belongsTo(WebsiteImportSource::class, 'source_id');
    }

    public function records()
    {
        return $this->hasMany(WebsiteImportRecord::class, 'import_run_id');
    }
}
