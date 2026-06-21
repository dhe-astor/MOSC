<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Models\ContentApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitePageTest extends TestCase
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

    public function test_create_and_submit_website_page(): void
    {
        // 1. Create draft website page
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/cms/pages', [
                'title' => 'Vienna Feast Page',
                'church_id' => $this->vienna->id,
                'page_type' => 'custom',
                'content' => 'Content details',
                'excerpt' => 'Excerpt details',
                'visibility' => 'public'
            ]);

        $response->assertStatus(210);
        $pageId = $response->json('data.id');

        $this->assertDatabaseHas('website_pages', [
            'id' => $pageId,
            'status' => 'draft',
            'title' => 'Vienna Feast Page'
        ]);

        // 2. Submit page for approval
        $submitResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/cms/pages/{$pageId}/submit", [
                'remarks' => 'Please review'
            ]);

        $submitResponse->assertStatus(200);

        $this->assertDatabaseHas('website_pages', [
            'id' => $pageId,
            'status' => 'submitted'
        ]);

        $this->assertDatabaseHas('content_approvals', [
            'approvable_type' => WebsitePage::class,
            'approvable_id' => $pageId,
            'status' => 'pending'
        ]);
    }

    public function test_approve_and_publish_website_page(): void
    {
        $page = WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'About Vienna Parish',
            'slug' => 'about-vienna-parish',
            'page_type' => 'about',
            'content' => 'Details',
            'created_by' => $this->viennaAdmin->id,
            'status' => 'submitted',
            'submitted_by' => $this->viennaAdmin->id,
            'submitted_at' => now()
        ]);

        $approval = ContentApproval::create([
            'diocese_id' => $page->diocese_id,
            'church_id' => $page->church_id,
            'approvable_type' => WebsitePage::class,
            'approvable_id' => $page->id,
            'approval_type' => 'page_publish',
            'requested_by' => $this->viennaAdmin->id,
            'requested_at' => now(),
            'status' => 'pending'
        ]);

        // Approve page
        $approveResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/pages/{$page->id}/approve", [
                'remarks' => 'Approved'
            ]);

        $approveResponse->assertStatus(200);

        $this->assertDatabaseHas('website_pages', [
            'id' => $page->id,
            'status' => 'approved'
        ]);

        // Publish page
        $publishResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/pages/{$page->id}/publish");

        $publishResponse->assertStatus(200);

        $this->assertDatabaseHas('website_pages', [
            'id' => $page->id,
            'status' => 'published'
        ]);
    }

    public function test_reject_website_page_with_reason(): void
    {
        $page = WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Vienna Bad Page',
            'slug' => 'vienna-bad-page',
            'page_type' => 'custom',
            'created_by' => $this->viennaAdmin->id,
            'status' => 'submitted',
            'submitted_by' => $this->viennaAdmin->id,
            'submitted_at' => now()
        ]);

        $approval = ContentApproval::create([
            'diocese_id' => $page->diocese_id,
            'church_id' => $page->church_id,
            'approvable_type' => WebsitePage::class,
            'approvable_id' => $page->id,
            'approval_type' => 'page_publish',
            'requested_by' => $this->viennaAdmin->id,
            'requested_at' => now(),
            'status' => 'pending'
        ]);

        // Reject page (fails if rejection_reason missing)
        $rejectFail = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/pages/{$page->id}/reject");
        $rejectFail->assertStatus(422);

        // Reject page successfully
        $rejectSuccess = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/pages/{$page->id}/reject", [
                'rejection_reason' => 'Grammar issues'
            ]);
        $rejectSuccess->assertStatus(200);

        $this->assertDatabaseHas('website_pages', [
            'id' => $page->id,
            'status' => 'rejected',
            'rejection_reason' => 'Grammar issues'
        ]);
    }
}
