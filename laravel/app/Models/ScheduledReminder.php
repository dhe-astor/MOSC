<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledReminder extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'reminder_type',
        'related_type',
        'related_id',
        'title',
        'body',
        'scheduled_at',
        'channel',
        'status',
        'created_by',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
