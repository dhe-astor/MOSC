<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\SundaySchoolTeacher;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SundaySchoolReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        // Create a teacher user
        $this->teacherUser = User::create([
            'name' => 'SS Teacher',
            'email' => 'ssteacher@example.com',
            'phone' => '+436649998888',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->vienna->diocese_id,
            'default_church_id' => $this->vienna->id,
            'is_active' => true,
        ]);
        $this->teacherUser->assignRole('Sunday School Teacher');
        $this->teacherUser->givePermissionTo('view_child_reports');

        \App\Models\UserChurchAccess::create([
            'user_id' => $this->teacherUser->id,
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'access_scope' => 'church_scoped',
            'status' => 'active'
        ]);
    }

    public function test_teacher_scoped_to_assigned_class(): void
    {
        $ay = \App\Models\SundaySchoolAcademicYear::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'AY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $level = \App\Models\SundaySchoolLevel::create([
            'diocese_id' => $this->vienna->diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
            'minimum_age' => 5,
            'maximum_age' => 12,
        ]);

        $teacher = SundaySchoolTeacher::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'user_id' => $this->teacherUser->id,
            'full_name' => 'SS Teacher',
            'phone' => '+436649998888',
            'email' => 'ssteacher@example.com',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $class1 = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class 1',
            'primary_teacher_id' => $teacher->id,
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $class2 = SundaySchoolClass::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class 2',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $member1 = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'Kid',
            'last_name' => 'One',
            'full_name' => 'Kid One',
            'gender' => 'male',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        SundaySchoolStudent::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_id' => $class1->id,
            'member_id' => $member1->id,
            'status' => 'enrolled',
            'enrollment_date' => '2026-01-01',
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Run report as Sunday School Teacher
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'sunday_school_progress'
            ]);

        $response->assertStatus(200);
        $students = $response->json('data.data');
        $this->assertCount(1, $students);
        $this->assertEquals('Kid One', $students[0]['Student Name']);
    }
}
