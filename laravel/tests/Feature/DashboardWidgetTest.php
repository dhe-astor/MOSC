<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DashboardWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
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

    public function test_dashboard_widgets_permissions(): void
    {
        // Create widget visible only to Super Admin
        DashboardWidget::create([
            'diocese_id' => 1,
            'widget_key' => 'audit_widget',
            'title' => 'Audit Overview',
            'widget_type' => 'card',
            'data_source' => 'audit_logs',
            'required_permissions' => ['view_audit_reports'],
            'status' => 'active',
        ]);

        // Super Admin sees widget
        $response1 = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/reports/dashboard-widgets');

        $response1->assertStatus(200);
        $widgets1 = $response1->json('data');
        $this->assertCount(1, $widgets1);

        // Vienna admin lacks view_audit_reports, so they don't see it
        $response2 = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/reports/dashboard-widgets');

        $response2->assertStatus(200);
        $widgets2 = $response2->json('data');
        $this->assertCount(0, $widgets2);
    }
}
