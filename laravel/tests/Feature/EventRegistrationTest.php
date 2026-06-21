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

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $event;
    protected $member;
    protected $family;

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
            'family_name' => 'Event Registrant Family',
            'family_code' => 'VIE-FAM-0004',
            'primary_phone' => '+43660111666',
            'address_line_1' => 'Vienna St 6',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_id' => $this->family->id,
            'member_code' => 'VIE-MEM-0004',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'full_name' => 'Charlie Brown',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1988-08-08',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->event = Event::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'title' => 'Parish Retreat 2026',
            'slug' => 'parish-retreat-2026',
            'event_type' => 'retreat',
            'start_datetime' => '2026-08-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'offline',
            'location_name' => 'Vienna Parish Hall',
            'visibility' => 'public',
            'status' => 'registration_open',
            'registration_required' => true,
            'registration_fee' => 20.00,
            'currency' => 'EUR',
            'max_participants' => 50,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_can_register_member_for_event(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/event-registrations', [
                'event_id' => $this->event->id,
                'registration_type' => 'member',
                'member_id' => $this->member->id,
                'payment_status' => 'pending',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $this->event->id,
            'member_id' => $this->member->id,
            'registration_status' => 'pending'
        ]);
    }

    public function test_can_confirm_event_registration_with_manual_payment(): void
    {
        $reg = EventRegistration::create([
            'event_id' => $this->event->id,
            'diocese_id' => $this->event->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'member',
            'member_id' => $this->member->id,
            'registration_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/event-registrations/{$reg->id}/confirm", [
                'payment_reference' => 'MANUAL-REF-999',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('paid', $reg->fresh()->payment_status);
        $this->assertEquals('confirmed', $reg->fresh()->registration_status);
    }
}
