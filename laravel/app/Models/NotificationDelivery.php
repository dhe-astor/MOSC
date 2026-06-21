<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id',
        'announcement_id',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'channel',
        'delivery_status',
        'provider',
        'provider_message_id',
        'error_message',
        'attempt_count',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    // Dynamic Helper to resolve recipient model
    public function recipient()
    {
        if ($this->recipient_type === 'user') {
            return $this->belongsTo(User::class, 'recipient_id');
        } elseif ($this->recipient_type === 'member') {
            return $this->belongsTo(Member::class, 'recipient_id');
        } elseif ($this->recipient_type === 'family') {
            return $this->belongsTo(Family::class, 'recipient_id');
        }
        return null;
    }
}
