<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\ProfileCorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser;
    protected $member;
    protected $family;

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
    }

    public function test_submit_profile_correction_request(): void
    {
        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/member-portal/correction-requests', [
                'member_id' => $this->member->id,
                'request_type' => 'contact_details',
                'requested_data' => [
                    'phone' => '+43660999999',
                    'occupation' => 'Engineer'
                ],
                'reason' => 'Changed job and phone'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('profile_correction_requests', [
            'member_id' => $this->member->id,
            'status' => 'submitted'
        ]);
    }

    public function test_admin_can_approve_and_auto_apply_corrections(): void
    {
        $request = ProfileCorrectionRequest::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->member->id,
            'requested_by' => $this->portalUser->id,
            'request_type' => 'contact_details',
            'current_data' => ['phone' => $this->member->phone],
            'requested_data' => ['phone' => '+43660888888', 'occupation' => 'Artist'],
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/member-portal/admin/correction-requests/{$request->id}/approve");

        $response->assertStatus(200);

        // Check request status updated
        $this->assertDatabaseHas('profile_correction_requests', [
            'id' => $request->id,
            'status' => 'approved'
        ]);

        // Check member model is auto-updated
        $this->member->refresh();
        $this->assertEquals('+43660888888', $this->member->phone);
        $this->assertEquals('Artist', $this->member->occupation);
    }
}
