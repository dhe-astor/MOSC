<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryOfficeBearer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryOfficeBearerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $youthOrg;
    protected $youthUnit;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();

        $this->youthUnit = MinistryUnit::create([
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
            'date_of_birth' => '2000-06-15',
            'created_by' => $this->viennaAdmin->id,
        ]);
    }

    public function test_assign_office_bearer(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-office-bearers', [
                'ministry_unit_id' => $this->youthUnit->id,
                'member_id' => $this->member->id,
                'role_title' => 'Parish Secretary',
                'role_category' => 'secretary',
                'start_date' => '2026-06-15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
        
        // Assert unit is updated
        $this->assertEquals($this->member->id, $this->youthUnit->fresh()->secretary_member_id);
    }

    public function test_end_office_bearer_term_preserves_history(): void
    {
        $bearer = MinistryOfficeBearer::create([
            'ministry_unit_id' => $this->youthUnit->id,
            'member_id' => $this->member->id,
            'role_title' => 'Parish Secretary',
            'role_category' => 'secretary',
            'start_date' => '2026-01-01',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/ministry-office-bearers/{$bearer->id}/end-term", [
                'end_date' => '2026-06-15',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ended');
        $this->assertStringStartsWith('2026-06-15', $response->json('data.end_date'));

        // Old bearer must still exist in DB
        $this->assertDatabaseHas('ministry_office_bearers', [
            'id' => $bearer->id,
            'status' => 'ended',
        ]);
    }
}
