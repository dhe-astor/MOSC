<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriestProfile extends Model
{
    use SoftDeletes;

    protected $table = 'priest_profiles';

    protected $fillable = [
        'diocese_id',
        'member_id',
        'user_id',
        'priest_code',
        'ordination_name',
        'display_name',
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
        'created_by',
        'updated_by',
        'public_slug',
        'public_bio',
        'show_public_profile',
        'show_public_phone',
        'show_public_email',
        'public_sort_order',
    ];

    protected $casts = [
        'ordination_date' => 'date',
        'show_public_profile' => 'boolean',
        'show_public_phone' => 'boolean',
        'show_public_email' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(PriestChurchAssignment::class, 'priest_profile_id');
    }

    public function activeAssignments()
    {
        $today = date('Y-m-d');
        return $this->hasMany(PriestChurchAssignment::class, 'priest_profile_id')
            ->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
            });
    }

    public function getFullNameAttribute()
    {
        return $this->display_name;
    }
}
