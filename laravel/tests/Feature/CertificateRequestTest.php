<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\Sacrament;
use App\Models\CertificateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CertificateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $priest;
    protected $vienna;
    protected $member;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Test Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Street 1',
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
    }

    public function test_request_requires_active_member(): void
    {
        // Set member status to pending
        $this->member->update(['membership_status' => 'pending']);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/certificate-requests', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'member_id' => $this->member->id,
                'certificate_type' => 'membership',
                'purpose' => 'Study abroad validation'
            ]);

        $response->assertStatus(400); // throws InvalidArgumentException
    }

    public function test_parish_review_moves_status(): void
    {
        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'certificate_type' => 'membership',
            'purpose' => 'Test',
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/parish-review");

        $response->assertStatus(200);
        $this->assertEquals('parish_review', $request->refresh()->status);
    }

    public function test_priest_approve_routes_to_diocese_review_if_configured(): void
    {
        // Set config to true for membership
        Config::set('settings.certificate_diocese_approval_required.membership', true);

        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'certificate_type' => 'membership',
            'purpose' => 'Test',
            'status' => 'parish_review'
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/priest-approve");

        $response->assertStatus(200);
        $this->assertEquals('diocese_review', $request->refresh()->status);
    }

    public function test_priest_approve_approves_directly_if_not_configured(): void
    {
        // Set config to false for baptism
        Config::set('settings.certificate_diocese_approval_required.baptism', false);

        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'certificate_type' => 'baptism',
            'purpose' => 'Test',
            'status' => 'parish_review'
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/priest-approve");

        $response->assertStatus(200);
        $this->assertEquals('approved', $request->refresh()->status);
    }

    public function test_rejection_stores_reason(): void
    {
        $request = CertificateRequest::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'requested_by' => $this->viennaAdmin->id,
            'member_id' => $this->member->id,
            'certificate_type' => 'membership',
            'purpose' => 'Test',
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/certificate-requests/{$request->id}/reject", [
                'reason' => 'Invalid details provided.'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('rejected', $request->refresh()->status);
        $this->assertEquals('Invalid details provided.', $request->rejection_reason);
    }
}
