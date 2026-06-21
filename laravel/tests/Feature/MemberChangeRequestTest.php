<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $priest;
    protected $vienna;
    protected $family;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Hauptstrasse 45',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'OriginalName',
            'last_name' => 'Doe',
            'full_name' => 'OriginalName Doe',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'phone' => '+436645555555',
            'occupation' => 'Engineer',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_updating_non_sensitive_field_applies_immediately(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->putJson("/api/v1/members/{$this->member->id}", [
                'first_name' => 'OriginalName',
                'last_name' => 'Doe',
                'relationship_to_head' => 'head',
                'phone' => '+436647777777', // Changed
                'occupation' => 'Doctor', // Changed
            ]);

        $response->assertStatus(200);

        // Member is updated immediately
        $this->member->refresh();
        $this->assertEquals('+436647777777', $this->member->phone);
        $this->assertEquals('Doctor', $this->member->occupation);

        // No change request created
        $this->assertDatabaseCount('member_change_requests', 0);
    }

    public function test_updating_sensitive_field_creates_pending_change_request(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->putJson("/api/v1/members/{$this->member->id}", [
                'first_name' => 'SensitiveChanged', // Sensitive
                'last_name' => 'Doe',
                'relationship_to_head' => 'head',
                'phone' => '+436645555555',
                'occupation' => 'Engineer',
            ]);

        $response->assertStatus(200);

        // Member name is NOT updated immediately
        $this->member->refresh();
        $this->assertEquals('OriginalName', $this->member->first_name);

        // Change request is created
        $this->assertDatabaseHas('member_change_requests', [
            'member_id' => $this->member->id,
            'change_type' => 'profile_update',
            'status' => 'submitted'
        ]);

        $request = MemberChangeRequest::first();
        $this->assertEquals('OriginalName', $request->old_data['first_name']);
        $this->assertEquals('SensitiveChanged', $request->new_data['first_name']);
    }

    public function test_priest_can_approve_change_request(): void
    {
        // Setup change request
        $request = MemberChangeRequest::create([
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'change_type' => 'profile_update',
            'old_data' => ['first_name' => 'OriginalName', 'full_name' => 'OriginalName Doe'],
            'new_data' => ['first_name' => 'ApprovedName', 'full_name' => 'ApprovedName Doe'],
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/member-change-requests/{$request->id}/approve");

        $response->assertStatus(200);

        // Member profile updated
        $this->member->refresh();
        $this->assertEquals('ApprovedName', $this->member->first_name);
        $this->assertEquals('ApprovedName Doe', $this->member->full_name);

        // Status is updated
        $this->assertEquals('approved', $request->refresh()->status);
        $this->assertNotNull($request->approved_at);
        $this->assertEquals($this->priest->id, $request->approved_by);
    }

    public function test_priest_can_reject_change_request(): void
    {
        $request = MemberChangeRequest::create([
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'change_type' => 'profile_update',
            'old_data' => ['first_name' => 'OriginalName'],
            'new_data' => ['first_name' => 'RejectedName'],
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/member-change-requests/{$request->id}/reject", [
                'rejection_reason' => 'Invalid document proof'
            ]);

        $response->assertStatus(200);

        // Member name unchanged
        $this->member->refresh();
        $this->assertEquals('OriginalName', $this->member->first_name);

        // Status is updated
        $this->assertEquals('rejected', $request->refresh()->status);
        $this->assertEquals('Invalid document proof', $request->rejection_reason);
    }
}
