<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_creating_template_generates_audit_log(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/communications/templates', [
                'template_key' => 'custom_alert',
                'name' => 'Custom Alert Mail',
                'channel' => 'email',
                'subject' => 'Alert!',
                'body' => 'Something happened.',
                'variables' => []
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Template Created'
        ]);
    }
}
