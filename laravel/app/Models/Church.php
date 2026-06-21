<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Church extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'country_id',
        'name',
        'short_name',
        'slug',
        'church_type',
        'patron_saint',
        'city',
        'state_region',
        'country',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'latitude',
        'longitude',
        'public_email',
        'public_phone',
        'website_url',
        'facebook_url',
        'instagram_url',
        'google_map_url',
        'established_date',
        'canonical_status',
        'membership_code_prefix',
        'public_page_slug',
        'description',
        'history',
        'qurbana_timing',
        'sort_order',
        'show_on_website',
        'source_url',
        'source_raw_name',
        'source_verified_at',
        'source_notes',
        'created_by',
        'updated_by',
        'public_slug',
        'public_description',
        'public_photo_path',
        'show_public_page',
        'show_service_times',
        'show_map',
        'public_sort_order',
        'seo_title',
        'seo_description'
    ];

    protected $casts = [
        'established_date' => 'date',
        'show_on_website' => 'boolean',
        'source_verified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'show_public_page' => 'boolean',
        'show_service_times' => 'boolean',
        'show_map' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function countryRelation()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function assignments()
    {
        return $this->hasMany(PriestAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->hasMany(PriestAssignment::class)->where('status', 'active');
    }

    public function primaryVicar()
    {
        return $this->hasOne(PriestAssignment::class)
            ->where('status', 'active')
            ->where('is_primary', true);
    }

    public function serviceTimings()
    {
        return $this->hasMany(ChurchServiceTiming::class, 'church_id');
    }

    public function priestChurchAssignments()
    {
        return $this->hasMany(PriestChurchAssignment::class, 'church_id');
    }
}
