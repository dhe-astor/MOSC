<?php

namespace App\Services;

use App\Models\WebsiteDownload;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class DownloadService
{
    public static function create(array $data, UploadedFile $file, User $user): WebsiteDownload
    {
        return DB::transaction(function () use ($data, $file, $user) {
            $filename = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            
            // Store inside private directory
            $path = $file->storeAs('private/downloads', $filename);

            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientMimeType();
            $data['file_size'] = $file->getSize();
            $data['slug'] = self::generateSlug($data['title']);
            $data['created_by'] = $user->id;
            $data['diocese_id'] = $user->default_diocese_id ?? 1;

            $download = WebsiteDownload::create($data);

            AuditLogService::log(
                'CMS',
                'Upload Downloadable',
                "Uploaded file: {$download->file_name} for {$download->title}",
                null,
                $download->toArray(),
                $download,
                $download->church_id,
                $download->diocese_id
            );

            return $download;
        });
    }

    public static function update(WebsiteDownload $download, array $data, ?UploadedFile $file, User $user): WebsiteDownload
    {
        return DB::transaction(function () use ($download, $data, $file, $user) {
            $oldValues = $download->toArray();

            if ($file) {
                // Delete old file
                if (Storage::exists($download->file_path)) {
                    Storage::delete($download->file_path);
                }

                $filename = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $path = $file->storeAs('private/downloads', $filename);

                $data['file_path'] = $path;
                $data['file_name'] = $file->getClientOriginalName();
                $data['file_type'] = $file->getClientMimeType();
                $data['file_size'] = $file->getSize();
            }

            if (isset($data['title']) && $data['title'] !== $download->title) {
                $data['slug'] = self::generateSlug($data['title'], $download->id);
            } elseif (isset($data['slug']) && $data['slug'] !== $download->slug) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $data['updated_by'] = $user->id;
            $download->update($data);

            AuditLogService::log(
                'CMS',
                'Update Downloadable',
                "Updated file details: {$download->title}",
                $oldValues,
                $download->toArray(),
                $download,
                $download->church_id,
                $download->diocese_id
            );

            return $download;
        });
    }

    public static function incrementCount(WebsiteDownload $download): void
    {
        $download->increment('download_count');
        
        AuditLogService::log(
            'CMS',
            'Download File',
            "Downloaded file: {$download->file_name} - Title: {$download->title}",
            null,
            null,
            $download,
            $download->church_id,
            $download->diocese_id
        );
    }

    public static function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $query = WebsiteDownload::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }
        return $slug;
    }
}
