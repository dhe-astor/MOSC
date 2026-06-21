<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementTarget extends Model
{
    protected $fillable = [
        'announcement_id',
        'target_type',
        'target_id',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
