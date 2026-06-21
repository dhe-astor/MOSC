<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\MinistryOrganization;
use App\Models\MinistryUnit;
use App\Models\MinistryMembership;
use App\Models\MinistryActivity;
use App\Models\MinistryActivityAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $youthOrg;
    protected $youthUnit;
    protected $membership;
    protected $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->youthOrg = MinistryOrganization::where('slug', 'msoc-europe-youth-association')->first();

        $this->youthUnit = MinistryUnit::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_organization_id' => $this->youthOrg->id,
            'unit_name' => 'Vienna Youth',
            'unit_level' => 'parish',
            'created_by' => $this->superAdmin->id,
        ]);

        $family = Family::create([
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

        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'approved_at' => now(),
            'date_of_birth' => '2000-06-15',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $this->membership = MinistryMembership::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_unit_id' => $this->youthUnit->id,
            'member_id' => $member->id,
            'joined_date' => '2026-06-15',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->activity = MinistryActivity::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'ministry_unit_id' => $this->youthUnit->id,
            'title' => 'Youth Meeting',
            'activity_type' => 'meeting',
            'start_datetime' => '2026-06-15 19:00:00',
            'timezone' => 'Europe/Vienna',
            'status' => 'published',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_mark_activity_attendance(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/ministry-attendance/mark', [
                'ministry_activity_id' => $this->activity->id,
                'records' => [
                    [
                        'ministry_membership_id' => $this->membership->id,
                        'status' => 'present',
                        'remarks' => 'On time',
                    ]
                ]
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ministry_activity_attendance', [
            'ministry_activity_id' => $this->activity->id,
            'ministry_membership_id' => $this->membership->id,
            'status' => 'present',
        ]);
    }
}
