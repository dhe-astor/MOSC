<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Member;
use App\Models\Family;
use App\Models\Church;
use App\Models\CourseRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $course;
    protected $batch;
    protected $member;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();
        $this->course = Course::first();

        // Let's create a member and family in Vienna for testing
        $this->family = Family::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_name' => 'Test Family',
            'family_code' => 'VIE-FAM-0001',
            'primary_phone' => '+43660111444',
            'address_line_1' => 'Vienna St 4',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_id' => $this->family->id,
            'member_code' => 'VIE-MEM-0001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->batch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'batch_name' => 'Syriac Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-0001',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'registration_open_at' => '2026-06-01 00:00:00',
            'registration_close_at' => '2026-06-30 23:59:59',
            'max_participants' => 2,
            'fee_amount' => 10,
            'currency' => 'EUR',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_can_register_member_for_batch(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-registrations', [
                'course_batch_id' => $this->batch->id,
                'registration_type' => 'member',
                'member_id' => $this->member->id,
                'payment_status' => 'pending',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_registrations', [
            'course_batch_id' => $this->batch->id,
            'member_id' => $this->member->id,
            'registration_status' => 'pending'
        ]);
    }

    public function test_cannot_register_duplicate_member(): void
    {
        // First registration
        CourseRegistration::create([
            'course_batch_id' => $this->batch->id,
            'diocese_id' => $this->batch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'member',
            'member_id' => $this->member->id,
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Attempt duplicate registration
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-registrations', [
                'course_batch_id' => $this->batch->id,
                'registration_type' => 'member',
                'member_id' => $this->member->id,
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'This member is already registered for this course batch.']);
    }

    public function test_can_register_external_participant(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-registrations', [
                'course_batch_id' => $this->batch->id,
                'registration_type' => 'external',
                'external_name' => 'Jane Smith',
                'external_email' => 'jane.smith@example.com',
                'external_phone' => '+436601234567',
                'payment_status' => 'paid',
                'payment_reference' => 'CASH-001',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_registrations', [
            'course_batch_id' => $this->batch->id,
            'external_name' => 'Jane Smith',
            'payment_status' => 'paid',
            'registration_status' => 'confirmed', // Should auto-confirm if paid! Let's check how the service handles this.
        ]);
    }

    public function test_capacity_limit_boundary(): void
    {
        // Let's create two registrations to fill the capacity (max_participants is 2)
        CourseRegistration::create([
            'course_batch_id' => $this->batch->id,
            'diocese_id' => $this->batch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'external',
            'external_name' => 'Participant 1',
            'external_email' => 'p1@example.com',
            'registration_status' => 'confirmed',
        ]);

        CourseRegistration::create([
            'course_batch_id' => $this->batch->id,
            'diocese_id' => $this->batch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'external',
            'external_name' => 'Participant 2',
            'external_email' => 'p2@example.com',
            'registration_status' => 'confirmed',
        ]);

        // Attempt 3rd registration
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-registrations', [
                'course_batch_id' => $this->batch->id,
                'registration_type' => 'external',
                'external_name' => 'Participant 3',
                'external_email' => 'p3@example.com',
                'external_phone' => '+43660111777',
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'This batch is full. Cannot register.']);
    }
}
