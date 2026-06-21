<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_store_draft_announcement(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/communications/announcements', [
                'title' => 'Diocese circular 2026',
                'body' => 'This is the circular text.',
                'announcement_type' => 'diocese',
                'priority' => 'normal',
                'visibility' => 'members',
                'targets' => [
                    ['target_type' => 'all_members']
                ]
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('announcements', [
            'title' => 'Diocese circular 2026',
            'status' => 'draft'
        ]);
    }

    public function test_admin_can_send_announcement_immediately(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        // Create draft
        $announcement = Announcement::create([
            'diocese_id' => 1,
            'title' => 'Urgent message',
            'body' => 'Please read.',
            'announcement_type' => 'diocese',
            'priority' => 'urgent',
            'status' => 'draft',
            'created_by' => $admin->id
        ]);

        $announcement->targets()->create([
            'target_type' => 'all_members'
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/send");

        $response->assertStatus(200);
        $this->assertEquals('sent', $announcement->fresh()->status);
    }

    public function test_admin_can_schedule_announcement(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $announcement = Announcement::create([
            'diocese_id' => 1,
            'title' => 'Scheduled announcement',
            'body' => 'Will send later.',
            'announcement_type' => 'general',
            'status' => 'draft',
            'created_by' => $admin->id
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/schedule", [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s')
            ]);

        $response->assertStatus(200);
        $this->assertEquals('scheduled', $announcement->fresh()->status);
    }

    public function test_admin_can_cancel_scheduled_announcement(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $announcement = Announcement::create([
            'diocese_id' => 1,
            'title' => 'Scheduled announcement',
            'body' => 'Will send later.',
            'announcement_type' => 'general',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'created_by' => $admin->id
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/cancel", [
                'cancellation_reason' => 'No longer needed'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $announcement->fresh()->status);
    }
}
