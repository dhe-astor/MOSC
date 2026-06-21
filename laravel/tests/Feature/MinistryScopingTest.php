<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $vienna;
    protected $herne;
    protected $youthOrg;
    protected $viennaUnit;
    protected $herneUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
        
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();

        $this->viennaUnit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $this->youthOrg->id,
            'unit_name' => 'Vienna Youth Unit',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->herneUnit = MinistryUnit::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'ministry_organization_id' => $this->youthOrg->id,
            'unit_name' => 'Herne Youth Unit',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_vienna_admin_cannot_view_herne_unit(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/ministry-units/{$this->herneUnit->id}");

        $response->assertStatus(403);
    }

    public function test_vienna_admin_can_view_vienna_unit(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/ministry-units/{$this->viennaUnit->id}");

        $response->assertStatus(200);
    }

    public function test_diocese_admin_can_view_all_units(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/ministry-units/{$this->herneUnit->id}");

        $response->assertStatus(200);

        $response2 = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/ministry-units/{$this->viennaUnit->id}");

        $response2->assertStatus(200);
    }
}
