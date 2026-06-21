<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\Certificate;
use App\Models\CertificateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPortalCertificateTest extends TestCase
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
        Storage::fake('local');

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

    public function test_submit_certificate_request(): void
    {
        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/member-portal/certificate-requests', [
                'member_id' => $this->member->id,
                'certificate_type' => 'membership',
                'purpose' => 'Study Abroad'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certificate_requests', [
            'member_id' => $this->member->id,
            'certificate_type' => 'membership',
            'status' => 'submitted'
        ]);
    }

    public function test_download_issued_certificate(): void
    {
        $pdfPath = 'certificates/cert-001.pdf';
        Storage::put($pdfPath, 'CERTIFICATE PDF');

        $template = \App\Models\CertificateTemplate::first();
        $cert = Certificate::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->member->id,
            'certificate_template_id' => $template->id,
            'certificate_number' => 'CERT-2026-001',
            'certificate_type' => 'membership',
            'issued_date' => now()->toDateString(),
            'verification_code' => 'V-CODE-001',
            'pdf_path' => $pdfPath,
            'issued_by' => $this->viennaAdmin->id,
            'issued_at' => now(),
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson("/api/v1/member-portal/certificates/{$cert->id}/download");

        $response->assertStatus(200);
    }
}
