<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sacrament extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'member_id',
        'family_id',
        'sacrament_type',
        'sacrament_date',
        'place',
        'officiated_by_priest_id',
        'certificate_number',
        'register_book_number',
        'register_page_number',
        'witness_1_name',
        'witness_2_name',
        'spouse_member_id',
        'spouse_name',
        'remarks',
        'document_path',
        'status',
        'created_by',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'sacrament_date' => 'date',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

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

    public function officiant()
    {
        return $this->belongsTo(Priest::class, 'officiated_by_priest_id');
    }

    public function spouse()
    {
        return $this->belongsTo(Member::class, 'spouse_member_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
