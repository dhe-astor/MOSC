<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_can_list_all_churches(): void
    {
        $user = User::where('email', 'superadmin@msoc-europe.org')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/churches');

        $response->assertStatus(200);
        
        // Should fetch all 25 seeded records (24 active + 1 upcoming)
        $this->assertEquals(25, count($response->json('data')));
    }

    public function test_diocese_admin_can_list_all_churches(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/churches');

        $response->assertStatus(200);
        $this->assertEquals(25, count($response->json('data')));
    }

    public function test_parish_admin_can_list_only_assigned_church(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/churches');

        $response->assertStatus(200);
        
        // Should list only St. Mary's Vienna
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('Vienna', $response->json('data.0.short_name'));
    }

    public function test_parish_admin_cannot_view_another_church_by_id(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        // Find another church, e.g. Rome or Herne
        $otherChurch = Church::where('short_name', 'Herne')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/churches/{$otherChurch->id}");

        $response->assertStatus(403);
    }

    public function test_priest_can_list_assigned_churches_only(): void
    {
        $user = User::where('email', 'priest@msoc-europe.org')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/churches');

        $response->assertStatus(200);
        
        // Assigned to Vienna and Herne
        $this->assertEquals(2, count($response->json('data')));
        
        $shorts = collect($response->json('data'))->pluck('short_name');
        $this->assertTrue($shorts->contains('Vienna'));
        $this->assertTrue($shorts->contains('Herne'));
    }

    public function test_user_cannot_set_active_church_to_unauthorized_church(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $otherChurch = Church::where('short_name', 'Herne')->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/active-church', [
            'church_id' => $otherChurch->id
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this church'
            ]);
    }
}
