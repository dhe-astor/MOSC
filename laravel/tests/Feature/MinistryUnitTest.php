<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Diocese;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryUnitTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $diocese;
    protected $vienna;
    protected $herne;
    protected $youthOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
        $this->diocese = Diocese::first();
        
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();
    }

    public function test_vienna_admin_can_create_vienna_unit(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-units', [
                'diocese_id' => $this->diocese->id,
                'church_id' => $this->vienna->id,
                'ministry_organization_id' => $this->youthOrg->id,
                'unit_name' => 'Vienna Youth Unit',
                'unit_level' => 'parish',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.unit_name', 'Vienna Youth Unit');
    }

    public function test_vienna_admin_cannot_create_herne_unit(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-units', [
                'diocese_id' => $this->diocese->id,
                'church_id' => $this->herne->id,
                'ministry_organization_id' => $this->youthOrg->id,
                'unit_name' => 'Herne Youth Unit',
                'unit_level' => 'parish',
            ]);

        $response->assertStatus(403);
    }
}
