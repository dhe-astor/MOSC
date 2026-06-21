<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteImportSource;
use App\Models\WebsiteImportRun;
use App\Models\WebsiteImportRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteClergyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_diocese_admin_can_run_import(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        // Create a source url
        $source = WebsiteImportSource::create([
            'diocese_id' => $admin->default_diocese_id,
            'source_type' => 'priests',
            'source_url' => 'https://msoc-europe.com/priests.php',
            'status' => 'active'
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/import-sources/{$source->id}/fetch");
        $response->assertStatus(200);

        // Verify import run record created
        $this->assertDatabaseHas('website_import_runs', [
            'source_id' => $source->id,
            'status' => 'review_required'
        ]);

        // Verify some parsed priest import records were created
        $run = WebsiteImportRun::where('source_id', $source->id)->first();
        $this->assertGreaterThan(0, WebsiteImportRecord::where('import_run_id', $run->id)->count());
    }
}
