<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\MemberPortalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPortalDocumentTest extends TestCase
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

    public function test_upload_private_document_under_limit(): void
    {
        $file = UploadedFile::fake()->create('id_card.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/member-portal/documents', [
                'file' => $file,
                'member_id' => $this->member->id,
                'document_type' => 'id_proof'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('member_portal_documents', [
            'member_id' => $this->member->id,
            'original_file_name' => 'id_card.pdf',
            'status' => 'uploaded'
        ]);

        $doc = MemberPortalDocument::first();
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_cannot_upload_unsupported_mime(): void
    {
        $file = UploadedFile::fake()->create('malicious.sh', 5, 'text/plain');

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/member-portal/documents', [
                'file' => $file,
                'member_id' => $this->member->id,
                'document_type' => 'id_proof'
            ]);

        $response->assertStatus(422); // Validation failed (mimes check)
    }

    public function test_authorized_download_works(): void
    {
        $filePath = 'private/portal_documents/1/1/doc.pdf';
        Storage::disk('local')->put($filePath, 'PDF CONTENT');

        $doc = MemberPortalDocument::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->member->id,
            'uploaded_by' => $this->portalUser->id,
            'document_type' => 'id_proof',
            'file_path' => $filePath,
            'original_file_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => 'uploaded'
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson("/api/v1/member-portal/documents/{$doc->id}/download");

        $response->assertStatus(200);
    }
}
