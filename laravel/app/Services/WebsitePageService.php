<?php

namespace App\Services;

use App\Models\WebsitePage;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WebsitePageService
{
    public static function create(array $data, User $user): WebsitePage
    {
        return DB::transaction(function () use ($data, $user) {
            $data['slug'] = self::generateSlug($data['title']);
            $data['created_by'] = $user->id;
            $data['diocese_id'] = $user->default_diocese_id ?? 1;
            
            $page = WebsitePage::create($data);

            AuditLogService::log(
                'CMS',
                'Create Page',
                "Created website page: {$page->title}",
                null,
                $page->toArray(),
                $page,
                $page->church_id,
                $page->diocese_id
            );

            return $page;
        });
    }

    public static function update(WebsitePage $page, array $data, User $user): WebsitePage
    {
        return DB::transaction(function () use ($page, $data, $user) {
            $oldValues = $page->toArray();

            if (isset($data['title']) && $data['title'] !== $page->title) {
                // If slug isn't provided or is being generated from title
                $data['slug'] = self::generateSlug($data['title'], $page->id);
            } elseif (isset($data['slug']) && $data['slug'] !== $page->slug) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $data['updated_by'] = $user->id;
            $page->update($data);

            AuditLogService::log(
                'CMS',
                'Update Page',
                "Updated website page: {$page->title}",
                $oldValues,
                $page->toArray(),
                $page,
                $page->church_id,
                $page->diocese_id
            );

            return $page;
        });
    }

    public static function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $query = WebsitePage::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }
        return $slug;
    }
}
