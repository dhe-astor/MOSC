<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CertificateRequest;
use App\Models\CertificateTemplate;
use App\Models\Member;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleNotificationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_certificate_request_submission_triggers_notification(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        $family = \App\Models\Family::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'family_code' => 'FAM-001',
            'family_name' => 'Mathew',
            'primary_phone' => '+43664777888',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $viennaAdmin->id
        ]);

        $member = Member::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'family_id' => $family->id,
            'member_code' => 'MEM-001',
            'first_name' => 'Jacob',
            'last_name' => 'Mathew',
            'full_name' => 'Jacob Mathew',
            'email' => 'jacob@example.com',
            'phone' => '+43664111222',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'approved_at' => now(),
            'gender' => 'male',
            'date_of_birth' => '1985-05-15',
            'created_by' => $viennaAdmin->id
        ]);

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->postJson('/api/v1/certificate-requests', [
                'diocese_id' => $viennaAdmin->default_diocese_id,
                'church_id' => $viennaAdmin->default_church_id,
                'member_id' => $member->id,
                'certificate_type' => 'membership',
                'purpose' => 'Job Application'
            ]);

        $response->assertStatus(201);

        // Verification: Check if a notification / delivery log was created
        $this->assertDatabaseHas('notification_deliveries', [
            'recipient_type' => 'user',
            'channel' => 'in_app'
        ]);
    }

    public function test_sunday_school_enrollment_approval_triggers_notification(): void
    {
        $viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        $family = \App\Models\Family::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'family_code' => 'FAM-002',
            'family_name' => 'Mathew Child',
            'primary_phone' => '+43664777889',
            'address_line_1' => 'Vienna St 2',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'approved_at' => now(),
            'created_by' => $viennaAdmin->id
        ]);

        $parent = Member::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'family_id' => $family->id,
            'member_code' => 'MEM-003',
            'first_name' => 'Jacob Sr',
            'last_name' => 'Mathew',
            'full_name' => 'Jacob Sr Mathew',
            'email' => 'jacobsr@example.com',
            'phone' => '+43664111224',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'approved_at' => now(),
            'gender' => 'male',
            'date_of_birth' => '1980-05-15',
            'created_by' => $viennaAdmin->id
        ]);

        $member = Member::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'family_id' => $family->id,
            'member_code' => 'MEM-002',
            'first_name' => 'Jacob Jr',
            'last_name' => 'Mathew',
            'full_name' => 'Jacob Jr Mathew',
            'email' => 'jacobjr@example.com',
            'phone' => '+43664111223',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'gender' => 'male',
            'date_of_birth' => now()->subYears(10)->toDateString(),
            'created_by' => $viennaAdmin->id
        ]);

        $ay = SundaySchoolAcademicYear::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
            'created_by' => $viennaAdmin->id
        ]);

        $level = \App\Models\SundaySchoolLevel::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'level_name' => 'Level 1',
            'level_code' => 'L1',
            'sort_order' => 1,
            'minimum_age' => 5,
            'maximum_age' => 15,
        ]);

        $class = SundaySchoolClass::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'academic_year_id' => $ay->id,
            'level_id' => $level->id,
            'class_name' => 'Class A',
            'created_by' => $viennaAdmin->id,
        ]);

        // Create pending student enrollment
        $student = SundaySchoolStudent::create([
            'diocese_id' => $viennaAdmin->default_diocese_id,
            'church_id' => $viennaAdmin->default_church_id,
            'academic_year_id' => $ay->id,
            'class_id' => $class->id,
            'member_id' => $member->id,
            'family_id' => $member->family_id,
            'enrollment_date' => now(),
            'enrollment_status' => 'pending',
            'created_by' => $viennaAdmin->id
        ]);

        $response = $this->actingAs($viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sunday-school/students/{$student->id}/approve");

        $response->assertStatus(200);

        // Verification: Check if a notification / delivery log was created
        $this->assertDatabaseHas('notification_deliveries', [
            'recipient_type' => 'member',
            'channel' => 'in_app'
        ]);
    }
}
