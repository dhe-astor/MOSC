<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\CertificateRequest;
use App\Models\CertificateTemplate;
use App\Models\Certificate;
use App\Models\Priest;
use App\Models\PriestAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $priestUser;
    protected $vienna;
    protected $member;
    protected $family;
    protected $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Storage::fake('local');

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->priestUser = User::where('email', 'priest@msoc-europe.org')->first();

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

        $this->template = CertificateTemplate::where('certificate_type', 'membership')->first();

        // Assign Priest as primary vicar to Vienna
        $priestModel = Priest::where('email', $this->priestUser->email)->first();
        
        // delete any seeded assignment to avoid unique keys
        PriestAssignment::query()->delete();

        PriestAssignment::create([
            'priest_id' => $priestModel->id,
            'church_id' => $this->vienna->id,
            'role' => 'vicar',
            'assignment_start_date' => now()->subDays(5),
            'is_primary' => true,
            'status' => 'active'
        ]);
    }

    public function test_approved_request_can_issue_certificate(): void
    {
        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'certificate_type' => 'membership',
            'purpose' => 'Test issue',
            'status' => 'approved',
            'priest_approved_by' => $this->priestUser->id,
            'priest_approved_at' => now()
        ]);

        $response = $this->actingAs($this->priestUser, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/issue", [
                'template_id' => $this->template->id
            ]);

        $response->assertStatus(201);
        $certificateId = $response->json('data.id');

        $this->assertDatabaseHas('certificates', [
            'id' => $certificateId,
            'certificate_request_id' => $request->id,
            'certificate_type' => 'membership',
            'status' => 'active'
        ]);

        // Verify request updated to issued
        $this->assertEquals('issued', $request->refresh()->status);
        $this->assertEquals($certificateId, $request->certificate_id);

        $certificate = Certificate::find($certificateId);
        $this->assertNotNull($certificate);
        
        // Verify PDF file was written to fake storage
        Storage::assertExists($certificate->pdf_path);

        // Verify unique certificate number format
        $this->assertStringStartsWith('MSOC-EU-MEM-' . date('Y') . '-', $certificate->certificate_number);

        // Verify verification code format: MSOC-V-XXXX-XXXX
        $this->assertMatchesRegularExpression('/^MSOC-V-[A-Z3-9]{4}-[A-Z3-9]{4}$/', $certificate->verification_code);
    }

    public function test_cannot_issue_certificate_twice_for_same_request(): void
    {
        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'certificate_type' => 'membership',
            'purpose' => 'Test duplicate issue',
            'status' => 'approved'
        ]);

        // First issue
        $this->actingAs($this->priestUser, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/issue", [
                'template_id' => $this->template->id
            ])
            ->assertStatus(201);

        // Try second issue should fail
        $response = $this->actingAs($this->priestUser, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/issue", [
                'template_id' => $this->template->id
            ]);

        $response->assertStatus(400); // Bad Request / InvalidArgumentException
    }
}
