<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use App\Models\Member;
use App\Models\Family;
use App\Models\Church;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $event;
    protected $member;
    protected $family;
    protected $registration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();

        // Create member, family
        $this->family = Family::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_name' => 'Event Attendance Family',
            'family_code' => 'VIE-FAM-0005',
            'primary_phone' => '+43660111555',
            'address_line_1' => 'Vienna St 5',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_id' => $this->family->id,
            'member_code' => 'VIE-MEM-0005',
            'first_name' => 'David',
            'last_name' => 'Miller',
            'full_name' => 'David Miller',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1987-07-07',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->event = Event::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'title' => 'Vienna Feast Day',
            'slug' => 'vienna-feast-day',
            'event_type' => 'feast',
            'start_datetime' => '2026-08-15 08:00:00',
            'end_datetime' => '2026-08-15 15:00:00',
            'mode' => 'offline',
            'location_name' => 'Vienna Parish Church',
            'visibility' => 'public',
            'status' => 'registration_open',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->registration = EventRegistration::create([
            'event_id' => $this->event->id,
            'diocese_id' => $this->event->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'member',
            'member_id' => $this->member->id,
            'registration_status' => 'confirmed',
            'qr_code' => 'EVENT-QR-123456',
        ]);
    }

    public function test_can_check_in_manually(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/event-attendance/mark', [
                'event_registration_id' => $this->registration->id,
                'remarks' => 'Vip guest',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('event_attendance', [
            'event_registration_id' => $this->registration->id,
            'status' => 'checked_in'
        ]);
        $this->assertEquals('checked_in', $this->registration->fresh()->registration_status);
    }

    public function test_can_check_in_via_qr_code(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/event-attendance/qr-check-in', [
                'event_id' => $this->event->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('event_attendance', [
            'event_registration_id' => $this->registration->id,
            'status' => 'checked_in'
        ]);
    }

    public function test_cannot_double_check_in(): void
    {
        // First check in
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/event-attendance/qr-check-in', [
                'event_id' => $this->event->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        // Attempt second check in
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/event-attendance/qr-check-in', [
                'event_id' => $this->event->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Participant is already checked in for this event.']);
    }
}
