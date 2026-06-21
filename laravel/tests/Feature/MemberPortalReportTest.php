<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MemberPortalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
    }

    public function test_member_portal_usage_report(): void
    {
        // Seed portal access
        MemberPortalAccess::create([
            'diocese_id' => 1,
            'church_id' => 1,
            'user_id' => $this->superAdmin->id,
            'access_type' => 'member',
            'status' => 'active',
            'invited_at' => now(),
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'portal_usage'
            ]);

        $response->assertStatus(200);
        $usage = $response->json('data.data');
        $this->assertNotEmpty($usage);
        $this->assertEquals('Active', $usage[0]['Invite Status']);
    }
}
