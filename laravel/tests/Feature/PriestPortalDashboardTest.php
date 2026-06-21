<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestPortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_priest_can_get_assigned_churches(): void
    {
        $priestUser = User::where('email', 'priest@msoc-europe.org')->first();

        $response = $this->actingAs($priestUser, 'sanctum')->getJson('/api/v1/priest/assigned-churches');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'short_name']
            ]
        ]);
    }

    public function test_priest_can_get_dashboard_data(): void
    {
        $priestUser = User::where('email', 'priest@msoc-europe.org')->first();

        $response = $this->actingAs($priestUser, 'sanctum')->getJson('/api/v1/priest/dashboard');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'profile',
                'active_assignments',
                'pending_certificates_count',
                'recent_payments'
            ]
        ]);
    }

    public function test_priest_can_switch_active_church(): void
    {
        $priestUser = User::where('email', 'priest@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();
        $herne = Church::where('short_name', 'Herne')->first();

        // Switch to Vienna
        $response = $this->actingAs($priestUser, 'sanctum')->postJson('/api/v1/priest/switch-church', [
            'church_id' => $vienna->id
        ]);
        $response->assertStatus(200);
        
        $priestUser->refresh();
        $this->assertEquals($vienna->id, $priestUser->active_church_id);

        // Switch to Herne
        $response2 = $this->actingAs($priestUser, 'sanctum')->postJson('/api/v1/priest/switch-church', [
            'church_id' => $herne->id
        ]);
        $response2->assertStatus(200);

        $priestUser->refresh();
        $this->assertEquals($herne->id, $priestUser->active_church_id);
    }
}
