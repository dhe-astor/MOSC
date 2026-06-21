<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'requested_by',
        'family_id',
        'member_id',
        'sacrament_id',
        'certificate_type',
        'purpose',
        'request_data',
        'supporting_document_path',
        'status',
        'parish_reviewed_by',
        'parish_reviewed_at',
        'priest_approved_by',
        'priest_approved_at',
        'diocese_approved_by',
        'diocese_approved_at',
        'rejection_reason',
        'certificate_id',
        'created_by',
    ];

    protected $casts = [
        'request_data' => 'array',
        'parish_reviewed_at' => 'datetime',
        'priest_approved_at' => 'datetime',
        'diocese_approved_at' => 'datetime',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function sacrament()
    {
        return $this->belongsTo(Sacrament::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function parishReviewer()
    {
        return $this->belongsTo(User::class, 'parish_reviewed_by');
    }

    public function priestApprover()
    {
        return $this->belongsTo(User::class, 'priest_approved_by');
    }

    public function dioceseApprover()
    {
        return $this->belongsTo(User::class, 'diocese_approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
