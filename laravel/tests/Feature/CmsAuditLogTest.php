<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Services\WebsitePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAuditLogTest extends TestCase
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

    public function test_audit_logs_for_cms_actions(): void
    {
        // Act as super admin and perform creation
        $this->actingAs($this->superAdmin, 'sanctum');

        $page = WebsitePageService::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Audited Page',
            'page_type' => 'custom',
            'content' => 'Content text...',
            'visibility' => 'public',
            'status' => 'draft'
        ], $this->superAdmin);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'CMS',
            'action' => 'Create Page',
            'auditable_type' => WebsitePage::class,
            'auditable_id' => $page->id
        ]);
    }
}
