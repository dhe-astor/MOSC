<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $member;
    protected $export;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed();

        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->admin->two_factor_enabled = true;
        $this->admin->two_factor_last_verified_at = now();
        $this->admin->save();

        $this->member = User::factory()->create([
            'email' => 'member@example.com',
            'is_active' => true,
        ]);

        $run = \App\Models\ReportRun::create([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'report_key' => 'members_list',
            'status' => 'completed',
            'row_count' => 10,
            'generated_by' => $this->admin->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->export = ReportExport::create([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'report_run_id' => $run->id,
            'file_name' => 'members.csv',
            'file_path' => 'private/report_exports/members.csv',
            'export_type' => 'csv',
            'status' => 'generated',
            'generated_by' => $this->admin->id,
            'expires_at' => now()->addDays(7),
        ]);
        Storage::put('private/report_exports/members.csv', 'name,email\nAdmin,admin@example.com');
    }

    public function test_guest_is_blocked_from_private_downloads(): void
    {
        $response = $this->getJson("/api/v1/reports/exports/{$this->export->id}/download");
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_is_blocked_from_private_downloads(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson("/api/v1/reports/exports/{$this->export->id}/download");

        $response->assertStatus(403);
    }

    public function test_authorized_user_with_2fa_verified_can_download_and_logs_audit(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin, ['2fa_verified']);
        $response = $this->getJson("/api/v1/reports/exports/{$this->export->id}/download");

        $response->assertStatus(200);
        
        // Assert download audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'module' => 'Reports',
            'action' => 'Report Export Downloaded'
        ]);
    }
}
