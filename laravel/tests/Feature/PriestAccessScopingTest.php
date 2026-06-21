<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestAccessScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_priest_can_only_access_members_of_assigned_churches(): void
    {
        $priestUser = User::where('email', 'priest@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();
        $rome = Church::where('short_name', 'Rome')->first();

        // Ensure priest is assigned to Vienna but NOT Rome
        // First end other assignments to be clean
        PriestChurchAssignment::where('priest_profile_id', $priestUser->priest->id)->delete();
        
        PriestChurchAssignment::create([
            'diocese_id' => $vienna->diocese_id,
            'priest_profile_id' => $priestUser->priest->id,
            'member_id' => $priestUser->priest->member_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'vicar',
            'start_date' => '2026-06-01',
            'is_primary' => true,
            'status' => 'active'
        ]);

        // Create a member in Vienna
        $viennaMember = Member::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'first_name' => 'Vienna',
            'last_name' => 'Resident',
            'full_name' => 'Vienna Resident',
            'gender' => 'female',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $priestUser->id
        ]);

        // Create a member in Rome
        $superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $romeMember = Member::create([
            'diocese_id' => $rome->diocese_id,
            'church_id' => $rome->id,
            'first_name' => 'Rome',
            'last_name' => 'Resident',
            'full_name' => 'Rome Resident',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $superAdmin->id
        ]);

        // Priest lists members
        $response = $this->actingAs($priestUser, 'sanctum')->getJson('/api/v1/members');
        $response->assertStatus(200);

        $members = collect($response->json('data'));
        $memberIds = $members->pluck('id');

        // Should see Vienna member, should NOT see Rome member
        $this->assertTrue($memberIds->contains($viennaMember->id));
        $this->assertFalse($memberIds->contains($romeMember->id));
    }
}
