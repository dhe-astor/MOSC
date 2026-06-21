<?php

namespace App\Services;

use App\Models\KalpanaCircular;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class KalpanaCircularService
{
    public static function create(array $data, ?UploadedFile $file, User $user): KalpanaCircular
    {
        return DB::transaction(function () use ($data, $file, $user) {
            if ($file) {
                $filename = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $path = $file->storeAs('private/circulars', $filename);
                $data['file_path'] = $path;
            }

            $data['slug'] = self::generateSlug($data['title']);
            $data['created_by'] = $user->id;
            $data['diocese_id'] = $user->default_diocese_id ?? 1;

            $circular = KalpanaCircular::create($data);

            AuditLogService::log(
                'CMS',
                'Create Circular',
                "Created official circular/Kalpana: {$circular->title}",
                null,
                $circular->toArray(),
                $circular,
                $circular->church_id,
                $circular->diocese_id
            );

            return $circular;
        });
    }

    public static function update(KalpanaCircular $circular, array $data, ?UploadedFile $file, User $user): KalpanaCircular
    {
        return DB::transaction(function () use ($circular, $data, $file, $user) {
            $oldValues = $circular->toArray();

            if ($file) {
                if ($circular->file_path && Storage::exists($circular->file_path)) {
                    Storage::delete($circular->file_path);
                }

                $filename = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $path = $file->storeAs('private/circulars', $filename);
                $data['file_path'] = $path;
            }

            if (isset($data['title']) && $data['title'] !== $circular->title) {
                $data['slug'] = self::generateSlug($data['title'], $circular->id);
            } elseif (isset($data['slug']) && $data['slug'] !== $circular->slug) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $data['updated_by'] = $user->id;
            $circular->update($data);

            AuditLogService::log(
                'CMS',
                'Update Circular',
                "Updated circular details: {$circular->title}",
                $oldValues,
                $circular->toArray(),
                $circular,
                $circular->church_id,
                $circular->diocese_id
            );

            return $circular;
        });
    }

    public static function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $query = KalpanaCircular::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }
        return $slug;
    }
}
