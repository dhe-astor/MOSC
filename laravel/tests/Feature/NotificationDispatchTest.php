<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notification;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_list_notifications_inbox(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();

        // Create mock notification
        Notification::create([
            'diocese_id' => 1,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'title' => 'Test Notification',
            'body' => 'This is a test notification.',
            'notification_type' => 'system',
            'channel' => 'in_app',
            'priority' => 'normal',
            'status' => 'delivered'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/communications/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();

        $notification = Notification::create([
            'diocese_id' => 1,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'title' => 'Test Notification',
            'body' => 'This is a test notification.',
            'notification_type' => 'system',
            'channel' => 'in_app',
            'priority' => 'normal',
            'status' => 'delivered'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/communications/notifications/{$notification->id}/mark-read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
