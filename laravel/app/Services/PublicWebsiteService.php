<?php

namespace App\Services;

use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\WebsiteDownload;
use App\Models\KalpanaCircular;
use App\Models\MediaGallery;
use App\Models\Church;
use App\Models\Priest;
use App\Models\Event;
use App\Models\Course;
use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\DB;

class PublicWebsiteService
{
    public static function getSetting(string $key, $default = null)
    {
        $setting = WebsiteSetting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function getHomepageData(): array
    {
        $newsCount = self::getSetting('homepage_featured_news_count', 3);
        $eventCount = self::getSetting('homepage_featured_events_count', 3);
        $galleryCount = self::getSetting('homepage_featured_gallery_count', 3);

        $news = NewsPost::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('published_at', 'desc')
            ->limit($newsCount)
            ->get()
            ->map(fn($n) => [
                'title' => $n->title,
                'slug' => $n->slug,
                'excerpt' => $n->excerpt,
                'content' => $n->content,
                'featured_image_path' => $n->featured_image_path,
                'category' => $n->category,
                'language' => $n->language,
                'published_at' => $n->published_at?->toIso8601String()
            ]);

        $events = Event::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('start_datetime', 'asc')
            ->limit($eventCount)
            ->get()
            ->map(fn($e) => [
                'title' => $e->title,
                'slug' => $e->slug,
                'description' => $e->description,
                'start_datetime' => $e->start_datetime?->toIso8601String(),
                'end_datetime' => $e->end_datetime?->toIso8601String(),
                'location_name' => $e->location_name,
                'address' => $e->address,
                'mode' => $e->mode,
                'registration_required' => $e->registration_required,
                'registration_fee' => $e->registration_fee,
                'banner_path' => $e->banner_path
            ]);

        $galleries = MediaGallery::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('published_at', 'desc')
            ->limit($galleryCount)
            ->get()
            ->map(fn($g) => [
                'title' => $g->title,
                'slug' => $g->slug,
                'description' => $g->description,
                'gallery_type' => $g->gallery_type,
                'cover_image_path' => $g->cover_image_path
            ]);

        return [
            'contact_email' => self::getSetting('public_contact_email', 'info@msoc-europe.org'),
            'contact_phone' => self::getSetting('public_contact_phone', '+43 1 234567'),
            'footer_address' => self::getSetting('public_footer_address', 'MSOC Europe Diocesan Center, Vienna, Austria'),
            'social_links' => self::getSetting('social_links', []),
            'featured_news' => $news,
            'featured_events' => $events,
            'featured_galleries' => $galleries
        ];
    }

    public static function getPage(string $slug): ?array
    {
        $page = WebsitePage::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        if (!$page) {
            return null;
        }

        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => $page->content,
            'excerpt' => $page->excerpt,
            'featured_image_path' => $page->featured_image_path,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'meta_keywords' => $page->meta_keywords
        ];
    }

    public static function getParishes(): array
    {
        return Church::orderBy('name')
            ->get()
            ->map(fn($c) => [
                'name' => $c->name,
                'slug' => $c->public_page_slug ?? $c->slug,
                'short_name' => $c->short_name,
                'city' => $c->city,
                'country' => $c->countryRelation?->name ?? $c->country,
                'address' => $c->address,
                'email' => $c->email,
                'phone' => $c->phone,
                'website_url' => $c->website_url,
                'map_link' => $c->map_link
            ])
            ->toArray();
    }

    public static function getParish(string $slug): ?array
    {
        $church = Church::where('public_page_slug', $slug)
            ->orWhere('slug', $slug)
            ->first();

        if (!$church) {
            return null;
        }

        // Fetch parish page if registered in website_pages
        $page = WebsitePage::where('church_id', $church->id)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        return [
            'name' => $church->name,
            'slug' => $church->public_page_slug ?? $church->slug,
            'short_name' => $church->short_name,
            'city' => $church->city,
            'country' => $church->countryRelation?->name ?? $church->country,
            'address' => $church->address,
            'email' => $church->email,
            'phone' => $church->phone,
            'map_link' => $church->map_link,
            'custom_content' => $page ? [
                'title' => $page->title,
                'content' => $page->content
            ] : null
        ];
    }

    public static function getPriests(): array
    {
        return Priest::where('status', 'active')
            ->where('show_on_website', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($p) => [
                'title' => $p->title,
                'full_name' => $p->full_name,
                'clergy_rank' => $p->clergy_rank,
                'photo_path' => $p->photo_path,
                'biography' => $p->biography
            ])
            ->toArray();
    }

    public static function getCouncilMembers(): array
    {
        // Diocese council consists of active priests showing on website and secretaries
        return Priest::where('status', 'active')
            ->where('show_on_website', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($p) => [
                'name' => $p->full_name,
                'role' => $p->clergy_rank === 'Metropolitan' ? 'Patron / Metropolitan' : 'Council Clergy Representative',
                'photo_path' => $p->photo_path
            ])
            ->toArray();
    }

