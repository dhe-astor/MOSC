<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser;
    protected $member;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->portalUser = User::create([
            'name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->viennaAdmin->default_diocese_id,
            'default_church_id' => $this->viennaAdmin->default_church_id,
        ]);

        $this->family = Family::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_code' => 'FAM-PORTAL-2',
            'family_name' => 'Jane Family',
            'primary_phone' => '+43660111223',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family->id,
            'member_code' => 'MEM-PORTAL-2',
            'first_name' => 'Jane',
            'last_name' => 'Member',
            'full_name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'phone' => '+43660111223',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gender' => 'female',
            'date_of_birth' => '1992-05-10',
            'created_by' => $this->viennaAdmin->id
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->member->id,
            'user_id' => $this->portalUser->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);
    }

    public function test_can_view_and_mark_notifications_read(): void
    {
        $notification = Notification::create([
            'diocese_id' => $this->family->diocese_id,
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $this->portalUser->id,
            'title' => 'Parish Picnic',
            'body' => 'Join us this Sunday.',
            'notification_type' => 'announcement',
            'channel' => 'in_app'
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson('/api/v1/member-portal/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Parish Picnic');

        $readResponse = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson("/api/v1/member-portal/notifications/{$notification->id}/mark-read");

        $readResponse->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => now()->toDateTimeString()
        ]);
    }

    public function test_critical_notifications_bypass_preferences(): void
    {
        // Set channels to disabled
        NotificationPreference::create([
            'diocese_id' => $this->family->diocese_id,
            'user_id' => $this->portalUser->id,
            'channel' => 'email',
            'notification_type' => 'general',
            'is_enabled' => false
        ]);
        NotificationPreference::create([
            'diocese_id' => $this->family->diocese_id,
            'user_id' => $this->portalUser->id,
            'channel' => 'email',
            'notification_type' => 'security',
            'is_enabled' => false
        ]);

        // Verification via service helper
        $recipient = [
            'recipient_type' => 'user',
            'recipient_id' => $this->portalUser->id
        ];
        $shouldSendGeneral = NotificationPreferenceService::canSend($recipient, 'email', 'general');
        $shouldSendCritical = NotificationPreferenceService::canSend($recipient, 'email', 'security');

        $this->assertFalse($shouldSendGeneral);
        $this->assertTrue($shouldSendCritical); // Critical category bypasses preference toggle
    }
}
