<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_set_preferences(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/communications/preferences', [
                'channel' => 'email',
                'notification_type' => 'newsletter',
                'is_enabled' => false
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $admin->id,
            'channel' => 'email',
            'notification_type' => 'newsletter',
            'is_enabled' => false
        ]);
    }

    public function test_preference_checks_are_bypassed_for_critical_alerts(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        // Turn off email notifications for finance (critical type)
        NotificationPreference::create([
            'diocese_id' => 1,
            'user_id' => $admin->id,
            'channel' => 'email',
            'notification_type' => 'finance',
            'is_enabled' => false
        ]);

        // PreferenceService should still return true (enabled) because 'finance' is critical!
        $recipient = [
            'recipient_type' => 'user',
            'recipient_id' => $admin->id
        ];
        $isEnabled = NotificationPreferenceService::canSend($recipient, 'email', 'finance');
        $this->assertTrue($isEnabled);

        // For non-critical, it should respect preference
        NotificationPreference::create([
            'diocese_id' => 1,
            'user_id' => $admin->id,
            'channel' => 'email',
            'notification_type' => 'general',
            'is_enabled' => false
        ]);
        $isEnabledGeneral = NotificationPreferenceService::canSend($recipient, 'email', 'general');
        $this->assertFalse($isEnabledGeneral);
    }
}
