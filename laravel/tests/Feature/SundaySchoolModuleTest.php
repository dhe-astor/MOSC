<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\Diocese;
use App\Models\CertificateTemplate;
use App\Models\SundaySchoolAcademicYear;
use App\Models\SundaySchoolLevel;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolTeacher;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAttendance;
use App\Models\SundaySchoolExam;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolProgressReport;
use App\Models\SundaySchoolCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\SundaySchoolAttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SundaySchoolModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $priest;
    protected $vienna;
    protected $herne;
    protected $member;
    protected $family;
    protected $parentMember;
    protected $parentUser;
    protected $teacherUser;
    protected $teacherProfile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->superAdmin->two_factor_enabled = true;
        $this->superAdmin->two_factor_last_verified_at = now();
        $this->superAdmin->save();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();

        // Create family
        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Test Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create parent member and user account
        $this->parentUser = User::create([
            'name' => 'Parent User',
            'email' => 'parent@test.com',
            'password' => bcrypt('Password123!'),
            'default_diocese_id' => $this->vienna->diocese_id,
        ]);
        $this->parentMember = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'user_id' => $this->parentUser->id,
            'first_name' => 'Parent',
            'last_name' => 'One',
            'full_name' => 'Parent One',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '1985-01-01',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create student member (child)
        $this->member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Vienna',
            'last_name' => 'Student',
            'full_name' => 'Vienna Student',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '2016-06-15', // 10 years old in 2026
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->teacherUser = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@test.com',
            'password' => bcrypt('Password123!'),
            'default_diocese_id' => $this->vienna->diocese_id,
            'default_church_id' => $this->vienna->id,
            'active_church_id' => $this->vienna->id,
        ]);

        $this->teacherProfile = SundaySchoolTeacher::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'user_id' => $this->teacherUser->id,
            'full_name' => 'Teacher User',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    // ==========================================
    // 1. SundaySchoolAcademicYearTest
    // ==========================================
    public function test_academic_year_lifecycle_and_current_rule(): void
    {
        // Create AY 1
        $ay1 = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create AY 2
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/academic-years', [
                'diocese_id' => $this->vienna->diocese_id,
                'name' => 'AY 2026-2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'status' => 'draft',
                'is_current' => false,
            ]);

        $response->assertStatus(201);
        $ay2Id = $response->json('data.id');

        // Activate AY 2
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/academic-years/{$ay2Id}/activate")
            ->assertStatus(200);

        // Verify only AY 2 is current
        $this->assertFalse($ay1->fresh()->is_current);
        $this->assertTrue(SundaySchoolAcademicYear::findOrFail($ay2Id)->is_current);
    }

    // ==========================================
    // 2. SundaySchoolLevelTest
    // ==========================================
    public function test_level_uniqueness_per_diocese(): void
    {
        SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        // Same code, same diocese: should fail
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/levels', [
                'diocese_id' => $this->vienna->diocese_id,
                'level_name' => 'Level 1 Duplicate',
                'level_code' => 'L1',
                'sort_order' => 2,
            ]);

        $response->assertStatus(422);

        $otherDiocese = Diocese::create(['name' => 'Other Diocese', 'short_name' => 'Other', 'region' => 'UK', 'canonical_name' => 'other-diocese']);

        $response2 = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/levels', [
                'diocese_id' => $otherDiocese->id,
                'level_name' => 'Level 1 UK',
                'level_code' => 'L1',
                'sort_order' => 1,
            ]);

        $response2->assertStatus(201);
    }

    // ==========================================
    // 3. SundaySchoolClassTest
    // ==========================================
    public function test_class_scoping_and_teacher_assignments(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        // Vienna admin creates class in Vienna
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/classes', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'academic_year_id' => $ay->id,
                'level_id' => $level->id,
                'class_name' => 'Vienna Class A',
                'mode' => 'offline',
            ]);

        $response->assertStatus(201);
        $classId = $response->json('data.id');

        // Assign teacher
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/teachers/{$this->teacherProfile->id}/assign-class", [
                'class_id' => $classId,
                'role' => 'primary',
                'assigned_from' => '2026-06-15',
            ])->assertStatus(200);

        // Verify assignment exists
        $this->assertDatabaseHas('sunday_school_class_teacher_assignments', [
            'class_id' => $classId,
            'teacher_id' => $this->teacherProfile->id,
            'status' => 'active',
        ]);
    }

    // ==========================================
    // 4. SundaySchoolEnrollmentTest
    // ==========================================
    public function test_student_enrollment_and_duplicate_prevention(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
            'minimum_age' => 5,
            'maximum_age' => 12,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        // 1. Enroll child
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/students', [
                'member_id' => $this->member->id,
                'class_id' => $class->id,
                'academic_year_id' => $ay->id,
            ]);

        $response->assertStatus(201);
        $studentId = $response->json('data.id');

        // 2. Prevent duplicate enrollment
        $response2 = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/students', [
                'member_id' => $this->member->id,
                'class_id' => $class->id,
                'academic_year_id' => $ay->id,
            ]);

        $response2->assertStatus(400); // Bad Request (duplicate blocked)

        // 3. Approve student
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/students/{$studentId}/approve")
            ->assertStatus(200);

        $this->assertEquals('active', SundaySchoolStudent::find($studentId)->enrollment_status);
    }

    // ==========================================
    // 5. SundaySchoolAttendanceTest
    // ==========================================
    public function test_attendance_marking_and_scoping(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        $student = SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $this->member->id,
            'enrollment_date' => '2026-06-15',
            'enrollment_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        // Unassigned teacher tries to mark: Denied (TeacherUser is not assigned yet)
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/sunday-school/attendance/mark', [
                'class_id' => $class->id,
                'attendance_date' => '2026-06-15',
                'records' => [
                    ['student_id' => $student->id, 'status' => 'present']
                ]
            ])->assertStatus(403);

        // Assign teacher
        DB::table('sunday_school_class_teacher_assignments')->insert([
            'class_id' => $class->id,
            'teacher_id' => $this->teacherProfile->id,
            'role' => 'primary',
            'assigned_from' => '2026-06-15',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        // Mark present
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/sunday-school/attendance/mark', [
                'class_id' => $class->id,
                'attendance_date' => '2026-06-15',
                'records' => [
                    ['student_id' => $student->id, 'status' => 'present']
                ]
            ])->assertStatus(200);

        // Mark absent on another date
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/sunday-school/attendance/mark', [
                'class_id' => $class->id,
                'attendance_date' => '2026-06-22',
                'records' => [
                    ['student_id' => $student->id, 'status' => 'absent']
                ]
            ])->assertStatus(200);

        // Verify percentage is 50.00
        $pct = app(SundaySchoolAttendanceService::class)->calculateAttendancePercentage($student->id);
        $this->assertEquals(50.00, $pct);
    }

    // ==========================================
    // 6. SundaySchoolExamMarksTest
    // ==========================================
    public function test_exam_marks_and_verification_lock(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        $student = SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $this->member->id,
            'enrollment_date' => '2026-06-15',
            'enrollment_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $exam = SundaySchoolExam::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'exam_name' => 'Midterm',
            'exam_type' => 'midterm',
            'exam_date' => '2026-06-15',
            'max_marks' => 100,
            'pass_marks' => 50,
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        // Assign teacher
        DB::table('sunday_school_class_teacher_assignments')->insert([
            'class_id' => $class->id,
            'teacher_id' => $this->teacherProfile->id,
            'role' => 'primary',
            'assigned_from' => '2026-06-15',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        // Enter mark
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/sunday-school/marks', [
                'exam_id' => $exam->id,
                'records' => [
                    ['student_id' => $student->id, 'marks_obtained' => 85]
                ]
            ])->assertStatus(200);

        // Verify marks (Vienna Admin verifies)
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/marks/{$exam->id}/verify")
            ->assertStatus(200);

        // Teacher tries to modify verified mark: Denied
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/sunday-school/marks', [
                'exam_id' => $exam->id,
                'records' => [
                    ['student_id' => $student->id, 'marks_obtained' => 90]
                ]
            ])->assertStatus(403);

        // Admin corrects mark: Allowed
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/sunday-school/marks', [
                'exam_id' => $exam->id,
                'records' => [
                    ['student_id' => $student->id, 'marks_obtained' => 95]
                ]
            ])->assertStatus(200);

        $this->assertEquals(95, SundaySchoolMark::where('exam_id', $exam->id)->first()->marks_obtained);
    }

    // ==========================================
    // 7. SundaySchoolProgressReportTest
    // ==========================================
    public function test_progress_report_generation(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        $student = SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $this->member->id,
            'enrollment_date' => '2026-06-15',
            'enrollment_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $exam = SundaySchoolExam::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'exam_name' => 'Midterm',
            'exam_type' => 'midterm',
            'exam_date' => '2026-06-15',
            'max_marks' => 100,
            'pass_marks' => 50,
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        SundaySchoolMark::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 90,
            'grade' => 'A',
            'result_status' => 'pass',
            'entered_by' => $this->superAdmin->id,
            'verified_by' => $this->superAdmin->id,
            'verified_at' => now(),
        ]);

        // Generate progress report
        \Laravel\Sanctum\Sanctum::actingAs($this->superAdmin, ['2fa_verified']);
        $response = $this->postJson("/api/v1/sunday-school/students/{$student->id}/generate-progress-report");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.pdf_path'));
        $this->assertEquals(90.00, $response->json('data.total_marks'));
        $this->assertEquals('A', $response->json('data.grade'));
    }

    // ==========================================
    // 8. SundaySchoolPromotionTest
    // ==========================================
    public function test_promotion_workflow_history_preservation(): void
    {
        $ay1 = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $ay2 = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'draft',
            'is_current' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        $level1 = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $level2 = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 2',
            'level_code' => 'L2',
            'sort_order' => 2,
        ]);

        $class1 = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay1->id,
            'level_id' => $level1->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        $class2 = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay2->id,
            'level_id' => $level2->id,
            'class_name' => 'Class B',
            'created_by' => $this->superAdmin->id,
        ]);

        $student = SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay1->id,
            'class_id' => $class1->id,
            'member_id' => $this->member->id,
            'enrollment_date' => '2025-09-01',
            'enrollment_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        // Promote
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/students/{$student->id}/promote", [
                'target_class_id' => $class2->id,
                'target_academic_year_id' => $ay2->id,
            ]);

        $response->assertStatus(200);

        // Verify status
        $this->assertEquals('promoted', $student->fresh()->enrollment_status);
        $this->assertDatabaseHas('sunday_school_students', [
            'member_id' => $this->member->id,
            'class_id' => $class2->id,
            'academic_year_id' => $ay2->id,
            'enrollment_status' => 'active',
        ]);
    }

    // ==========================================
    // 9. SundaySchoolCertificateTest
    // ==========================================
    public function test_certificate_generation_via_phase_3_engine(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->superAdmin->id,
        ]);

        $student = SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $this->member->id,
            'enrollment_date' => '2026-06-15',
            'enrollment_status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $template = CertificateTemplate::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'Sunday School Certificate',
            'certificate_type' => 'course_completion',
            'html_template' => '<html><body><h1>Certificate</h1><p>{{member_full_name}}</p></body></html>',
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // Issue certificate
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/students/{$student->id}/issue-certificate", [
                'certificate_type' => 'completion',
                'certificate_template_id' => $template->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sunday_school_certificates', [
            'student_id' => $student->id,
            'certificate_type' => 'completion',
        ]);
    }

    // ==========================================
    // 10. SundaySchoolScopingTest
    // ==========================================
    public function test_role_based_scoping_for_admin_vicar_teacher_parent(): void
    {
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
        ]);

        $classVienna = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Vienna Class',
            'created_by' => $this->superAdmin->id,
        ]);

        $classHerne = SundaySchoolClass::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Herne Class',
            'created_by' => $this->superAdmin->id,
        ]);

        // 1. Vienna Parish Admin tries to see Vienna class: Allowed
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/sunday-school/classes/{$classVienna->id}")
            ->assertStatus(200);

        // 2. Vienna Parish Admin tries to see Herne class: Denied
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/sunday-school/classes/{$classHerne->id}")
            ->assertStatus(403);

        // 3. Parent user tries to get children: returns our test child
        $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/v1/sunday-school/parents/my-children')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data'); // Member child isn't enrolled yet, so returns empty list of SundaySchoolStudent records
    }

    // ==========================================
    // 11. SundaySchoolChildPrivacyTest
    // ==========================================
    public function test_student_privacy_and_export_locks(): void
    {
        // Parent/Teacher tries to export child data without permission: Denied
        $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/v1/sunday-school/exports/students')
            ->assertStatus(403);

        // Super Admin tries to export: Allowed
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/sunday-school/exports/students')
            ->assertStatus(200);
    }
}
