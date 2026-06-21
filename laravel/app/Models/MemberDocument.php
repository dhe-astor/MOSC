<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class MemberDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'member_id',
        'family_id',
        'church_id',
        'document_type',
        'title',
        'file_path',
        'visibility',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
