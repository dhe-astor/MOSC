<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteApiTest extends TestCase
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

    public function test_public_guest_endpoints(): void
    {
        // 1. Create a published public page and a draft page
        WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'title' => 'Diocese History',
            'slug' => 'diocese-history',
            'page_type' => 'custom',
            'content' => 'Diocese history content details...',
            'status' => 'published',
            'visibility' => 'public',
            'created_by' => $this->superAdmin->id
        ]);

        WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'title' => 'Secret Page',
            'slug' => 'secret-page',
            'page_type' => 'custom',
            'content' => 'Top secret content...',
            'status' => 'draft',
            'visibility' => 'private',
            'created_by' => $this->superAdmin->id
        ]);

        // 2. Fetch public page - should pass
        $response1 = $this->getJson('/api/v1/public/pages/diocese-history');
        $response1->assertStatus(200)
            ->assertJsonPath('data.title', 'Diocese History');

        // 3. Fetch draft/private page - should fail
        $response2 = $this->getJson('/api/v1/public/pages/secret-page');
        $response2->assertStatus(404);

        // 4. Fetch parishes list
        $responseParishes = $this->getJson('/api/v1/public/parishes');
        $responseParishes->assertStatus(200);
        $this->assertNotEmpty($responseParishes->json('data'));

        // 5. Fetch contact info
        $responseContact = $this->getJson('/api/v1/public/contact');
        $responseContact->assertStatus(200)
            ->assertJsonPath('data.email', 'info@msoc-europe.org');
    }
}
