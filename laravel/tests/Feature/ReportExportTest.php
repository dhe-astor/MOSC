<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ReportRun;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->superAdmin->two_factor_enabled = true;
        $this->superAdmin->two_factor_last_verified_at = now();
        $this->superAdmin->save();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        Storage::fake();
    }

    public function test_create_and_download_export(): void
    {
        // 1. Run report definition first
        $run = ReportRun::create([
            'diocese_id' => 1,
            'report_definition_id' => 1,
            'report_key' => 'diocese_overview',
            'status' => 'completed',
            'generated_by' => $this->superAdmin->id,
        ]);

        // 2. Request export file
        \Laravel\Sanctum\Sanctum::actingAs($this->superAdmin, ['2fa_verified']);
        $exportResponse = $this->postJson("/api/v1/reports/runs/{$run->id}/export", [
            'export_type' => 'csv'
        ]);

        $exportResponse->assertStatus(201);
        $exportId = $exportResponse->json('data.id');
        $this->assertNotNull($exportId);

        // 3. Download the export
        $downloadResponse = $this->get("/api/v1/reports/exports/{$exportId}/download");

        $downloadResponse->assertStatus(200);
    }

    public function test_expired_export_cannot_be_downloaded(): void
    {
        $run = ReportRun::create([
            'diocese_id' => 1,
            'report_definition_id' => 1,
            'report_key' => 'diocese_overview',
            'status' => 'completed',
            'generated_by' => $this->superAdmin->id,
        ]);

        $export = ReportExport::create([
            'diocese_id' => 1,
            'report_run_id' => $run->id,
            'export_type' => 'csv',
            'file_path' => 'private/report_exports/expired.csv',
            'file_name' => 'expired.csv',
            'status' => 'expired',
            'generated_by' => $this->superAdmin->id,
            'expires_at' => now()->subDay(),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->superAdmin, ['2fa_verified']);
        $response = $this->get("/api/v1/reports/exports/{$export->id}/download");

        $response->assertStatus(403);
    }
}
