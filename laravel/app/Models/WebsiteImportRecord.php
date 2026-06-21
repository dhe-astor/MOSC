<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteImportRecord extends Model
{
    protected $table = 'website_import_records';

    protected $fillable = [
        'import_run_id',
        'record_type',
        'external_key',
        'raw_name',
        'normalized_name',
        'raw_payload',
        'matched_member_id',
        'matched_priest_profile_id',
        'matched_church_id',
        'match_status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(WebsiteImportRun::class, 'import_run_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'matched_member_id');
    }

    public function priestProfile()
    {
        return $this->belongsTo(PriestProfile::class, 'matched_priest_profile_id');
    }

    public function church()
    {
        return $this->belongsTo(Church::class, 'matched_church_id');
    }
}
