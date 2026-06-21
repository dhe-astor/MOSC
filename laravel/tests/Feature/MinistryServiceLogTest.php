<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryServiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryServiceLogTest extends TestCase
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

    public function test_submit_and_verify_service_log(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-service-logs', [
                'ministry_unit_id' => $this->youthUnit->id,
                'member_id' => $this->member->id,
                'service_type' => 'charity',
                'service_date' => '2026-06-15',
                'hours_count' => 3.5,
                'description' => 'Helping with charity food distribution',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted');

        $logId = $response->json('data.id');

        $verifyResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/ministry-service-logs/{$logId}/verify");

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'verified');
    }
}
