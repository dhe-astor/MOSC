<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $herneAdmin;
    protected $priest;
    protected $vienna;
    protected $herne;
    protected $munich;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
        $this->munich = Church::where('short_name', 'Munich')->first();
    }

    public function test_parish_admin_can_create_family_in_own_parish(): void
    {
        $country = Country::first();

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/families', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'family_name' => 'Mathew Family',
                'primary_phone' => '+436640001111',
                'address_line_1' => 'Hauptstrasse 45',
                'city' => 'Vienna',
                'postal_code' => '1010',
                'country_id' => $country?->id,
                'preferred_language' => 'en',
                'gdpr_consent' => true,
                'communication_consent' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.family_name', 'Mathew Family')
            ->assertJsonPath('data.membership_status', 'pending');

        $this->assertDatabaseHas('families', [
            'family_name' => 'Mathew Family',
            'church_id' => $this->vienna->id,
            'membership_status' => 'pending'
        ]);
    }

    public function test_parish_admin_cannot_create_family_in_another_parish(): void
    {
        $country = Country::first();

        // Vienna admin tries to create family in Herne
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/families', [
                'diocese_id' => $this->herne->diocese_id,
                'church_id' => $this->herne->id,
                'family_name' => 'Unauthorized Family',
                'primary_phone' => '+491760000000',
                'address_line_1' => 'Some St 2',
                'city' => 'Herne',
                'country_id' => $country?->id,
                'preferred_language' => 'en',
                'gdpr_consent' => true,
                'communication_consent' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_scoping_families_list_for_parish_admin(): void
    {
        // Create a family in Vienna
        $viennaFamily = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Local Family',
            'primary_phone' => '+436649999999',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create a family in Herne
        $herneFamily = Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Local Family',
            'primary_phone' => '+491769999999',
            'address_line_1' => 'Herne St 1',
            'city' => 'Herne',
            'membership_status' => 'pending',
            'created_by' => $this->herneAdmin->id
        ]);

        // Vienna Admin lists families - should only see Vienna
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')->getJson('/api/v1/families');
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $names = collect($data)->pluck('family_name');
        $this->assertTrue($names->contains('Vienna Local Family'));
        $this->assertFalse($names->contains('Herne Local Family'));
    }

    public function test_priest_can_approve_family_in_assigned_parish(): void
    {
        // Create pending family in Vienna
        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Pending Vienna Family',
            'primary_phone' => '+436640987654',
            'address_line_1' => 'Vicarage Rd 1',
            'city' => 'Vienna',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Priest approves Vienna family (since they are assigned to Vienna)
        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/families/{$family->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals('active', $family->refresh()->membership_status);
        $this->assertNotNull($family->family_code);
        $this->assertStringStartsWith('MSOC-', $family->family_code);

        // Verify church history record was created
        $this->assertDatabaseHas('family_church_history', [
            'family_id' => $family->id,
            'church_id' => $this->vienna->id,
            'status' => 'active'
        ]);
    }

    public function test_priest_cannot_approve_family_in_unassigned_parish(): void
    {
        // Munich is not assigned to the priest
        $family = Family::create([
            'diocese_id' => $this->munich->diocese_id,
            'church_id' => $this->munich->id,
            'family_name' => 'Munich Family',
            'primary_phone' => '+4989000000',
            'address_line_1' => 'Munich Rd 12',
            'city' => 'Munich',
            'membership_status' => 'pending',
            'created_by' => User::where('email', 'admin@msoc-europe.org')->first()->id
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/families/{$family->id}/approve");

        $response->assertStatus(403);
    }
}
