<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $herneAdmin;
    protected $priest;
    protected $vienna;
    protected $herne;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();

        // Create a test family in Vienna
        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Test Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Hauptstrasse 45',
            'city' => 'Vienna',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_parish_admin_can_create_member_for_family_in_own_parish(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/members', [
                'family_id' => $this->family->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'relationship_to_head' => 'head',
                'date_of_birth' => '1990-01-01',
                'phone' => '+436640002222',
                'email' => 'john.doe@example.com',
                'student_status' => false,
                'marital_status' => 'single',
                'address_same_as_family' => true,
                'gdpr_consent' => true,
                'communication_consent' => true,
                'show_in_directory' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.full_name', 'John Doe')
            ->assertJsonPath('data.membership_status', 'pending');

        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'family_id' => $this->family->id,
            'membership_status' => 'pending'
        ]);
    }

    public function test_parish_admin_cannot_create_member_for_family_in_another_parish(): void
    {
        // Create a family in Herne
        $herneFamily = Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family',
            'primary_phone' => '+491769999999',
            'address_line_1' => 'Herne St 1',
            'city' => 'Herne',
            'membership_status' => 'pending',
            'created_by' => $this->herneAdmin->id
        ]);

        // Vienna Admin tries to add member to Herne Family
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/members', [
                'family_id' => $herneFamily->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'relationship_to_head' => 'head',
                'date_of_birth' => '1990-01-01',
                'student_status' => false,
                'marital_status' => 'single',
                'address_same_as_family' => true,
                'gdpr_consent' => true,
                'communication_consent' => true,
                'show_in_directory' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_priest_can_approve_member_in_assigned_parish(): void
    {
        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Alice',
            'last_name' => 'Doe',
            'full_name' => 'Alice Doe',
            'relationship_to_head' => 'spouse',
            'membership_status' => 'pending',
            'created_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/members/{$member->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals('active', $member->refresh()->membership_status);
        $this->assertNotNull($member->member_code);
        $this->assertStringStartsWith('MSOC-', $member->member_code);
    }

    public function test_soft_delete_member(): void
    {
        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Bob',
            'last_name' => 'Doe',
            'full_name' => 'Bob Doe',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->deleteJson("/api/v1/members/{$member->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }
}
