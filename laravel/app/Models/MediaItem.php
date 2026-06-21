<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaItem extends Model
{
    protected $fillable = [
        'media_gallery_id',
        'title',
        'caption',
        'media_type',
        'media_path',
        'thumbnail_path',
        'external_video_url',
        'sort_order',
        'alt_text',
        'status',
        'created_by',
        'updated_by'
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(MediaGallery::class, 'media_gallery_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function taggedMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'media_item_member', 'media_item_id', 'member_id')
            ->withTimestamps();
    }
}
