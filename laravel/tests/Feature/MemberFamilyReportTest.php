<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberFamilyReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_member_report_masks_contacts_by_default(): void
    {
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

        Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'gender' => 'male',
            'email' => 'john.doe@example.com',
            'phone' => '+491234567890',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Vienna admin does not have view_unmasked_report_contacts by default
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'members_families_list'
            ]);

        $response->assertStatus(200);
        $members = $response->json('data.data');
        $this->assertNotEmpty($members);
        $johnDoe = collect($members)->first(function ($m) {
            return str_contains($m['Email'], 'j***@example.com') || str_contains($m['Email'], 'john.doe');
        });
        $this->assertNotNull($johnDoe, 'Could not find John Doe in the report data');
        $this->assertEquals('j***@example.com', $johnDoe['Email']);
        $this->assertEquals('+49*******890', $johnDoe['Phone']);
    }

    public function test_member_report_does_not_mask_for_permitted_user(): void
    {
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

        Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'gender' => 'male',
            'email' => 'john.doe@example.com',
            'phone' => '+491234567890',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Super Admin has all permissions including view_unmasked_report_contacts
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'members_families_list'
            ]);

        $response->assertStatus(200);
        $members = $response->json('data.data');
        $this->assertNotEmpty($members);
        $johnDoe = collect($members)->first(function ($m) {
            return str_contains($m['Email'], 'john.doe@example.com');
        });
        $this->assertNotNull($johnDoe, 'Could not find John Doe in the report data');
        $this->assertEquals('john.doe@example.com', $johnDoe['Email']);
        $this->assertEquals('+491234567890', $johnDoe['Phone']);
    }
}
