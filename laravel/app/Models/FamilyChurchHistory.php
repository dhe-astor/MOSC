<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyChurchHistory extends Model
{
    protected $table = 'family_church_history';

    protected $fillable = [
        'family_id',
        'church_id',
        'start_date',
        'end_date',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
