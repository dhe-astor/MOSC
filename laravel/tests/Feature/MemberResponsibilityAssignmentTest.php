<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Church;
use App\Models\MemberResponsibilityAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberResponsibilityAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_diocese_admin_can_manage_responsibilities(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();
        $member = Member::first();

        // Create assignment
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/clergy/responsibilities', [
            'church_id' => $vienna->id,
            'member_id' => $member->id,
            'responsibility_type' => 'parish_office',
            'designation' => 'secretary',
            'start_date' => '2026-06-01',
            'is_primary' => true
        ]);

        $response->assertStatus(201);
        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('member_responsibility_assignments', [
            'id' => $assignmentId,
            'designation' => 'secretary',
            'status' => 'active'
        ]);

        // End responsibility
        $endResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/responsibilities/{$assignmentId}/end", [
            'end_date' => '2026-06-15'
        ]);
        $endResponse->assertStatus(200);

        $this->assertDatabaseHas('member_responsibility_assignments', [
            'id' => $assignmentId,
            'status' => 'ended'
        ]);
    }
}
