<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\CertificateTemplate;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $member;
    protected $family;
    protected $template;
    protected $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'John Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Vienna Hauptstrasse 12',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->template = CertificateTemplate::first();

        $this->certificate = Certificate::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'certificate_template_id' => $this->template->id,
            'certificate_number' => 'MSOC-EU-MEM-2026-000001',
            'certificate_type' => 'membership',
            'issued_date' => now(),
            'issued_by' => $this->superAdmin->id,
            'pdf_path' => 'private/certificates/MSOC-V-ABCD-EFGH.pdf',
            'verification_code' => 'MSOC-V-ABCD-EFGH',
            'public_verification_enabled' => true,
            'status' => 'active'
        ]);
    }

    public function test_public_verification_returns_only_safe_metadata_unauthenticated(): void
    {
        $response = $this->getJson("/api/v1/certificates/verify/MSOC-V-ABCD-EFGH");

        $response->assertStatus(200)
            ->assertJsonPath('data.certificate_number', 'MSOC-EU-MEM-2026-000001')
            ->assertJsonPath('data.certificate_type', 'membership')
            ->assertJsonPath('data.issuing_church', $this->vienna->name)
            ->assertJsonPath('data.status', 'active');

        // Verify sensitive fields are hidden
        $this->assertNull($response->json('data.member'));
        $this->assertNull($response->json('data.member_id'));
        $this->assertNull($response->json('data.family_id'));
        $this->assertNull($response->json('data.pdf_path'));
    }

    public function test_cancelled_certificate_verification_shows_cancelled(): void
    {
        $this->certificate->update(['status' => 'cancelled']);

        $response = $this->getJson("/api/v1/certificates/verify/MSOC-V-ABCD-EFGH");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_invalid_verification_code_returns_404(): void
    {
        $response = $this->getJson("/api/v1/certificates/verify/MSOC-V-FAKE-CODE");

        $response->assertStatus(404);
    }
}
