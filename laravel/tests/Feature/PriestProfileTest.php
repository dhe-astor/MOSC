<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\PriestProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_diocese_admin_can_create_priest_profile(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        $member = Member::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'first_name' => 'John',
            'last_name' => 'Jacob',
            'full_name' => 'John Jacob',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $admin->id
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/clergy/priests', [
            'diocese_id' => $vienna->diocese_id,
            'member_id' => $member->id,
            'display_name' => 'Rev. Fr. John Jacob',
            'ordination_name' => 'John Jacob',
            'clergy_type' => 'priest',
            'status' => 'active'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('priest_profiles', [
            'member_id' => $member->id,
            'display_name' => 'Rev. Fr. John Jacob',
            'clergy_type' => 'priest'
        ]);
    }

    public function test_can_create_priest_login(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        $member = Member::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'first_name' => 'Test',
            'last_name' => 'Priest',
            'full_name' => 'Test Priest',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $admin->id
        ]);

        $priest = PriestProfile::create([
            'diocese_id' => $vienna->diocese_id,
            'member_id' => $member->id,
            'display_name' => 'Rev. Fr. Test Priest',
            'clergy_type' => 'priest',
            'status' => 'active'
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/priests/{$priest->id}/create-login", [
            'email' => 'newpriest@msoc-europe.org',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'email' => 'newpriest@msoc-europe.org',
            'name' => 'Rev. Fr. Test Priest'
        ]);

        $priest->refresh();
        $this->assertNotNull($priest->user_id);
    }
}
