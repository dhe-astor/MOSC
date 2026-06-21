<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Family extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'family_code',
        'family_name',
        'head_member_id',
        'primary_phone',
        'whatsapp_phone',
        'primary_email',
        'address_line_1',
        'address_line_2',
        'city',
        'state_region',
        'postal_code',
        'country_id',
        'preferred_language',
        'membership_status',
        'registered_date',
        'approved_by',
        'approved_at',
        'gdpr_consent',
        'gdpr_consent_at',
        'communication_consent',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'registered_date' => 'date',
        'approved_at' => 'datetime',
        'gdpr_consent' => 'boolean',
        'gdpr_consent_at' => 'datetime',
        'communication_consent' => 'boolean',
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

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function headMember()
    {
        return $this->belongsTo(Member::class, 'head_member_id');
    }

    public function history()
    {
        return $this->hasMany(FamilyChurchHistory::class);
    }

    public function activeHistory()
    {
        return $this->hasOne(FamilyChurchHistory::class)->where('status', 'active');
    }

    public function documents()
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function transferRequests()
    {
        return $this->hasMany(FamilyTransferRequest::class);
    }

    public function changeRequests()
    {
        return $this->hasMany(MemberChangeRequest::class);
    }
}
