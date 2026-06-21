<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\SundaySchoolAcademicYear;
use App\Models\SundaySchoolLevel;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolExam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalSundaySchoolTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser;
    protected $parentMember;
    protected $childMember;
    protected $family;
    protected $student;

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

        $this->parentMember = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family->id,
            'member_code' => 'MEM-PORTAL-P',
            'first_name' => 'Jane',
            'last_name' => 'Member',
            'full_name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'phone' => '+43660111223',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gender' => 'female',
            'date_of_birth' => '1980-05-10',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->childMember = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family->id,
            'member_code' => 'MEM-PORTAL-C',
            'first_name' => 'Jane Jr',
            'last_name' => 'Member',
            'full_name' => 'Jane Jr Member',
            'email' => 'janejr@example.com',
            'phone' => '+43660111223',
            'relationship_to_head' => 'daughter',
            'membership_status' => 'active',
            'gender' => 'female',
            'date_of_birth' => '2015-05-10',
            'created_by' => $this->viennaAdmin->id
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->parentMember->id,
            'user_id' => $this->portalUser->id,
            'access_type' => 'parent_guardian',
            'status' => 'active'
        ]);

        // Create Sunday School setup
        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $level = SundaySchoolLevel::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
            'minimum_age' => 5,
            'maximum_age' => 15,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->student = SundaySchoolStudent::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $this->childMember->id,
            'family_id' => $this->childMember->family_id,
            'parent_member_id' => $this->parentMember->id,
            'enrollment_date' => now(),
            'enrollment_status' => 'approved',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_parent_can_view_verified_child_marks(): void
    {
        $exam = SundaySchoolExam::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'academic_year_id' => $this->student->academic_year_id,
            'class_id' => $this->student->class_id,
            'exam_name' => 'Midterm 2026',
            'exam_type' => 'midterm',
            'exam_date' => now()->toDateString(),
            'max_marks' => 100,
            'weightage' => 40,
            'status' => 'published',
            'created_by' => $this->viennaAdmin->id
        ]);

        $marks = SundaySchoolMark::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'marks_obtained' => 95,
            'result_status' => 'passed',
            'entered_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson("/api/v1/member-portal/children/{$this->childMember->id}/marks");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.marks_obtained', '95.00');
    }
}
