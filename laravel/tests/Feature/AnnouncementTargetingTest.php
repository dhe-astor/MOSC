<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Member;
use App\Services\RecipientResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTargetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    public function test_target_preview_shows_count_only_for_non_unmasked_permission(): void
    {
        // Parish admin of Vienna does not have 'view_unmasked_notification_recipients' by default
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $viennaAdmin->givePermissionTo('manage_announcements');

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->postJson('/api/v1/communications/announcements/preview-targets', [
                'targets' => [
                    ['target_type' => 'all_members']
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['recipients'])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'estimated_recipients',
                    'channels'
                ]
            ]);
    }

    public function test_target_preview_shows_recipients_list_for_authorized_superadmin(): void
    {
        $super = User::where('email', 'superadmin@msoc-europe.org')->first();

        $response = $this->actingAs($super, 'sanctum')
            ->postJson('/api/v1/communications/announcements/preview-targets', [
                'targets' => [
                    ['target_type' => 'all_members']
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'estimated_recipients',
                    'channels',
                    'recipients'
                ]
            ]);
    }
}
