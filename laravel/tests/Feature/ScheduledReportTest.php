<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScheduledReport;
use App\Models\ReportDefinition;
use App\Services\ScheduledReportService;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
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
        Storage::fake();
    }

    public function test_scheduled_report_runs_and_notifies(): void
    {
        $def = ReportDefinition::where('report_key', 'diocese_overview')->first();

        // Create a scheduled report
        $scheduled = ScheduledReport::create([
            'diocese_id' => 1,
            'report_definition_id' => $def->id,
            'name' => 'Weekly Diocese Status',
            'frequency' => 'weekly',
            'timezone' => 'Europe/Vienna',
            'recipients' => [$this->superAdmin->id],
            'export_type' => 'csv',
            'status' => 'active',
            'next_run_at' => now()->subMinutes(5), // Due
            'created_by' => $this->superAdmin->id,
        ]);

        // Process scheduled reports
        $count = ScheduledReportService::processScheduledReports();
        $this->assertEquals(1, $count);

        // Verify next run is updated
        $scheduled->refresh();
        $this->assertNotNull($scheduled->last_run_at);
        $this->assertTrue($scheduled->next_run_at->isAfter(now()));

        // Verify notification is created
        $notification = Notification::where('body', 'like', '%Weekly Diocese Status%')->first();
        $this->assertNotNull($notification);
    }
}
