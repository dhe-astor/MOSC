<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalEventCourseTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser;
    protected $member;
    protected $family;
    protected $event;
    protected $courseBatch;

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

        // Create published event
        $this->event = Event::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'title' => 'Parish Day 2026',
            'slug' => 'parish-day-2026',
            'event_type' => 'feast',
            'description' => 'Annual feast',
            'start_datetime' => now()->addDays(5),
            'end_datetime' => now()->addDays(5)->addHours(4),
            'visibility' => 'members_only',
            'status' => 'published',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create course and open batch
        $course = Course::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'name' => 'Sunday School Teacher Training',
            'slug' => 'sunday-school-teacher-training',
            'course_type' => 'other',
            'description' => 'Training course',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->courseBatch = CourseBatch::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'course_id' => $course->id,
            'batch_name' => 'Batch 2026 A',
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(40),
            'mode' => 'offline',
            'status' => 'open',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_can_register_for_event(): void
    {
        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson("/api/v1/member-portal/events/{$this->event->id}/register", [
                'member_id' => $this->member->id,
                'participant_count' => 1
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $this->event->id,
            'member_id' => $this->member->id,
            'registration_status' => 'confirmed'
        ]);
    }

    public function test_cannot_register_duplicate_event(): void
    {
        EventRegistration::create([
            'diocese_id' => $this->event->diocese_id,
            'church_id' => $this->event->church_id,
            'event_id' => $this->event->id,
            'registration_type' => 'member',
            'member_id' => $this->member->id,
            'participant_count' => 1,
            'registration_status' => 'confirmed',
            'payment_status' => 'pending'
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson("/api/v1/member-portal/events/{$this->event->id}/register", [
                'member_id' => $this->member->id
            ]);

        $response->assertStatus(400);
    }
}
