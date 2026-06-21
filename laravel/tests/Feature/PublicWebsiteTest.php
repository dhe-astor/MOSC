<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_dynamic_sitemap_generation(): void
    {
        // Create sample page
        WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'title' => 'Feast Info',
            'slug' => 'feast-info',
            'page_type' => 'custom',
            'content' => 'Sample feast info',
            'status' => 'published',
            'visibility' => 'public',
            'created_by' => $this->superAdmin->id
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<loc>', $response->getContent());
        $this->assertStringContainsString('feast-info', $response->getContent());
    }

    public function test_robots_txt_content(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('User-agent: *', $response->getContent());
        $this->assertStringContainsString('Disallow: /portal/', $response->getContent());
        $this->assertStringContainsString('Sitemap:', $response->getContent());
    }

    public function test_contact_form_submission_rate_limiting(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Inquiry',
            'message' => 'Hello, I have a question.',
        ];

        // First 3 submissions should succeed (within the 3 per minute rate limit window)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/public/contact', $payload);
            $response->assertStatus(200);
        }

        // The 4th submission should trigger rate limiting (429 Too Many Requests)
        $response = $this->postJson('/api/v1/public/contact', $payload);
        $response->assertStatus(429);
    }
}
