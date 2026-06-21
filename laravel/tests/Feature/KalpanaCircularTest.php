<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\KalpanaCircular;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KalpanaCircularTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_create_and_update_circular(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/cms/kalpana-circulars', [
                'title' => 'Official Diocesan Circular 01/2026',
                'circular_type' => 'circular',
                'circular_date' => '2026-06-15',
                'reference_number' => 'MSOC-EU-CIR-2026-01',
                'content' => 'Diocese directives details...',
                'visibility' => 'clergy_only'
            ]);

        $response->assertStatus(210);
        $circularId = $response->json('data.id');

        $this->assertDatabaseHas('kalpana_circulars', [
            'id' => $circularId,
            'title' => 'Official Diocesan Circular 01/2026',
            'reference_number' => 'MSOC-EU-CIR-2026-01',
            'visibility' => 'clergy_only'
        ]);

        // Update visibility
        $updateResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/cms/kalpana-circulars/{$circularId}", [
                'title' => 'Official Diocesan Circular 01/2026 Updated',
                'circular_date' => '2026-06-15',
                'visibility' => 'public'
            ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('kalpana_circulars', [
            'id' => $circularId,
            'visibility' => 'public'
        ]);
    }
}
