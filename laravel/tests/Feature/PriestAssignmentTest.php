<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_diocese_admin_can_assign_priest_to_church(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/assignments", [
            'priest_profile_id' => $priest->id,
            'church_id' => $rome->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2026-06-01',
            'is_primary' => false,
            'notes' => 'Assigned to Rome as visiting priest'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('priest_church_assignments', [
            'priest_profile_id' => $priest->id,
            'church_id' => $rome->id,
            'assignment_role' => 'visiting_priest',
            'status' => 'active'
        ]);
    }

    public function test_parish_admin_cannot_assign_priest(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($viennaAdmin, 'sanctum')->postJson("/api/v1/clergy/assignments", [
            'priest_profile_id' => $priest->id,
            'church_id' => $rome->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2026-06-01'
        ]);

        $response->assertStatus(403);
    }

    public function test_only_one_active_primary_vicar_per_parish(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        // Let's create another priest
        $newMember = Member::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'first_name' => 'Thomas',
            'last_name' => 'Koshy',
            'full_name' => 'Thomas Koshy',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $admin->id
        ]);

        $newPriest = PriestProfile::create([
            'diocese_id' => $vienna->diocese_id,
            'member_id' => $newMember->id,
            'display_name' => 'Thomas Koshy',
            'clergy_type' => 'priest',
            'phone_public' => '+43 664 9999999',
            'status' => 'active'
        ]);

        // Attempting to assign Fr. Thomas as primary vicar to Vienna while Fr. Jacob Mathew is already one
        // should fail and return 400.
        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/assignments", [
            'priest_profile_id' => $newPriest->id,
            'church_id' => $vienna->id,
            'assignment_role' => 'vicar',
            'start_date' => '2026-06-15',
            'is_primary' => true
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Church already has an active primary Vicar.']);
    }

    public function test_ending_assignment_preserves_history(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        
        $assignment = PriestChurchAssignment::first(); // Vienna assignment

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/assignments/{$assignment->id}/end", [
            'end_date' => '2026-06-15',
            'end_reason' => 'Transferred'
        ]);

        $response->assertStatus(200);

        // Verify the status is now ended and end date is set
        $updated = PriestChurchAssignment::find($assignment->id);
        $this->assertEquals('ended', $updated->status);
        $this->assertEquals('2026-06-15', $updated->end_date->toDateString());
        $this->assertEquals('Transferred', $updated->end_reason);
    }
}
