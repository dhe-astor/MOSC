<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_parish_admin_cannot_create_diocese_wide_announcement(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->postJson('/api/v1/communications/announcements', [
                'title' => 'Parish announcement trying to be diocese-wide',
                'body' => 'Violating boundary.',
                'announcement_type' => 'diocese',
                'church_id' => null, // Diocese-wide
                'targets' => [
                    ['target_type' => 'all_members']
                ]
            ]);

        $response->assertStatus(403);
    }

    public function test_parish_admin_cannot_target_another_parish(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $herne = Church::where('short_name', 'Herne')->first();

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->postJson('/api/v1/communications/announcements', [
                'title' => 'Vienna targeting Herne',
                'body' => 'Violating boundary.',
                'announcement_type' => 'parish',
                'church_id' => $herne->id, // Herne
                'targets' => [
                    ['target_type' => 'church', 'target_id' => $herne->id]
                ]
            ]);

        $response->assertStatus(403);
    }
}
