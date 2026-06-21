<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MediaGallery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diocese_id',
        'church_id',
        'event_id',
        'ministry_unit_id',
        'title',
        'slug',
        'gallery_type',
        'description',
        'cover_image_path',
        'visibility',
        'status',
        'published_at',
        'published_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ministryUnit(): BelongsTo
    {
        return $this->belongsTo(MinistryUnit::class, 'ministry_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'media_gallery_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(ContentApproval::class, 'approvable');
    }
}
