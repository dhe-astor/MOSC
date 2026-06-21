<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Models\ContentApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApprovalTest extends TestCase
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

    public function test_list_approvals_queue(): void
    {
        $page = WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Page 1',
            'slug' => 'page-1',
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

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/cms/approvals');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_process_decision(): void
    {
        $page = WebsitePage::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Page 2',
            'slug' => 'page-2',
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

        // Process rejection decision
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/cms/approvals/{$approval->id}/decision", [
                'action' => 'reject',
                'rejection_reason' => 'Poor formatting'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('content_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
            'rejection_reason' => 'Poor formatting'
        ]);

        $this->assertDatabaseHas('website_pages', [
            'id' => $page->id,
            'status' => 'rejected',
            'rejection_reason' => 'Poor formatting'
        ]);
    }
}
