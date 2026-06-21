<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $youthOrg;
    protected $samajamOrg;
    protected $youthUnit;
    protected $samajamUnit;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();
        $this->samajamOrg = MinistryOrganization::where('slug', 'msoc-europe-marthamariyam-samajam')->first();

        $this->youthUnit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $this->youthOrg->id,
            'unit_name' => 'Vienna Youth',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->samajamUnit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $this->samajamOrg->id,
            'unit_name' => 'Vienna Samajam',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->family = Family::create([
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
    }

    public function test_youth_age_eligibility_underage(): void
    {
        // 10 years old
        $underageMember = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Kid',
            'last_name' => 'One',
            'full_name' => 'Kid One',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '2016-06-15',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-memberships', [
                'ministry_unit_id' => $this->youthUnit->id,
                'member_id' => $underageMember->id,
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('is less than the minimum required age of 15.', $response->json('message'));
    }

    public function test_samajam_gender_eligibility_fails_for_male(): void
    {
        $maleMember = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Adult',
            'last_name' => 'Male',
            'full_name' => 'Adult Male',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'approved_at' => now(),
            'gender' => 'male',
            'date_of_birth' => '1990-06-15',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-memberships', [
                'ministry_unit_id' => $this->samajamUnit->id,
                'member_id' => $maleMember->id,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Member gender (male) does not match the organization requirement (female).']);
    }

    public function test_eligibility_override_authorized(): void
    {
        $underageMember = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Kid',
            'last_name' => 'One',
            'full_name' => 'Kid One',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '2016-06-15',
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Diocese Admin override
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-memberships', [
                'ministry_unit_id' => $this->youthUnit->id,
                'member_id' => $underageMember->id,
                'override_eligibility' => true,
            ]);

        $response->assertStatus(201);
    }
}
