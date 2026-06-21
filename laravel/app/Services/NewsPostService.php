<?php

namespace App\Services;

use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NewsPostService
{
    public static function create(array $data, User $user): NewsPost
    {
        return DB::transaction(function () use ($data, $user) {
            $data['slug'] = self::generateSlug($data['title']);
            $data['created_by'] = $user->id;
            $data['diocese_id'] = $user->default_diocese_id ?? 1;

            $post = NewsPost::create($data);

            AuditLogService::log(
                'CMS',
                'Create News',
                "Created news post: {$post->title}",
                null,
                $post->toArray(),
                $post,
                $post->church_id,
                $post->diocese_id
            );

            return $post;
        });
    }

    public static function update(NewsPost $post, array $data, User $user): NewsPost
    {
        return DB::transaction(function () use ($post, $data, $user) {
            $oldValues = $post->toArray();

            if (isset($data['title']) && $data['title'] !== $post->title) {
                $data['slug'] = self::generateSlug($data['title'], $post->id);
            } elseif (isset($data['slug']) && $data['slug'] !== $post->slug) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $data['updated_by'] = $user->id;
            $post->update($data);

            AuditLogService::log(
                'CMS',
                'Update News',
                "Updated news post: {$post->title}",
                $oldValues,
                $post->toArray(),
                $post,
                $post->church_id,
                $post->diocese_id
            );

            return $post;
        });
    }

    public static function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $query = NewsPost::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }
        return $slug;
    }
}