    public static function getNews(): array
    {
        return NewsPost::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(fn($n) => [
                'title' => $n->title,
                'slug' => $n->slug,
                'excerpt' => $n->excerpt,
                'content' => $n->content,
                'featured_image_path' => $n->featured_image_path,
                'category' => $n->category,
                'language' => $n->language,
                'published_at' => $n->published_at?->toIso8601String()
            ])
            ->toArray();
    }

    public static function getNewsPost(string $slug): ?array
    {
        $n = NewsPost::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        if (!$n) {
            return null;
        }

        return [
            'title' => $n->title,
            'slug' => $n->slug,
            'excerpt' => $n->excerpt,
            'content' => $n->content,
            'featured_image_path' => $n->featured_image_path,
            'category' => $n->category,
            'language' => $n->language,
            'published_at' => $n->published_at?->toIso8601String()
        ];
    }

    public static function getEvents(): array
    {
        return Event::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(fn($e) => [
                'title' => $e->title,
                'slug' => $e->slug,
                'description' => $e->description,
                'start_datetime' => $e->start_datetime?->toIso8601String(),
                'end_datetime' => $e->end_datetime?->toIso8601String(),
                'location_name' => $e->location_name,
                'address' => $e->address,
                'mode' => $e->mode,
                'registration_required' => $e->registration_required,
                'registration_fee' => $e->registration_fee,
                'banner_path' => $e->banner_path
            ])
            ->toArray();
    }

    public static function getEvent(string $slug): ?array
    {
        $e = Event::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        if (!$e) {
            return null;
        }

        return [
            'title' => $e->title,
            'slug' => $e->slug,
            'description' => $e->description,
            'start_datetime' => $e->start_datetime?->toIso8601String(),
            'end_datetime' => $e->end_datetime?->toIso8601String(),
            'location_name' => $e->location_name,
            'address' => $e->address,
            'mode' => $e->mode,
            'registration_required' => $e->registration_required,
            'registration_fee' => $e->registration_fee,
            'banner_path' => $e->banner_path
        ];
    }

    public static function getCourses(): array
    {
        return Course::where('status', 'active')
            ->where('show_on_portal', true)
            ->get()
            ->map(fn($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'course_type' => $c->course_type,
                'description' => $c->description,
                'eligibility' => $c->eligibility,
                'default_fee_amount' => $c->default_fee_amount,
                'currency' => $c->currency
            ])
            ->toArray();
    }

    public static function getGalleries(): array
    {
        return MediaGallery::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(fn($g) => [
                'title' => $g->title,
                'slug' => $g->slug,
                'description' => $g->description,
                'gallery_type' => $g->gallery_type,
                'cover_image_path' => $g->cover_image_path
            ])
            ->toArray();
    }

    public static function getGalleryBySlug(string $slug): ?array
    {
        $gallery = MediaGallery::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        if (!$gallery) {
            return null;
        }

        $items = [];
        foreach ($gallery->items()->where('status', 'active')->with('taggedMembers')->get() as $item) {
            if (MediaGalleryService::passesChildPrivacyCheck($item)) {
                $items[] = [
                    'title' => $item->title,
                    'caption' => $item->caption,
                    'media_type' => $item->media_type,
                    'media_path' => $item->media_path,
                    'thumbnail_path' => $item->thumbnail_path,
                    'external_video_url' => $item->external_video_url,
                    'alt_text' => $item->alt_text,
                    'sort_order' => $item->sort_order
                ];
            }
        }

        return [
            'title' => $gallery->title,
            'slug' => $gallery->slug,
            'description' => $gallery->description,
            'gallery_type' => $gallery->gallery_type,
            'cover_image_path' => $gallery->cover_image_path,
            'items' => $items
        ];
    }

    public static function getDownloads(): array
    {
        return WebsiteDownload::where('status', 'published')
            ->where('visibility', 'public')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'slug' => $d->slug,
                'description' => $d->description,
                'download_type' => $d->download_type,
                'file_name' => $d->file_name,
                'file_type' => $d->file_type,
                'file_size' => $d->file_size,
                'download_count' => $d->download_count
            ])
            ->toArray();
    }

    public static function getKalpanaCirculars(): array
    {
        return KalpanaCircular::where('status', 'published')
            ->where('visibility', 'public')
            ->orderBy('circular_date', 'desc')
            ->get()
            ->map(fn($k) => [
                'title' => $k->title,
                'slug' => $k->slug,
                'circular_type' => $k->circular_type,
                'circular_date' => $k->circular_date->toDateString(),
                'reference_number' => $k->reference_number,
                'content' => $k->content,
                'file_path' => $k->file_path ? asset($k->file_path) : null
            ])
            ->toArray();
    }
}
