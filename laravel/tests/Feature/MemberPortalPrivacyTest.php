<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPortalPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser1;
    protected $portalUser2;
    protected $family1;
    protected $family2;
    protected $member1;
    protected $member2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        
        $this->portalUser1 = User::create([
            'name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->viennaAdmin->default_diocese_id,
            'default_church_id' => $this->viennaAdmin->default_church_id,
        ]);

        $this->portalUser2 = User::create([
            'name' => 'Bob Member',
            'email' => 'bob.member@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->viennaAdmin->default_diocese_id,
            'default_church_id' => $this->viennaAdmin->default_church_id,
        ]);

        $this->family1 = Family::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_code' => 'FAM-PORTAL-1',
            'family_name' => 'Jane Family',
            'primary_phone' => '+43660111223',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->family2 = Family::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_code' => 'FAM-PORTAL-2',
            'family_name' => 'Bob Family',
            'primary_phone' => '+43660111224',
            'address_line_1' => 'Vienna St 2',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member1 = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family1->id,
            'member_code' => 'MEM-PORTAL-1',
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

        $this->member2 = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family2->id,
            'member_code' => 'MEM-PORTAL-2',
            'first_name' => 'Bob',
            'last_name' => 'Member',
            'full_name' => 'Bob Member',
            'email' => 'bob.member@example.com',
            'phone' => '+43660111224',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gender' => 'male',
            'date_of_birth' => '1988-10-10',
            'created_by' => $this->viennaAdmin->id
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $this->family1->diocese_id,
            'church_id' => $this->family1->church_id,
            'family_id' => $this->family1->id,
            'member_id' => $this->member1->id,
            'user_id' => $this->portalUser1->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $this->family2->diocese_id,
            'church_id' => $this->family2->church_id,
            'family_id' => $this->family2->id,
            'member_id' => $this->member2->id,
            'user_id' => $this->portalUser2->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);
    }

    public function test_unauthenticated_user_cannot_access_portal_apis(): void
    {
        $response = $this->getJson('/api/v1/member-portal/me');
        $response->assertStatus(401);
    }

    public function test_member_cannot_access_another_members_profile(): void
    {
        // User 1 tries to access Member 2 profile details
        $response = $this->actingAs($this->portalUser1, 'sanctum')
            ->getJson("/api/v1/member-portal/members/{$this->member2->id}");

        $response->assertStatus(403); // Access denied
    }

    public function test_member_cannot_download_another_families_certificate(): void
    {
        $pdfPath = 'certificates/cert-bob.pdf';
        Storage::put($pdfPath, 'BOB CERTIFICATE PDF');

        $template = \App\Models\CertificateTemplate::first();
        $cert = Certificate::create([
            'diocese_id' => $this->family2->diocese_id,
            'church_id' => $this->family2->church_id,
            'family_id' => $this->family2->id,
            'member_id' => $this->member2->id,
            'certificate_template_id' => $template->id,
            'certificate_number' => 'CERT-2026-BOB',
            'certificate_type' => 'membership',
            'issued_date' => now()->toDateString(),
            'verification_code' => 'V-CODE-BOB',
            'pdf_path' => $pdfPath,
            'issued_by' => $this->viennaAdmin->id,
            'issued_at' => now(),
            'status' => 'active'
        ]);

        // User 1 tries to download Bob's certificate
        $response = $this->actingAs($this->portalUser1, 'sanctum')
            ->getJson("/api/v1/member-portal/certificates/{$cert->id}/download");

        $response->assertStatus(403); // Access denied
    }
}
