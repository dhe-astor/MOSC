<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'family_id',
        'user_id',
        'member_code',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'baptism_name',
        'gender',
        'date_of_birth',
        'relationship_to_head',
        'phone',
        'whatsapp_phone',
        'email',
        'occupation',
        'employer_or_school',
        'student_status',
        'marital_status',
        'address_same_as_family',
        'individual_address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_photo_path',
        'membership_status',
        'gdpr_consent',
        'communication_consent',
        'show_in_directory',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'photo_publication_consent',
        'photo_publication_consent_at',
        'photo_publication_consent_source',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'student_status' => 'boolean',
        'address_same_as_family' => 'boolean',
        'individual_address' => 'array',
        'gdpr_consent' => 'boolean',
        'communication_consent' => 'boolean',
        'show_in_directory' => 'boolean',
        'approved_at' => 'datetime',
        'photo_publication_consent' => 'boolean',
        'photo_publication_consent_at' => 'datetime',
    ];

    protected $appends = ['age'];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function changeRequests()
    {
        return $this->hasMany(MemberChangeRequest::class);
    }

    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }
        return Carbon::parse($this->date_of_birth)->age;
    }
}
