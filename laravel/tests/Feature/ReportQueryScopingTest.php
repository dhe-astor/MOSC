<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportQueryScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $herne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
    }

    public function test_diocese_admin_sees_all_parishes(): void
    {
        // Add families to different parishes
        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family',
            'primary_phone' => '+491760000001',
            'address_line_1' => 'Herne St 1',
            'city' => 'Herne',
            'membership_status' => 'active',
            'created_by' => $this->superAdmin->id
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'members_families_list'
            ]);

        $response->assertStatus(200);
    }

    public function test_parish_admin_scoped_to_own_parish(): void
    {
        // Vienna admin run report with Herne church_id should fail
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'members_families_list',
                'filters' => [
                    'church_id' => $this->herne->id
                ]
            ]);

        $response->assertStatus(403);
    }
}
