<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Priest extends Model
{
    use SoftDeletes;

    protected $table = 'priest_profiles';

    protected $fillable = [
        'diocese_id',
        'member_id',
        'user_id',
        'display_name',
        'ordination_name',
        'canonical_title',
        'clergy_type',
        'ordination_date',
        'ordination_place',
        'home_diocese',
        'phone_public',
        'email_public',
        'photo_path',
        'bio',
        'status',
        
        // Legacy fields for seeder/tests
        'full_name',
        'baptism_name',
        'clergy_rank',
        'date_of_birth',
        'primary_phone',
        'whatsapp_phone',
        'email',
        'address',
        'city',
        'country',
        'biography',
        'show_on_website',
        'sort_order'
    ];

    protected $casts = [
        'ordination_date' => 'date',
    ];

    public function newEloquentBuilder($query)
    {
        return (new \App\Models\Builders\CompatibilityBuilder($query))->setMappings([
            'email' => 'email_public',
            'full_name' => 'display_name',
            'biography' => 'bio',
            'clergy_rank' => 'clergy_type',
            'primary_phone' => 'phone_public',
        ]);
    }

    protected static function booted()
    {
        static::saving(function ($priest) {
            // Map legacy attributes to new attributes on save
            if (isset($priest->attributes['full_name']) && !isset($priest->attributes['display_name'])) {
                $priest->attributes['display_name'] = $priest->attributes['full_name'];
            }
            if (isset($priest->attributes['email']) && !isset($priest->attributes['email_public'])) {
                $priest->attributes['email_public'] = $priest->attributes['email'];
            }
            if (isset($priest->attributes['primary_phone']) && !isset($priest->attributes['phone_public'])) {
                $priest->attributes['phone_public'] = $priest->attributes['primary_phone'];
            }
            if (isset($priest->attributes['biography']) && !isset($priest->attributes['bio'])) {
                $priest->attributes['bio'] = $priest->attributes['biography'];
            }
            if (isset($priest->attributes['clergy_rank']) && !isset($priest->attributes['clergy_type'])) {
                $priest->attributes['clergy_type'] = $priest->attributes['clergy_rank'];
            }

            if (!$priest->member_id) {
                // Find or create a member record to satisfy foreign key constraints
                $createdBy = auth()->id() ?? \App\Models\User::where('email', 'admin@msoc-europe.org')->first()?->id ?? \App\Models\User::first()?->id ?? 1;
                $member = \App\Models\Member::create([
                    'diocese_id' => $priest->diocese_id ?? 1,
                    'church_id' => 1,
                    'first_name' => $priest->display_name ?? 'Legacy',
                    'last_name' => 'Priest',
                    'full_name' => $priest->display_name ?? 'Legacy Priest',
                    'gender' => 'male',
                    'relationship_to_head' => 'other',
                    'membership_status' => 'active',
                    'created_by' => $createdBy
                ]);
                $priest->member_id = $member->id;
            }
        });
    }

    // Accessors and Mutators for legacy compatibility
    public function getFullNameAttribute()
    {
        return $this->display_name;
    }

    public function setFullNameAttribute($value)
    {
        $this->attributes['display_name'] = $value;
    }

    public function getEmailAttribute()
    {
        return $this->email_public;
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email_public'] = $value;
    }

    public function getPrimaryPhoneAttribute()
    {
        return $this->phone_public;
    }

    public function setPrimaryPhoneAttribute($value)
    {
        $this->attributes['phone_public'] = $value;
    }

    public function getBiographyAttribute()
    {
        return $this->bio;
    }

    public function setBiographyAttribute($value)
    {
        $this->attributes['bio'] = $value;
    }

    public function getClergyRankAttribute()
    {
        return $this->clergy_type;
    }

    public function setClergyRankAttribute($value)
    {
        $this->attributes['clergy_type'] = $value;
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(PriestAssignment::class, 'priest_profile_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(PriestAssignment::class, 'priest_profile_id')->where('status', 'active');
    }
}
