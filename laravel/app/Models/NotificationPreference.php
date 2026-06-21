<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'diocese_id',
        'user_id',
        'member_id',
        'family_id',
        'channel',
        'notification_type',
        'is_enabled',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
