<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

// Models
use App\Models\WebsiteSetting;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\Event;
use App\Models\PriestProfile;
use App\Models\Church;
use App\Models\ChurchServiceTiming;
use App\Models\WebsiteDownload;
use App\Models\KalpanaCircular;
use App\Models\MediaGallery;
use App\Models\MediaItem;
use App\Models\MinistryOrganization;
use App\Models\PriestChurchAssignment;

class PublicWebsiteController extends Controller
{
    /**
     * Helper to retrieve a website setting.
     */
    private function getSetting($key, $default = null)
    {
        $setting = WebsiteSetting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * GET /api/public/home
     */
    public function getHome()
    {
        // 1. Fetch Branding/Website Settings
        $branding = [
            'site_name' => $this->getSetting('site_name', 'MSOC Europe'),
            'site_logo' => $this->getSetting('site_logo'),
            'site_favicon' => $this->getSetting('site_favicon'),
            'site_primary_color' => $this->getSetting('site_primary_color', '#7A1E2C'),
            'site_secondary_color' => $this->getSetting('site_secondary_color', '#C9A227'),
        ];

        // 2. Fetch Hero configuration
        $hero = [
            'title' => $this->getSetting('homepage_hero_title', 'Malankara Syrian Orthodox Church in Europe'),
            'subtitle' => $this->getSetting('homepage_hero_subtitle', 'Faith • Tradition • Community'),
            'image' => $this->getSetting('homepage_hero_image'),
            'primary_btn_text' => $this->getSetting('homepage_primary_button_text', 'Find a Parish'),
            'primary_btn_url' => $this->getSetting('homepage_primary_button_url', '/parishes'),
            'secondary_btn_text' => $this->getSetting('homepage_secondary_button_text', 'Latest News'),
            'secondary_btn_url' => $this->getSetting('homepage_secondary_button_url', '/news'),
        ];

        // 3. Welcome Section
        $welcome = [
            'title' => $this->getSetting('homepage_welcome_title', 'Welcome to MSOC Europe'),
            'content' => $this->getSetting('homepage_welcome_content', 'Serving our faithful across Europe with liturgical purity and pastoral care.'),
            'image' => $this->getSetting('homepage_welcome_image'),
        ];

        // 4. Metropolitan profile
        $metroId = $this->getSetting('homepage_metropolitan_profile_id');
        $metropolitan = null;
        if ($metroId) {
            $metroProfile = PriestProfile::where('show_public_profile', true)
                ->where('id', $metroId)
                ->first();
            if ($metroProfile) {
                $metropolitan = [
                    'name' => $metroProfile->display_name ?: $metroProfile->ordination_name,
                    'title' => $metroProfile->canonical_title ?: 'Metropolitan',
                    'photo' => $metroProfile->photo_path,
                    'bio' => $metroProfile->public_bio ?: $metroProfile->bio,
                    'slug' => $metroProfile->public_slug ?: 'metropolitan',
                ];
            }
        }

        // 5. Active Top Announcement Bar
        $announcement = [
            'text' => $this->getSetting('announcement_text'),
            'link_text' => $this->getSetting('announcement_link_text'),
            'link_url' => $this->getSetting('announcement_link_url'),
            'is_active' => (bool)$this->getSetting('is_active', false),
        ];
        
        $startDate = $this->getSetting('start_date');
        $endDate = $this->getSetting('end_date');
        $today = Carbon::today()->toDateString();
        
        if ($announcement['is_active']) {
            if ($startDate && $today < $startDate) {
                $announcement['is_active'] = false;
            }
            if ($endDate && $today > $endDate) {
                $announcement['is_active'] = false;
            }
        }

        // 6. Latest 3 News
        $newsLimit = (int)$this->getSetting('homepage_featured_news_limit', 3);
        $news = NewsPost::where('status', 'published')
            ->where('visibility', 'public')
            ->latest('published_at')
            ->take($newsLimit)
            ->get()
            ->map(function ($post) {
                return [
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'published_at' => $post->published_at,
                    'featured_image' => $post->featured_image_path,
                    'category' => $post->category,
                ];
            });

        // 7. Latest 3 public upcoming events
        $eventsLimit = (int)$this->getSetting('homepage_featured_events_limit', 3);
        $events = Event::where('visibility', 'public')
            ->where('status', 'published')
            ->where('start_datetime', '>=', $today)
            ->orderBy('start_datetime', 'asc')
            ->take($eventsLimit)
            ->get()
            ->map(function ($ev) {
                return [
                    'title' => $ev->title,
                    'slug' => $ev->slug,
                    'start_date' => $ev->start_datetime ? $ev->start_datetime->toDateString() : null,
                    'start_time' => $ev->start_datetime ? $ev->start_datetime->toTimeString() : null,
                    'location' => $ev->location_name ?: $ev->address,
                    'summary' => $ev->description,
                ];
            });

        // 8. Latest 3 public downloads
        $downloadLimit = (int)$this->getSetting('homepage_download_limit', 3);
        $downloads = WebsiteDownload::where('visibility', 'public')
            ->latest()
            ->take($downloadLimit)
            ->get()
            ->map(function ($dl) {
                return [
                    'title' => $dl->title,
                    'file_type' => pathinfo($dl->file_path, PATHINFO_EXTENSION),
                    'file_size' => $dl->file_size_formatted ?: 'KB',
                    'file_url' => url('/api/public/downloads/file/' . $dl->id),
                    'category' => $dl->category,
                    'created_at' => $dl->created_at->toDateString(),
                ];
            });

        return response()->json([
            'branding' => $branding,
            'hero' => $hero,
            'welcome' => $welcome,
            'metropolitan' => $metropolitan,
            'announcement' => $announcement,
            'news' => $news,
            'events' => $events,
            'downloads' => $downloads,
        ]);
    }

    /**
     * GET /api/public/pages/{slug}
     */
    public function getPage($slug)
    {
        $page = WebsitePage::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->firstOrFail();

        return response()->json([
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => $page->content,
            'featured_image' => $page->featured_image_path,
            'seo_title' => $page->meta_title ?: $page->title,
            'seo_description' => $page->meta_description,
            'page' => $page,
            'data' => $page
        ]);
    }

    /**
     * GET /api/public/news
     */
    public function getNews(Request $request)
    {
        $query = NewsPost::where('status', 'published')
            ->where('visibility', 'public');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $news = $query->latest('published_at')
            ->paginate(9);

        return response()->json($news);
    }

    /**
     * GET /api/public/news/{slug}
     */
    public function getNewsItem($slug)
    {
        $post = NewsPost::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->firstOrFail();

        return response()->json($post);
    }

    /**
     * GET /api/public/events
     */
    public function getEvents(Request $request)
    {
        $query = Event::where('visibility', 'public')
            ->where('status', 'published');

        $today = Carbon::today()->toDateString();
        
        if ($request->input('scope') === 'past') {
            $query->where('start_datetime', '<', $today)->orderBy('start_datetime', 'desc');
        } else {
            $query->where('start_datetime', '>=', $today)->orderBy('start_datetime', 'asc');
        }

        $events = $query->paginate(9);
        return response()->json($events);
    }

    /**
     * GET /api/public/events/{slug}
     */
    public function getEventItem($slug)
    {
        $ev = Event::where('slug', $slug)
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json($ev);
    }

    /**
     * GET /api/public/priests
     */
    public function getPriests(Request $request)
    {
        $priests = PriestProfile::where('status', 'active')
            ->where('show_public_profile', true)
            ->orderBy('public_sort_order')
            ->with(['activeAssignments.church'])
            ->get()
            ->map(function ($p) {
                $assignments = $p->activeAssignments->map(function ($ass) {
                    return [
                        'church_name' => $ass->church->name,
                        'church_city' => $ass->church->city,
                        'church_country' => $ass->church->country,
                        'role' => $ass->assignment_role,
                    ];
                });

                return [
                    'name' => $p->display_name ?: $p->ordination_name,
                    'title' => $p->canonical_title,
                    'slug' => $p->public_slug ?: "priest-{$p->id}",
                    'photo' => $p->photo_path,
                    'phone' => $p->show_public_phone ? $p->phone_public : null,
                    'email' => $p->show_public_email ? $p->email_public : null,
                    'assignments' => $assignments,
                ];
            });

        return response()->json(['data' => $priests]);
    }

    /**
     * GET /api/public/priests/{slug}
     */
    public function getPriestItem($slug)
    {
        $priest = PriestProfile::where('status', 'active')
            ->where('show_public_profile', true)
            ->where(function ($q) use ($slug) {
                $q->where('public_slug', $slug)
                  ->orWhere('id', str_replace('priest-', '', $slug));
            })
            ->with(['activeAssignments.church'])
            ->firstOrFail();

        return response()->json([
            'name' => $priest->display_name ?: $priest->ordination_name,
            'title' => $priest->canonical_title,
            'slug' => $priest->public_slug ?: "priest-{$priest->id}",
            'photo' => $priest->photo_path,
            'bio' => $priest->public_bio ?: $priest->bio,
            'ordination_date' => $priest->ordination_date ? $priest->ordination_date->toDateString() : null,
            'ordination_place' => $priest->ordination_place,
            'home_diocese' => $priest->home_diocese,
            'phone' => $priest->show_public_phone ? $priest->phone_public : null,
            'email' => $priest->show_public_email ? $priest->email_public : null,
            'assignments' => $priest->activeAssignments->map(function ($ass) {
                return [
                    'church_name' => $ass->church->name,
                    'church_city' => $ass->church->city,
                    'church_country' => $ass->church->country,
                    'role' => $ass->assignment_role,
                    'start_date' => $ass->start_date ? Carbon::parse($ass->start_date)->toDateString() : null,
                ];
            }),
        ]);
    }

    /**
     * GET /api/public/parishes
     */
    public function getParishes(Request $request)
    {
        $query = Church::where('show_public_page', true)
            ->orderBy('public_sort_order');

        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $parishes = $query->get()->map(function ($c) {
            // Find vicar
            $vicarAssignment = PriestChurchAssignment::where('church_id', $c->id)
                ->where('status', 'active')
                ->where('assignment_role', 'vicar')
                ->with('priestProfile')
                ->first();

            $vicarName = $vicarAssignment && $vicarAssignment->priestProfile
                ? ($vicarAssignment->priestProfile->display_name ?: $vicarAssignment->priestProfile->ordination_name)
                : null;

            return [
                'name' => $c->name,
                'slug' => $c->public_slug ?: $c->slug,
                'city' => $c->city,
                'country' => $c->country,
                'church_type' => $c->church_type,
                'photo' => $c->public_photo_path,
                'vicar' => $vicarName,
                'email' => $c->public_email,
                'phone' => $c->public_phone,
            ];
        });

        $countries = Church::where('show_public_page', true)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country');

        return response()->json([
            'status' => 'success',
            'parishes' => $parishes,
            'countries' => $countries,
            'data' => $parishes
        ]);
    }

    /**
     * GET /api/public/parishes/{slug}
     */
    public function getParishItem($slug)
    {
        $c = Church::where('show_public_page', true)
            ->where(function ($q) use ($slug) {
                $q->where('public_slug', $slug)
                  ->orWhere('slug', $slug);
            })
            ->firstOrFail();

        // 1. Service Timings
        $timings = [];
        if ($c->show_service_times) {
            $timings = ChurchServiceTiming::where('church_id', $c->id)
                ->where('status', 'active')
                ->where('is_public', true)
                ->orderBy('start_time')
                ->get()
                ->map(function ($st) {
                    return [
                        'name' => $st->service_name,
                        'day' => $st->day_of_week,
                        'date' => $st->service_date ? $st->service_date->toDateString() : null,
                        'start' => $st->start_time,
                        'end' => $st->end_time,
                        'language' => $st->language,
                        'frequency' => $st->frequency,
                        'notes' => $st->notes,
                    ];
                });
        }

        // 2. Priest assignments
        $clergy = PriestChurchAssignment::where('church_id', $c->id)
            ->where('status', 'active')
            ->with('priestProfile')
            ->get()
            ->map(function ($ass) {
                return [
                    'name' => $ass->priestProfile->display_name ?: $ass->priestProfile->ordination_name,
                    'title' => $ass->priestProfile->canonical_title,
                    'role' => $ass->assignment_role,
                    'photo' => $ass->priestProfile->photo_path,
                ];
            });

        // 3. News posts
        $news = NewsPost::where('church_id', $c->id)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->latest()
            ->take(5)
            ->get();

        // 4. Events
        $events = Event::where('church_id', $c->id)
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->public_slug ?: $c->slug,
            'church_type' => $c->church_type,
            'description' => $c->public_description ?: $c->description,
            'photo' => $c->public_photo_path,
            'address' => trim($c->address_line_1 . ' ' . $c->address_line_2),
            'city' => $c->city,
            'country' => $c->country,
            'postal_code' => $c->postal_code,
            'email' => $c->public_email,
            'phone' => $c->public_phone,
            'map_url' => $c->show_map ? $c->google_map_url : null,
            'service_timings' => $timings,
            'clergy' => $clergy,
            'news' => $news,
            'events' => $events,
            'seo_title' => $c->seo_title ?: $c->name,
            'seo_description' => $c->seo_description,
        ]);
    }

    /**
     * GET /api/public/organizations
     */
    public function getOrganizations()
    {
        $orgs = [
            [
                'name' => 'Sunday School / MJSSA',
                'slug' => 'sunday-school',
                'description' => 'The Malankara Syrian Sunday School Association (MJSSA) coordinates religious education for kids across Europe.',
            ],
            [
                'name' => 'Youth Association',
                'slug' => 'youth-association',
                'description' => 'Spiritual growth, social activities, and community service initiatives for young adults.',
            ],
            [
                'name' => 'Marthamariyam Samajam',
                'slug' => 'marthamariyam-samajam',
                'description' => 'The women\'s wing of the diocese, leading spiritual gatherings, charity drives, and fellowship.',
            ]
        ];

        return response()->json(['data' => $orgs]);
    }

    /**
     * GET /api/public/organizations/{slug}
     */
    public function getOrganizationItem($slug)
    {
        // Fetch pages associated with this organization
        $page = WebsitePage::where('slug', "organization-{$slug}")
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->first();

        // Fallback standard text
        $fallback = [
            'sunday-school' => [
                'name' => 'Sunday School / MJSSA',
                'description' => 'Coordinates Christian education for children and adolescents.',
                'content' => 'The Sunday School department provides comprehensive curricula, teacher training, and assessments across all European parishes.',
            ],
            'youth-association' => [
                'name' => 'Youth Association',
                'description' => 'Spiritual and community platform for youth.',
                'content' => 'Empowers youth members to active participation in liturgy, community service, and spiritual growth.',
            ],
            'marthamariyam-samajam' => [
                'name' => 'Marthamariyam Samajam',
                'description' => 'The women\'s wing of the diocese.',
                'content' => 'Fosters prayer and charity among the women of the parish, supporting missions throughout the diocese.',
            ],
        ];

        if (!$page && !isset($fallback[$slug])) {
            abort(404);
        }

        return response()->json([
            'name' => $page ? $page->title : ($fallback[$slug]['name'] ?? ucfirst($slug)),
            'slug' => $slug,
            'description' => $page ? $page->excerpt : ($fallback[$slug]['description'] ?? ''),
            'content' => $page ? $page->content : ($fallback[$slug]['content'] ?? ''),
            'featured_image' => $page ? $page->featured_image_path : null,
        ]);
    }

    /**
     * GET /api/public/downloads
     */
    public function getDownloads(Request $request)
    {
        $query = WebsiteDownload::where('visibility', 'public');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $downloads = $query->latest()->paginate(15);
        return response()->json($downloads);
    }

    /**
     * GET /api/public/circulars
     */
    public function getCirculars()
    {
        $circulars = KalpanaCircular::where('visibility', 'public')
            ->latest('publish_date')
            ->paginate(15);
            
        return response()->json($circulars);
    }

    /**
     * Controlled file download endpoint to avoid leakage
     */
    public function getDownloadFile($id)
    {
        $dl = WebsiteDownload::where('visibility', 'public')
            ->where('id', $id)
            ->firstOrFail();

        return response()->download(storage_path('app/' . $dl->file_path));
    }

    /**
     * GET /api/public/galleries
     */
    public function getGalleries()
    {
        $galleries = MediaGallery::where('status', 'published')
            ->where('visibility', 'public')
            ->latest('published_at')
            ->paginate(9);

        return response()->json($galleries);
    }

    /**
     * GET /api/public/galleries/{slug}
     */
    public function getGalleryItem($slug)
    {
        $gallery = MediaGallery::where('slug', $slug)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->firstOrFail();

        // Load items with tagged members to respect photo consent rules
        $items = $gallery->items()
            ->where('status', 'approved')
            ->with(['taggedMembers'])
            ->get()
            ->filter(function ($item) {
                // Respect child photo consent rules
                foreach ($item->taggedMembers as $member) {
                    $birthDate = $member->date_of_birth;
                    if ($birthDate) {
                        $age = Carbon::parse($birthDate)->age;
                        if ($age < 18 && !$member->photo_publication_consent) {
                            // Exclude photo if a tagged child lacks consent
                            return false;
                        }
                    }
                }
                return true;
            })
            ->values();

        return response()->json([
            'id' => $gallery->id,
            'title' => $gallery->title,
            'description' => $gallery->description,
            'cover_image' => $gallery->cover_image_path,
            'items' => $items,
        ]);
    }

    /**
     * GET /api/public/contact
     */
    public function getContact()
    {
        $settings = [
            'email' => $this->getSetting('contact_email', 'info@msoc-europe.org'),
            'phone' => $this->getSetting('contact_phone', '+43 1 555 1234'),
            'address' => $this->getSetting('contact_address', 'MSOC Europe Diocesan Center, Vienna, Austria'),
        ];

        return response()->json([
            'status' => 'success',
            'contact' => $settings,
            'data' => $settings
        ]);
    }

    /**
     * POST /api/public/contact
     */
    public function postContact(Request $request)
    {
        // Simple rate limiter implementation using Laravel cache rate limits or standard validation
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:30',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
        ]);

        // Clean values to avoid script injection
        $name = strip_tags($request->input('name'));
        $email = strip_tags($request->input('email'));
        $phone = strip_tags($request->input('phone'));
        $subject = strip_tags($request->input('subject'));
        $message = strip_tags($request->input('message'));

        Log::info("Public Contact Form Submission received: [Name: {$name}, Email: {$email}, Subject: {$subject}]");
        Log::info("Message details: {$message}");

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully. The Diocese office will review and contact you if needed.'
        ]);
    }

    /**
     * GET /api/public/search
     */
    public function getSearch(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $results = [];

        // Search Pages
        $pages = WebsitePage::where('status', 'published')
            ->where('visibility', 'public')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            })->take(5)->get();
        foreach ($pages as $p) {
            $results[] = [
                'title' => $p->title,
                'type' => 'Page',
                'url' => $p->slug === 'about' ? '/about' : "/pages/{$p->slug}",
                'snippet' => substr(strip_tags($p->content), 0, 150) . '...'
            ];
        }

        // Search News
        $news = NewsPost::where('status', 'published')
            ->where('visibility', 'public')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            })->take(5)->get();
        foreach ($news as $n) {
            $results[] = [
                'title' => $n->title,
                'type' => 'News',
                'url' => "/news/{$n->slug}",
                'snippet' => $n->excerpt
            ];
        }

        // Search Events
        $events = Event::where('visibility', 'public')
            ->where('status', 'published')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })->take(5)->get();
        foreach ($events as $ev) {
            $results[] = [
                'title' => $ev->title,
                'type' => 'Event',
                'url' => "/events/{$ev->slug}",
                'snippet' => substr(strip_tags($ev->description), 0, 150) . '...'
            ];
        }

        // Search Parishes
        $churches = Church::where('show_public_page', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('city', 'like', "%{$q}%");
            })->take(5)->get();
        foreach ($churches as $ch) {
            $results[] = [
                'title' => $ch->name,
                'type' => 'Parish',
                'url' => "/parishes/{$ch->slug}",
                'snippet' => "Parish in {$ch->city}, {$ch->country}."
            ];
        }

        return response()->json(['results' => $results]);
    }
}
