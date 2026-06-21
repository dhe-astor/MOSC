<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'diocese_id',
        'church_id',
        'family_id',
        'member_id',
        'external_name',
        'external_email',
        'external_phone',
        'registration_type',
        'participant_count',
        'payment_status',
        'payment_reference',
        'registration_status',
        'qr_code',
        'checked_in_at',
        'checked_in_by',
        'registered_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function registrar()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendances()
    {
        return $this->hasMany(EventAttendance::class);
    }
}
