<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditReportTest extends TestCase
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

    public function test_audit_report_permission_denied(): void
    {
        // Vienna admin lacks view_audit_reports permission
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'audit_logs'
            ]);

        $response->assertStatus(403);
    }

    public function test_audit_report_success(): void
    {
        // Write audit log
        AuditLogService::log(
            'Members',
            'Member Created',
            'Created new member record',
            null,
            ['name' => 'John Doe'],
            null,
            1,
            1
        );

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'audit_logs'
            ]);

        $response->assertStatus(200);
        $logs = $response->json('data.data');
        $this->assertNotEmpty($logs);
        $actions = collect($logs)->pluck('Action')->toArray();
        $this->assertContains('Member Created', $actions);
    }
}
