<?php

namespace App\Services;

use App\Models\MediaGallery;
use App\Models\MediaItem;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class MediaGalleryService
{
    public static function create(array $data, User $user): MediaGallery
    {
        return DB::transaction(function () use ($data, $user) {
            $data['slug'] = self::generateSlug($data['title']);
            $data['created_by'] = $user->id;
            $data['diocese_id'] = $user->default_diocese_id ?? 1;

            $gallery = MediaGallery::create($data);

            AuditLogService::log(
                'CMS',
                'Create Gallery',
                "Created photo/video gallery: {$gallery->title}",
                null,
                $gallery->toArray(),
                $gallery,
                $gallery->church_id,
                $gallery->diocese_id
            );

            return $gallery;
        });
    }

    public static function update(MediaGallery $gallery, array $data, User $user): MediaGallery
    {
        return DB::transaction(function () use ($gallery, $data, $user) {
            $oldValues = $gallery->toArray();

            if (isset($data['title']) && $data['title'] !== $gallery->title) {
                $data['slug'] = self::generateSlug($data['title'], $gallery->id);
            } elseif (isset($data['slug']) && $data['slug'] !== $gallery->slug) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $data['updated_by'] = $user->id;
            $gallery->update($data);

            AuditLogService::log(
                'CMS',
                'Update Gallery',
                "Updated gallery details: {$gallery->title}",
                $oldValues,
                $gallery->toArray(),
                $gallery,
                $gallery->church_id,
                $gallery->diocese_id
            );

            return $gallery;
        });
    }

    public static function addImageItem(MediaGallery $gallery, UploadedFile $file, array $itemData, User $user): MediaItem
    {
        return DB::transaction(function () use ($gallery, $file, $itemData, $user) {
            $filename = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());

            // Draft galleries store in private, published moves to public
            if ($gallery->status === 'published') {
                // Store in public disk directly
                $path = $file->storeAs('galleries/published', $filename, 'public');
                $thumbPath = $file->storeAs('galleries/thumbnails', $filename, 'public');
                $mediaPath = 'storage/' . $path;
                $thumbnailPath = 'storage/' . $thumbPath;
            } else {
                // Private storage path
                $path = $file->storeAs('private/cms/galleries/drafts', $filename);
                $mediaPath = $path;
                $thumbnailPath = null;
            }

            $item = MediaItem::create([
                'media_gallery_id' => $gallery->id,
                'title' => $itemData['title'] ?? null,
                'caption' => $itemData['caption'] ?? null,
                'media_type' => 'image',
                'media_path' => $mediaPath,
                'thumbnail_path' => $thumbnailPath,
                'alt_text' => $itemData['alt_text'] ?? $itemData['title'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? 0,
                'status' => 'active',
                'created_by' => $user->id
            ]);

            // Handle tagging members
            if (isset($itemData['tagged_member_ids']) && is_array($itemData['tagged_member_ids'])) {
                $item->taggedMembers()->sync($itemData['tagged_member_ids']);
            }

            AuditLogService::log(
                'CMS',
                'Add Gallery Image',
                "Added image item to gallery ID {$gallery->id}: {$item->title}",
                null,
                $item->toArray(),
                $gallery,
                $gallery->church_id,
                $gallery->diocese_id
            );

            return $item;
        });
    }

    public static function addVideoItem(MediaGallery $gallery, string $videoUrl, array $itemData, User $user): ?MediaItem
    {
        $parsed = self::parseVideoUrl($videoUrl);
        if (!$parsed) {
            return null;
        }

        return DB::transaction(function () use ($gallery, $parsed, $itemData, $user) {
            $item = MediaItem::create([
                'media_gallery_id' => $gallery->id,
                'title' => $itemData['title'] ?? null,
                'caption' => $itemData['caption'] ?? null,
                'media_type' => 'video',
                'external_video_url' => $parsed['embed_url'],
                'alt_text' => $itemData['alt_text'] ?? $itemData['title'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? 0,
                'status' => 'active',
                'created_by' => $user->id
            ]);

            if (isset($itemData['tagged_member_ids']) && is_array($itemData['tagged_member_ids'])) {
                $item->taggedMembers()->sync($itemData['tagged_member_ids']);
            }

            AuditLogService::log(
                'CMS',
                'Add Gallery Video',
                "Added video item to gallery ID {$gallery->id}: {$item->title}",
                null,
                $item->toArray(),
                $gallery,
                $gallery->church_id,
                $gallery->diocese_id
            );

            return $item;
        });
    }

    public static function publishGalleryAssets(MediaGallery $gallery): void
    {
        DB::transaction(function () use ($gallery) {
            foreach ($gallery->items as $item) {
                if ($item->media_type === 'image' && !str_starts_with($item->media_path, 'storage/')) {
                    // It is stored in private folder, move it to public folder
                    $privatePath = $item->media_path;
                    if (Storage::exists($privatePath)) {
                        $filename = basename($privatePath);
                        $publicFileContent = Storage::get($privatePath);
                        
                        Storage::disk('public')->put('galleries/published/' . $filename, $publicFileContent);
                        Storage::disk('public')->put('galleries/thumbnails/' . $filename, $publicFileContent);
                        
                        $item->update([
                            'media_path' => 'storage/galleries/published/' . $filename,
                            'thumbnail_path' => 'storage/galleries/thumbnails/' . $filename,
                        ]);

                        // Delete private draft
                        Storage::delete($privatePath);
                    }
                }
            }
        });
    }

    public static function parseVideoUrl(string $url): ?array
    {
        // YouTube patterns
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
            $videoId = $match[1];
            return [
                'provider' => 'youtube',
                'video_id' => $videoId,
                'embed_url' => "https://www.youtube.com/embed/{$videoId}"
            ];
        }
        // Vimeo patterns
        if (preg_match('%vimeo\.com/(?:channels/(?:\w+\/)?|groups/([^/]*)/videos/|album/(\d+)/video/|video/|)(\d+)(?:$|/|\?)%i', $url, $match)) {
            $videoId = $match[3];
            return [
                'provider' => 'vimeo',
                'video_id' => $videoId,
                'embed_url' => "https://player.vimeo.com/video/{$videoId}"
            ];
        }
        return null;
    }

    public static function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $query = MediaGallery::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }
        return $slug;
    }

    public static function passesChildPrivacyCheck(MediaItem $item): bool
    {
        // Enforce Child Photo Privacy Rule
        foreach ($item->taggedMembers as $member) {
            $isChild = false;
            if ($member->date_of_birth) {
                $age = Carbon::parse($member->date_of_birth)->age;
                if ($age < 18) {
                    $isChild = true;
                }
            }
            if ($isChild && !$member->photo_publication_consent) {
                return false;
            }
        }
        return true;
    }
}
