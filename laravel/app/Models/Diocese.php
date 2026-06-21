<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diocese extends Model
{
    protected $fillable = [
        'name',
        'canonical_name',
        'short_name',
        'description',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'logo_path',
        'seal_path',
        'status'
    ];

    public function churches()
    {
        return $this->hasMany(Church::class);
    }

    public function priests()
    {
        return $this->hasMany(Priest::class);
    }
}
