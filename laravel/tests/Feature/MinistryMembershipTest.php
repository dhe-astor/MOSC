<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\Diocese;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $youthOrg;
    protected $viennaUnit;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();

        $this->viennaUnit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $this->youthOrg->id,
            'unit_name' => 'Vienna Youth',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);

        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Test Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '2000-06-15', // Age 26
            'created_by' => $this->viennaAdmin->id,
        ]);
    }

    public function test_enroll_member_pending_and_approve(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-memberships', [
                'ministry_unit_id' => $this->viennaUnit->id,
                'member_id' => $this->member->id,
                'joined_date' => '2026-06-15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $membershipId = $response->json('data.id');

        $approveResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/ministry-memberships/{$membershipId}/approve");

        $approveResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_prevent_duplicate_active_enrollment(): void
    {
        MinistryMembership::create([
            'diocese_id' => $this->viennaUnit->diocese_id,
            'church_id' => $this->viennaUnit->church_id,
            'ministry_unit_id' => $this->viennaUnit->id,
            'member_id' => $this->member->id,
            'joined_date' => '2026-06-15',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-memberships', [
                'ministry_unit_id' => $this->viennaUnit->id,
                'member_id' => $this->member->id,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Member is already enrolled (active or pending) in this unit.']);
    }
}
