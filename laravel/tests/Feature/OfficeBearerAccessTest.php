<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberResponsibilityAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeBearerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_parish_admin_can_only_view_own_parish_office_bearers(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();
        $herne = Church::where('short_name', 'Herne')->first();

        // Create an office bearer in Vienna
        $viennaMember = Member::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'first_name' => 'Vienna',
            'last_name' => 'Bearer',
            'full_name' => 'Vienna Bearer',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $viennaAdmin->id
        ]);

        MemberResponsibilityAssignment::create([
            'diocese_id' => $vienna->diocese_id,
            'church_id' => $vienna->id,
            'member_id' => $viennaMember->id,
            'responsibility_type' => 'parish_office',
            'designation' => 'secretary',
            'start_date' => '2026-06-01',
            'status' => 'active',
            'assigned_by' => $viennaAdmin->id
        ]);

        // 1. Can view own parish office bearers
        $response1 = $this->actingAs($viennaAdmin, 'sanctum')->getJson("/api/v1/clergy/churches/{$vienna->id}/office-bearers");
        $response1->assertStatus(200);
        $this->assertNotEmpty($response1->json('data'));

        // 2. Cannot view another parish office bearers
        $response2 = $this->actingAs($viennaAdmin, 'sanctum')->getJson("/api/v1/clergy/churches/{$herne->id}/office-bearers");
        $response2->assertStatus(403);
    }
}
