<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'phone_code',
        'currency',
        'timezone',
        'status'
    ];

    public function churches()
    {
        return $this->hasMany(Church::class);
    }
}
