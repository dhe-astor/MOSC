<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Models\CourseRegistration;
use App\Models\Member;
use App\Models\Family;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $course;
    protected $batch;
    protected $session;
    protected $registration;
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

        // Create member, family
        $this->family = Family::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_name' => 'Attendance Family',
            'family_code' => 'VIE-FAM-0002',
            'primary_phone' => '+43660111222',
            'address_line_1' => 'Vienna St 2',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_id' => $this->family->id,
            'member_code' => 'VIE-MEM-0002',
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'full_name' => 'Bob Smith',
            'relationship_to_head' => 'head',
            'gender' => 'male',
            'date_of_birth' => '1985-05-05',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Create batch, session
        $this->batch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'batch_name' => 'Syriac Attendance Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-0002',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->session = CourseSession::create([
            'course_batch_id' => $this->batch->id,
            'title' => 'Session 1',
            'session_date' => '2026-07-05',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'session_order' => 1,
            'attendance_required' => true,
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create registration
        $this->registration = CourseRegistration::create([
            'course_batch_id' => $this->batch->id,
            'diocese_id' => $this->batch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'registration_type' => 'member',
            'member_id' => $this->member->id,
            'registration_status' => 'confirmed',
            'qr_code' => 'QR-CODE-TEST-12345',
        ]);
    }

    public function test_can_mark_attendance_manually(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-attendance/mark', [
                'course_session_id' => $this->session->id,
                'attendance' => [
                    [
                        'course_registration_id' => $this->registration->id,
                        'status' => 'present',
                        'remarks' => 'On time',
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('course_attendance', [
            'course_session_id' => $this->session->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present'
        ]);
    }

    public function test_can_check_in_via_qr_code(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-attendance/qr-check-in', [
                'course_session_id' => $this->session->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('course_attendance', [
            'course_session_id' => $this->session->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present'
        ]);
    }

    public function test_cannot_duplicate_qr_check_in(): void
    {
        // Check in once
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-attendance/qr-check-in', [
                'course_session_id' => $this->session->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        // Attempt second check in
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-attendance/qr-check-in', [
                'course_session_id' => $this->session->id,
                'qr_code' => $this->registration->qr_code,
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Attendance has already been marked present for this session.']);
    }
}
