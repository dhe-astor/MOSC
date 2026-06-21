<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ReportDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDefinitionTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
    }

    public function test_list_definitions_for_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/reports/definitions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'report_key',
                        'name',
                        'report_category',
                    ]
                ]
            ]);
    }

    public function test_definition_uniqueness_per_diocese(): void
    {
        // Add definition for same key but different diocese
        $def1 = ReportDefinition::create([
            'diocese_id' => 1,
            'report_key' => 'custom_rep',
            'name' => 'Custom Report 1',
            'report_category' => 'custom',
            'status' => 'active'
        ]);

        $this->assertTrue($def1->exists);

        // Different diocese_id should be allowed
        $def2 = ReportDefinition::create([
            'diocese_id' => null,
            'report_key' => 'custom_rep',
            'name' => 'Custom Report 2',
            'report_category' => 'custom',
            'status' => 'active'
        ]);

        $this->assertTrue($def2->exists);
    }
}
