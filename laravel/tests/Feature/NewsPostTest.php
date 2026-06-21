<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\NewsPost;
use App\Models\ContentApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPostTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_create_and_submit_news_post(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/cms/news', [
                'title' => 'Vienna Parish Feast Update',
                'church_id' => $this->vienna->id,
                'content' => 'Full update text...',
                'category' => 'parish',
                'language' => 'en',
                'visibility' => 'public'
            ]);

        $response->assertStatus(210);
        $postId = $response->json('data.id');

        $this->assertDatabaseHas('news_posts', [
            'id' => $postId,
            'status' => 'draft',
            'title' => 'Vienna Parish Feast Update'
        ]);

        $submitResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/cms/news/{$postId}/submit");

        $submitResponse->assertStatus(200);

        $this->assertDatabaseHas('news_posts', [
            'id' => $postId,
            'status' => 'submitted'
        ]);
    }

    public function test_approve_and_publish_news_post(): void
    {
        $post = NewsPost::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Parish Announcement',
            'slug' => 'parish-announcement',
            'content' => 'Full text...',
            'category' => 'announcement',
            'language' => 'en',
            'created_by' => $this->viennaAdmin->id,
            'status' => 'submitted',
            'submitted_by' => $this->viennaAdmin->id,
            'submitted_at' => now()
        ]);

        $approval = ContentApproval::create([
            'diocese_id' => $post->diocese_id,
            'church_id' => $post->church_id,
            'approvable_type' => NewsPost::class,
            'approvable_id' => $post->id,
            'approval_type' => 'news_publish',
            'requested_by' => $this->viennaAdmin->id,
            'requested_at' => now(),
            'status' => 'pending'
        ]);

        $approveResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/news/{$post->id}/approve");

        $approveResponse->assertStatus(200);

        $this->assertDatabaseHas('news_posts', [
            'id' => $post->id,
            'status' => 'approved'
        ]);

        $publishResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/news/{$post->id}/publish");

        $publishResponse->assertStatus(200);

        $this->assertDatabaseHas('news_posts', [
            'id' => $post->id,
            'status' => 'published'
        ]);
    }
}
