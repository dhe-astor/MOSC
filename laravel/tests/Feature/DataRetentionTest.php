<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ReportExport;
use App\Models\NotificationDelivery;
use App\Models\MemberPortalActivityLog;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed();

        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
    }
    public function test_retention_cleanup_command_executes_successfully(): void
    {
        $run = \App\Models\ReportRun::create([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'report_key' => 'members_list',
            'status' => 'completed',
            'row_count' => 10,
            'generated_by' => $this->admin->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        // 1. Create expired and non-expired Report Exports
        $expiredExport = ReportExport::create([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'report_run_id' => $run->id,
            'file_name' => 'expired_report.csv',
            'file_path' => 'private/report_exports/expired_report.csv',
            'export_type' => 'csv',
            'status' => 'generated',
            'generated_by' => $this->admin->id,
            'expires_at' => now()->subDays(1),
        ]);
        Storage::put('private/report_exports/expired_report.csv', 'dummy,data');

        $activeExport = ReportExport::create([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'report_run_id' => $run->id,
            'file_name' => 'active_report.csv',
            'file_path' => 'private/report_exports/active_report.csv',
            'export_type' => 'csv',
            'status' => 'generated',
            'generated_by' => $this->admin->id,
            'expires_at' => now()->addDays(5),
        ]);
        Storage::put('private/report_exports/active_report.csv', 'dummy,data');

        // 2. Create expired logs using raw DB insert to bypass Eloquent timestamp override
        $expiredLogId = \Illuminate\Support\Facades\DB::table('member_portal_activity_logs')->insertGetId([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla',
            'created_at' => now()->subYears(4),
            'updated_at' => now()->subYears(4),
        ]);
        $expiredPortalLog = MemberPortalActivityLog::find($expiredLogId);

        $activeLogId = \Illuminate\Support\Facades\DB::table('member_portal_activity_logs')->insertGetId([
            'diocese_id' => $this->admin->default_diocese_id ?: 1,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $activePortalLog = MemberPortalActivityLog::find($activeLogId);

        // 3. Execute cleanup command
        $exitCode = Artisan::call('gdpr:cleanup-retention');

        $this->assertEquals(0, $exitCode);

        // 4. Assert expired exports deleted/expired
        Storage::assertMissing('private/report_exports/expired_report.csv');
        Storage::assertExists('private/report_exports/active_report.csv');

        $expiredExport->refresh();
        $this->assertEquals('expired', $expiredExport->status);

        $activeExport->refresh();
        $this->assertEquals('generated', $activeExport->status);

        // 5. Assert expired logs deleted, active ones remain
        $this->assertDatabaseMissing('member_portal_activity_logs', [
            'id' => $expiredPortalLog->id
        ]);
        $this->assertDatabaseHas('member_portal_activity_logs', [
            'id' => $activePortalLog->id
        ]);

        // 6. Assert a gdpr_cleanup audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'system',
            'action' => 'gdpr_cleanup'
        ]);
    }
}
