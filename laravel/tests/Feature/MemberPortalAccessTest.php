<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $regularUser;
    protected $member;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        // Create a regular user who will get portal access
        $this->regularUser = User::create([
            'name' => 'John Member',
            'email' => 'john.member@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->viennaAdmin->default_diocese_id,
            'default_church_id' => $this->viennaAdmin->default_church_id,
        ]);

        $this->family = Family::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_code' => 'FAM-PORTAL-1',
            'family_name' => 'Portal Family',
            'primary_phone' => '+43660111222',
            'address_line_1' => 'Main St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family->id,
            'member_code' => 'MEM-PORTAL-1',
            'first_name' => 'John',
            'last_name' => 'Member',
            'full_name' => 'John Member',
            'email' => 'john.member@example.com',
            'phone' => '+43660111222',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_admin_can_invite_member_portal_access(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/member-portal/admin/access/invite', [
                'user_id' => $this->regularUser->id,
                'access_type' => 'member',
                'member_id' => $this->member->id
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('member_portal_access', [
            'user_id' => $this->regularUser->id,
            'member_id' => $this->member->id,
            'status' => 'invited'
        ]);
    }

    public function test_cannot_duplicate_active_invitation(): void
    {
        MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'user_id' => $this->regularUser->id,
            'access_type' => 'family_head',
            'status' => 'invited'
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/member-portal/admin/access/invite', [
                'user_id' => $this->regularUser->id,
                'access_type' => 'family_head',
                'family_id' => $this->family->id
            ]);

        $response->assertStatus(400); // Bad request because duplicate exists
    }

    public function test_suspend_portal_access_revokes_tokens(): void
    {
        $access = MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'user_id' => $this->regularUser->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        // Generate token for user
        $this->regularUser->createToken('test-token');
        $this->assertCount(1, $this->regularUser->tokens);

        $this->viennaAdmin->two_factor_enabled = true;
        $this->viennaAdmin->two_factor_last_verified_at = now();
        $this->viennaAdmin->save();

        \Laravel\Sanctum\Sanctum::actingAs($this->viennaAdmin, ['2fa_verified']);
        $response = $this->postJson("/api/v1/member-portal/admin/access/{$access->id}/suspend", [
            'reason' => 'Misbehavior'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('member_portal_access', [
            'id' => $access->id,
            'status' => 'suspended',
            'suspension_reason' => 'Misbehavior'
        ]);

        // Tokens should be cleared
        $this->regularUser->refresh();
        $this->assertCount(0, $this->regularUser->tokens);
    }

    public function test_get_portal_contexts(): void
    {
        MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'user_id' => $this->regularUser->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/member-portal/me');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.contexts')
            ->assertJsonPath('data.contexts.0.context_type', 'family_head');
    }
}
