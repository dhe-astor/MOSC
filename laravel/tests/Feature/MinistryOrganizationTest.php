<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Diocese;
use App\Models\MinistryOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vicar;
    protected $diocese;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vicar = User::where('email', 'priest@msoc-europe.org')->first();
        $this->diocese = Diocese::first();
    }

    public function test_list_ministry_organizations(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/ministry-organizations');

        $response->assertStatus(200)
            ->assertJsonFragment(['slug' => 'msoc-europe-youth-association'])
            ->assertJsonFragment(['slug' => 'msoc-europe-marthamariyam-samajam']);
    }

    public function test_store_ministry_organization_authorized(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-organizations', [
                'diocese_id' => $this->diocese->id,
                'name' => 'Custom Ministry Association',
                'slug' => 'custom-ministry-association',
                'organization_type' => 'other',
                'description' => 'A custom spiritual association',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'custom-ministry-association');
    }

    public function test_store_ministry_organization_unauthorized(): void
    {
        $response = $this->actingAs($this->vicar, 'sanctum')
            ->postJson('/api/v1/ministry-organizations', [
                'diocese_id' => $this->diocese->id,
                'name' => 'Should Fail',
                'slug' => 'should-fail',
                'organization_type' => 'other',
            ]);

        $response->assertStatus(403);
    }
}
