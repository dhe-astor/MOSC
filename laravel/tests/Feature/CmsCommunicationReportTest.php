<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsCommunicationReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
    }

    public function test_cms_and_communication_reports(): void
    {
        NotificationDelivery::create([
            'diocese_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => $this->superAdmin->id,
            'recipient_name' => 'Super Admin',
            'recipient_email' => 'superadmin@msoc-europe.org',
            'notification_category' => 'system',
            'channel' => 'email',
            'recipient_contact' => 'test@example.com',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        $cmsResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'cms_publishing'
            ]);

        $cmsResponse->assertStatus(200);

        $commResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'communications_delivery'
            ]);

        $commResponse->assertStatus(200);
    }
}
