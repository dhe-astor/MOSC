<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSequence extends Model
{
    protected $fillable = [
        'diocese_id',
        'certificate_type',
        'year',
        'last_number',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }
}
