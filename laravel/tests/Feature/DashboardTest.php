<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Priest;
use App\Models\PriestAssignment;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberChangeRequest;
use App\Models\FamilyTransferRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $priestUser;
    protected $vienna;
    protected $herne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->priestUser = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
    }

    public function test_diocese_dashboard_stats(): void
    {
        // Create 2 families in Vienna, 1 in Herne
        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family 1',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family 2 (Pending)',
            'primary_phone' => '+436640000002',
            'address_line_1' => 'Street 2',
            'city' => 'Vienna',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family 1',
            'primary_phone' => '+491760000001',
            'address_line_1' => 'Street 1',
            'city' => 'Herne',
            'membership_status' => 'active',
            'created_by' => $this->superAdmin->id
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/dashboard?type=diocese');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_families', 3)
            ->assertJsonPath('data.pending_families_count', 1);
    }

    public function test_parish_dashboard_stats_is_scoped(): void
    {
        // Create 1 family in Vienna, 2 in Herne
        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family 1',
            'primary_phone' => '+491760000001',
            'address_line_1' => 'Street 1',
            'city' => 'Herne',
            'membership_status' => 'active',
            'created_by' => $this->superAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family 2',
            'primary_phone' => '+491760000002',
            'address_line_1' => 'Street 2',
            'city' => 'Herne',
            'membership_status' => 'pending',
            'created_by' => $this->superAdmin->id
        ]);

        // Access via Vienna Admin (Vienna parish)
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/dashboard?type=parish');

        $response->assertStatus(200)
            ->assertJsonPath('data.church.id', $this->vienna->id)
            ->assertJsonPath('data.total_families', 1)
            ->assertJsonPath('data.pending_families_count', 0);
    }

    public function test_priest_dashboard_stats_is_scoped(): void
    {
        // Clear existing assignments to avoid unique constraint violations
        PriestAssignment::query()->delete();

        // Get the priest model associated with the priest user
        $priest = Priest::where('email', $this->priestUser->email)->first();

        // Assign Priest to Vienna
        PriestAssignment::create([
            'priest_id' => $priest->id,
            'church_id' => $this->vienna->id,
            'role' => 'vicar',
            'assignment_start_date' => now()->subDays(10),
            'is_primary' => true,
            'status' => 'active'
        ]);

        // Create 2 families in Vienna, 1 in Herne
        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family 1',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family 2',
            'primary_phone' => '+436640000002',
            'address_line_1' => 'Street 2',
            'city' => 'Vienna',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);

        Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family',
            'primary_phone' => '+491760000001',
            'address_line_1' => 'Street 1',
            'city' => 'Herne',
            'membership_status' => 'active',
            'created_by' => $this->superAdmin->id
        ]);

        // Access via Priest
        $response = $this->actingAs($this->priestUser, 'sanctum')
            ->getJson('/api/v1/dashboard?type=priest');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_families', 2)
            ->assertJsonPath('data.pending_families_count', 1);
    }
}
