<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'notifiable_type',
        'notifiable_id',
        'title',
        'body',
        'notification_type',
        'channel',
        'priority',
        'status',
        'read_at',
        'action_url',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
