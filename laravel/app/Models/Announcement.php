<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'diocese_id',
        'church_id',
        'title',
        'body',
        'announcement_type',
        'priority',
        'visibility',
        'status',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'created_by',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function targets()
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
