<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_delivery_logs_are_masked_for_regular_vienna_admin(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $viennaAdmin->givePermissionTo('view_notification_logs');

        $delivery = NotificationDelivery::create([
            'recipient_type' => 'external',
            'recipient_name' => 'John Doe',
            'recipient_email' => 'john@example.com',
            'recipient_phone' => '+491234567890',
            'channel' => 'email',
            'delivery_status' => 'delivered'
        ]);

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->getJson("/api/v1/communications/deliveries/{$delivery->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.recipient_email', 'j***@example.com')
            ->assertJsonPath('data.recipient_phone', '+49******7890');
    }

    public function test_delivery_logs_are_unmasked_for_super_admin(): void
    {
        $super = User::where('email', 'superadmin@msoc-europe.org')->first();

        $delivery = NotificationDelivery::create([
            'recipient_type' => 'external',
            'recipient_name' => 'John Doe',
            'recipient_email' => 'john@example.com',
            'recipient_phone' => '+491234567890',
            'channel' => 'email',
            'delivery_status' => 'delivered'
        ]);

        $response = $this->actingAs($super, 'sanctum')
            ->getJson("/api/v1/communications/deliveries/{$delivery->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.recipient_email', 'john@example.com')
            ->assertJsonPath('data.recipient_phone', '+491234567890');
    }
}
