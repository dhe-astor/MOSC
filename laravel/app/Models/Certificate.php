<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = [
        'certificate_request_id',
        'diocese_id',
        'church_id',
        'member_id',
        'family_id',
        'sacrament_id',
        'certificate_template_id',
        'certificate_number',
        'certificate_type',
        'issued_date',
        'issued_by',
        'approved_by',
        'pdf_path',
        'verification_code',
        'public_verification_enabled',
        'status',
        'metadata',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'public_verification_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function request()
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function sacrament()
    {
        return $this->belongsTo(Sacrament::class);
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
