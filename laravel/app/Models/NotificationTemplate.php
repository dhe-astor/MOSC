<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'diocese_id',
        'template_key',
        'name',
        'channel',
        'subject',
        'body',
        'variables',
        'status',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_system' => 'boolean',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
