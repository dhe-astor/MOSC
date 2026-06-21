<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendance extends Model
{
    protected $table = 'event_attendance';

    protected $fillable = [
        'event_id',
        'event_registration_id',
        'member_id',
        'attendance_date',
        'status',
        'marked_by',
        'marked_at',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'marked_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function registration()
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
