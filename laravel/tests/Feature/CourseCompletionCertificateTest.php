<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseSession;
use App\Models\CourseRegistration;
use App\Models\CourseAttendance;
use App\Models\CertificateRequest;
use App\Models\CertificateTemplate;
use App\Models\Member;
use App\Models\Family;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCompletionCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $course;
    protected $batch;
    protected $session1;
    protected $session2;
    protected $registration;
    protected $member;
    protected $family;
    protected $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();

        // Ensure we have a certificate template for course_completion
        $this->template = CertificateTemplate::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'name' => 'Syriac Course Completion Template',
            'certificate_type' => 'course_completion',
            'language' => 'en',
            'html_template' => '<h1>Course Completion Certificate</h1><p>Congratulations, {{member_name}}!</p>',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->course = Course::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'name' => 'Syriac Advanced',
            'slug' => 'syriac-advanced',
            'course_type' => 'syriac_language',
            'certificate_enabled' => true,
            'certificate_template_id' => $this->template->id,
            'feedback_required' => true,
            'attendance_required_percentage' => 75,
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create member, family
        $this->family = Family::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_name' => 'Completion Family',
            'family_code' => 'VIE-FAM-0003',
            'primary_phone' => '+43660111333',
            'address_line_1' => 'Vienna St 3',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaChurch->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'family_id' => $this->family->id,
            'member_code' => 'VIE-MEM-0003',
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'full_name' => 'Alice Johnson',
            'relationship_to_head' => 'head',
            'gender' => 'female',
            'date_of_birth' => '1995-10-10',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Create batch with 2 sessions
        $this->batch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'batch_name' => 'Syriac Advanced Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-0003',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'certificate_enabled' => true,
            'certificate_template_id' => $this->template->id,
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->session1 = CourseSession::create([
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

        $this->session2 = CourseSession::create([
            'course_batch_id' => $this->batch->id,
            'title' => 'Session 2',
            'session_date' => '2026-07-12',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'session_order' => 2,
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
        ]);
    }

    public function test_cannot_complete_without_meeting_attendance_threshold(): void
    {
        // Session 1: Present
        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session1->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        // Session 2: Absent (50% attendance total, which is < 75%)
        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session2->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'absent',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        // Submit feedback
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-feedback', [
                'course_registration_id' => $this->registration->id,
                'rating' => 5,
                'feedback_text' => 'Good course',
            ]);

        // Attempt mark-completed
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/course-registrations/{$this->registration->id}/mark-completed");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Cannot complete course. Attendance is 50%, which is below the required 75%.']);
        $this->assertFalse($this->registration->fresh()->certificate_issued);
    }

    public function test_cannot_complete_without_feedback_completed_if_required(): void
    {
        // Both sessions present (100% attendance)
        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session1->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session2->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        // Feedback is required but NOT submitted. Attempt mark-completed.
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/course-registrations/{$this->registration->id}/mark-completed");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Cannot complete course. Participant feedback is required.']);
        $this->assertFalse($this->registration->fresh()->certificate_issued);
    }

    public function test_successful_completion_automatically_issues_certificate(): void
    {
        // Both sessions present
        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session1->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        CourseAttendance::create([
            'course_batch_id' => $this->batch->id,
            'course_session_id' => $this->session2->id,
            'course_registration_id' => $this->registration->id,
            'status' => 'present',
            'marked_by' => $this->superAdmin->id,
            'marked_at' => now(),
            'attendance_date' => now()->toDateString(),
        ]);

        // Submit feedback
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-feedback', [
                'course_registration_id' => $this->registration->id,
                'rating' => 5,
                'feedback_text' => 'Loved it!',
            ]);

        // Mark completed -> should trigger auto-certificate issuance!
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/course-registrations/{$this->registration->id}/mark-completed");

        $response->assertStatus(200);
        $this->assertTrue($this->registration->fresh()->certificate_issued);
        $this->assertNotNull($this->registration->fresh()->certificate_id);
    }
}
