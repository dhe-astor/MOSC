<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\UserChurchAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_can_assign_church_access(): void
    {
        $super = User::where('email', 'superadmin@msoc-europe.org')->first();
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($super, 'sanctum')->postJson("/api/v1/users/{$user->id}/access", [
            'diocese_id' => $rome->diocese_id,
            'church_id' => $rome->id,
            'access_scope' => 'church_specific',
            'starts_at' => '2026-06-01'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_church_access', [
            'user_id' => $user->id,
            'church_id' => $rome->id,
            'status' => 'active'
        ]);
    }

    public function test_parish_admin_cannot_assign_church_access(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $user = User::where('email', 'priest@msoc-europe.org')->first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($viennaAdmin, 'sanctum')->postJson("/api/v1/users/{$user->id}/access", [
            'diocese_id' => $rome->diocese_id,
            'church_id' => $rome->id,
            'access_scope' => 'church_specific'
        ]);

        $response->assertStatus(403);
    }

    public function test_inactive_access_mapping_blocks_church_access(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        // Deactivate access to Vienna
        $access = UserChurchAccess::where('user_id', $user->id)->first();
        $access->status = 'inactive';
        $access->save();

        // Vienna Parish Admin tries to fetch Vienna details
        $vienna = Church::where('short_name', 'Vienna')->first();
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/churches/{$vienna->id}");

        $response->assertStatus(403);
    }

    public function test_expired_access_mapping_blocks_church_access(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        // Expire access to Vienna (ends yesterday)
        $access = UserChurchAccess::where('user_id', $user->id)->first();
        $access->ends_at = Carbon::yesterday();
        $access->save();

        // Vienna Parish Admin tries to fetch Vienna details
        $vienna = Church::where('short_name', 'Vienna')->first();
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/churches/{$vienna->id}");

        $response->assertStatus(403);
    }
}
