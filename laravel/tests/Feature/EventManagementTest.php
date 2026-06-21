<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();
    }

    public function test_diocese_admin_can_create_diocesan_event(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/events', [
                'title' => 'Diocesan Retreat 2026',
                'event_type' => 'retreat',
                'start_datetime' => '2026-09-01 09:00:00',
                'end_datetime' => '2026-09-03 17:00:00',
                'mode' => 'offline',
                'location_name' => 'Diocesan Center',
                'visibility' => 'public',
                'registration_required' => true,
                'registration_fee' => 15.00,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'Diocesan Retreat 2026',
            'church_id' => null
        ]);
    }

    public function test_parish_admin_cannot_create_diocesan_event(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/events', [
                'title' => 'Invalid Diocesan Event',
                'event_type' => 'retreat',
                'start_datetime' => '2026-09-01 09:00:00',
                'end_datetime' => '2026-09-03 17:00:00',
                'mode' => 'offline',
                'visibility' => 'public',
            ]);

        $response->assertStatus(403);
    }

    public function test_parish_admin_can_create_parish_event(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/events', [
                'church_id' => $this->viennaChurch->id,
                'title' => 'Vienna Parish Feast 2026',
                'event_type' => 'feast',
                'start_datetime' => '2026-08-15 08:00:00',
                'end_datetime' => '2026-08-15 15:00:00',
                'mode' => 'offline',
                'location_name' => 'Vienna Parish Church',
                'visibility' => 'public',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'Vienna Parish Feast 2026',
            'church_id' => $this->viennaChurch->id
        ]);
    }

    public function test_can_publish_event(): void
    {
        $event = Event::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'title' => 'Draft Event',
            'slug' => 'draft-event',
            'event_type' => 'meeting',
            'start_datetime' => '2026-08-15 08:00:00',
            'end_datetime' => '2026-08-15 15:00:00',
            'mode' => 'online',
            'visibility' => 'public',
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/events/{$event->id}/publish");

        $response->assertStatus(200);
        $this->assertEquals('published', $event->fresh()->status);
    }
}
