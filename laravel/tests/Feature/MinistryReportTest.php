<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $coordinatorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_ministry_coordination_report(): void
    {
        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'Coordinator',
            'last_name' => 'User',
            'full_name' => 'Coordinator User',
            'gender' => 'male',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create coordinator user
        $this->coordinatorUser = User::create([
            'name' => 'Coordinator',
            'email' => 'coordinator@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->vienna->diocese_id,
            'default_church_id' => $this->vienna->id,
            'is_active' => true,
        ]);
        $this->coordinatorUser->assignRole('Youth Association Coordinator');

        \App\Models\UserChurchAccess::create([
            'user_id' => $this->coordinatorUser->id,
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'access_scope' => 'church_scoped',
            'status' => 'active'
        ]);

        $member->update(['user_id' => $this->coordinatorUser->id]);

        $org = MinistryOrganization::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'Youth Association',
            'slug' => 'youth-association',
            'organization_type' => 'youth_association',
            'code' => 'YOUTH',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $unit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $org->id,
            'unit_name' => 'Vienna Youth Unit',
            'unit_level' => 'parish',
            'coordinator_member_id' => $member->id,
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        MinistryMembership::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_unit_id' => $unit->id,
            'member_id' => $member->id,
            'family_id' => $family->id,
            'membership_type' => 'regular',
            'joined_date' => '2026-01-01',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Run report as coordinator
        $response = $this->actingAs($this->coordinatorUser, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'ministries_overview'
            ]);

        $response->assertStatus(200);
        $memberships = $response->json('data.data');
        $this->assertCount(1, $memberships);
        $this->assertEquals('Coordinator User', $memberships[0]['Member Name']);
    }
}
