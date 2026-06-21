<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryActivityTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $youthOrg;
    protected $youthUnit;

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
    }

    public function test_create_and_publish_activity(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-activities', [
                'ministry_unit_id' => $this->youthUnit->id,
                'title' => 'Monthly Prayer Meeting',
                'activity_type' => 'prayer',
                'start_datetime' => '2026-06-20 18:00:00',
                'timezone' => 'Europe/Vienna',
                'mode' => 'offline',
                'location_name' => 'Vienna Church Hall',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $activityId = $response->json('data.id');

        $publishResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/ministry-activities/{$activityId}/publish");

        $publishResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }
}
