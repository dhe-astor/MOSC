<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserChurchAccess extends Model
{
    use SoftDeletes;

    protected $table = 'user_church_access';

    protected $fillable = [
        'user_id',
        'diocese_id',
        'church_id',
        'access_scope',
        'ministry_id',
        'starts_at',
        'ends_at',
        'status',
        'created_by'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
