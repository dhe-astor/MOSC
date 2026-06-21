<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->member = User::factory()->create([
            'email' => 'regular@example.com',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_blocked_from_health_check(): void
    {
        $response = $this->getJson('/api/v1/system/health');
        $response->assertStatus(401);
    }

    public function test_non_admin_is_blocked_from_health_check(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/system/health');

        $response->assertStatus(403);
    }

    public function test_system_health_checks_return_proper_keys(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'database',
                'storage_writable',
                'cache',
                'queue',
                'scheduler',
                'mail_config',
                'disk_free_space',
                'timestamp'
            ]
        ]);
    }
}
