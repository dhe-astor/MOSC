<?php

use Illuminate\Support\Facades\Route;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\Event;
use App\Models\Priest;
use App\Models\Church;
use App\Models\MediaGallery;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sitemap.xml', function () {
    $urls = [
        url('/'),
        url('/about'),
        url('/metropolitan'),
        url('/administration'),
        url('/priests'),
        url('/parishes'),
        url('/organizations'),
        url('/circulars'),
        url('/downloads'),
        url('/gallery'),
        url('/contact'),
    ];

    // Add CMS pages
    $pages = WebsitePage::where('status', 'published')->where('visibility', 'public')->get();
    foreach ($pages as $p) {
        if (!in_array($p->slug, ['about', 'metropolitan', 'administration', 'sunday-school'])) {
            $urls[] = url("/pages/{$p->slug}");
        }
    }

    // Add news posts
    $news = NewsPost::where('status', 'published')->where('visibility', 'public')->get();
    foreach ($news as $n) {
        $urls[] = url("/news/{$n->slug}");
    }

    // Add events
    $events = Event::where('status', 'published')->where('visibility', 'public')->get();
    foreach ($events as $e) {
        $urls[] = url("/events/{$e->slug}");
    }

    // Add priests
    $priests = Priest::where('status', 'active')->where('is_public', true)->get();
    foreach ($priests as $pr) {
        $urls[] = url("/priests/{$pr->public_slug}");
    }

    // Add parishes
    $churches = Church::where('status', 'active')->where('is_public', true)->get();
    foreach ($churches as $ch) {
        $urls[] = url("/parishes/{$ch->public_slug}");
    }

    // Add media galleries
    $galleries = MediaGallery::where('status', 'published')->where('visibility', 'public')->get();
    foreach ($galleries as $g) {
        $urls[] = url("/gallery/{$g->slug}");
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        $xml .= '<url>';
        $xml .= '<loc>' . htmlspecialchars($url) . '</loc>';
        $xml .= '<changefreq>weekly</changefreq>';
        $xml .= '<priority>0.8</priority>';
        $xml .= '</url>';
    }
    $xml .= '</urlset>';

    return response($xml, 200, [
        'Content-Type' => 'application/xml',
    ]);
});

Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Disallow: /api/\n";
    $content .= "Disallow: /admin/\n";
    $content .= "Disallow: /portal/\n";
    $content .= "\nSitemap: " . url('/sitemap.xml') . "\n";

    return response($content, 200, [
        'Content-Type' => 'text/plain',
    ]);
});
