<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'diocese_id',
        'name',
        'certificate_type',
        'language',
        'html_template',
        'background_image_path',
        'seal_required',
        'signature_required',
        'default_priest_signature_position',
        'default_seal_position',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'seal_required' => 'boolean',
        'signature_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
