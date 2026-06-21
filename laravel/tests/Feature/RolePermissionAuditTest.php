<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionAuditTest extends TestCase
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

    public function test_authorized_user_can_access_role_permission_audit(): void
    {
        // Must authenticate and simulate 2FA verification since it's sensitive
        \Laravel\Sanctum\Sanctum::actingAs($this->admin, ['2fa_verified']);
        $response = $this->getJson('/api/v1/system/role-permission-audit');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'role_id',
                    'name',
                    'permissions_count',
                    'permissions'
                ]
            ]
        ]);
    }

    public function test_unauthorized_user_is_blocked_from_role_permission_audit(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/system/role-permission-audit');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_sensitive_permissions(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin, ['2fa_verified']);
        $response = $this->getJson('/api/v1/security/sensitive-permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'permission',
                    'users_count',
                    'users'
                ]
            ]
        ]);
    }
}
