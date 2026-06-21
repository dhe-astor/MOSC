<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GdprConsentTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $priest;
    protected $vienna;
    protected $family;

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
    }

    public function test_gdpr_directory_flags_default_to_false(): void
    {
        // Add member without explicit GDPR flags
        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Default',
            'last_name' => 'User',
            'full_name' => 'Default User',
            'relationship_to_head' => 'spouse',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $member->refresh();

        $this->assertFalse((bool)$member->gdpr_consent);
        $this->assertFalse((bool)$member->communication_consent);
        $this->assertFalse((bool)$member->show_in_directory);
    }

    public function test_children_profiles_are_excluded_from_public_listings(): void
    {
        // 1. Adult member with show_in_directory = true
        $adult = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Adult',
            'last_name' => 'Member',
            'full_name' => 'Adult Member',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'show_in_directory' => true,
            'created_by' => $this->viennaAdmin->id
        ]);

        // 2. Child member (age 10) with show_in_directory = true (should be blocked / excluded)
        $child = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Child',
            'last_name' => 'Member',
            'full_name' => 'Child Member',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'date_of_birth' => Carbon::now()->subYears(10)->format('Y-m-d'),
            'show_in_directory' => true,
            'created_by' => $this->viennaAdmin->id
        ]);

        // 3. Query directory endpoint (in MemberController index with filter directory=true)
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/members?directory=true');

        $response->assertStatus(200);
        $members = $response->json('data');

        $ids = collect($members)->pluck('id');

        // Adult should be in directory
        $this->assertTrue($ids->contains($adult->id));
        // Child MUST be excluded from standard directory list regardless of show_in_directory flag
        $this->assertFalse($ids->contains($child->id));
    }
}
