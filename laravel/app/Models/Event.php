<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'title',
        'slug',
        'event_type',
        'description',
        'start_datetime',
        'end_datetime',
        'timezone',
        'location_name',
        'address',
        'country_id',
        'mode',
        'meeting_link',
        'registration_required',
        'registration_fee',
        'currency',
        'max_participants',
        'poster_path',
        'banner_path',
        'visibility',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'approved_at' => 'datetime',
        'registration_required' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendances()
    {
        return $this->hasMany(EventAttendance::class);
    }
}
