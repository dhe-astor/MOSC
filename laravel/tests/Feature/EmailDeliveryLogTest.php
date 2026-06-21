<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationDelivery;
use App\Models\Notification;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_delivery_logs(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        // Create a delivery log
        NotificationDelivery::create([
            'recipient_type' => 'user',
            'recipient_id' => $admin->id,
            'recipient_name' => $admin->name,
            'recipient_email' => 'admin@msoc-europe.org',
            'channel' => 'email',
            'delivery_status' => 'delivered'
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/communications/deliveries');

        $response->assertStatus(200);
    }

    public function test_can_retry_failed_delivery_within_limit(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $notification = Notification::create([
            'diocese_id' => 1,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'title' => 'Important alert',
            'body' => 'Details.',
            'notification_type' => 'system',
            'channel' => 'email',
            'priority' => 'high',
            'status' => 'failed'
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'recipient_type' => 'user',
            'recipient_id' => $admin->id,
            'recipient_name' => $admin->name,
            'recipient_email' => 'admin@msoc-europe.org',
            'channel' => 'email',
            'delivery_status' => 'failed',
            'attempt_count' => 1
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/deliveries/{$delivery->id}/retry");

        $response->assertStatus(200);
        // Status should transition back to queued/sent
        $this->assertNotEquals('failed', $delivery->fresh()->delivery_status);
        $this->assertEquals(2, $delivery->fresh()->attempt_count);
    }

    public function test_cannot_retry_beyond_max_limit(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $notification = Notification::create([
            'diocese_id' => 1,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'title' => 'Important alert',
            'body' => 'Details.',
            'notification_type' => 'system',
            'channel' => 'email',
            'status' => 'failed'
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'recipient_type' => 'user',
            'recipient_id' => $admin->id,
            'recipient_name' => $admin->name,
            'recipient_email' => 'admin@msoc-europe.org',
            'channel' => 'email',
            'delivery_status' => 'failed',
            'attempt_count' => 3 // Max retries reached
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/deliveries/{$delivery->id}/retry");

        $response->assertStatus(400); // Throws exception/bad request
        $this->assertEquals('failed', $delivery->fresh()->delivery_status);
    }
}
