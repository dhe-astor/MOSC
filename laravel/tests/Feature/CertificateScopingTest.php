<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\CertificateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $vienna;
    protected $herne;
    protected $viennaMember;
    protected $herneMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();

        // Create families
        $viennaFamily = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $herneFamily = Family::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_name' => 'Herne Family',
            'primary_phone' => '+491760001111',
            'address_line_1' => 'Herne St 1',
            'city' => 'Herne',
            'membership_status' => 'active',
            'created_by' => $this->herneAdmin->id
        ]);

        // Create members
        $this->viennaMember = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $viennaFamily->id,
            'first_name' => 'V',
            'last_name' => 'Member',
            'full_name' => 'V Member',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->herneMember = Member::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'family_id' => $herneFamily->id,
            'first_name' => 'H',
            'last_name' => 'Member',
            'full_name' => 'H Member',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->herneAdmin->id
        ]);

        // Create requests
        CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->viennaMember->id,
            'certificate_type' => 'membership',
            'purpose' => 'Vienna Request',
            'status' => 'submitted'
        ]);

        CertificateRequest::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'requested_by' => $this->herneAdmin->id,
            'member_id' => $this->herneMember->id,
            'certificate_type' => 'membership',
            'purpose' => 'Herne Request',
            'status' => 'submitted'
        ]);
    }

    public function test_diocese_admin_can_see_all_certificate_requests(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/certificate-requests');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(2, count($data));
    }

    public function test_parish_admin_can_see_only_own_parish_requests(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/certificate-requests');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, count($data));
        $this->assertEquals('Vienna Request', $data[0]['purpose']);
    }
}
