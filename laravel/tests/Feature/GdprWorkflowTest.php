<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\GdprRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $member;
    protected $church;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed();

        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->admin->two_factor_enabled = true;
        $this->admin->two_factor_last_verified_at = now();
        $this->admin->save();

        $this->church = Church::first();

        $this->family = Family::create([
            'diocese_id' => $this->church->diocese_id,
            'church_id' => $this->church->id,
            'family_name' => 'GDPR Family',
            'primary_phone' => '+436640002222',
            'address_line_1' => 'Main St 10',
            'city' => 'City',
            'membership_status' => 'active',
            'created_by' => $this->admin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->church->diocese_id,
            'church_id' => $this->church->id,
            'family_id' => $this->family->id,
            'first_name' => 'John',
            'last_name' => 'GDPR',
            'full_name' => 'John GDPR',
            'email' => 'john.gdpr@example.com',
            'phone' => '+4912345678',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gdpr_consent' => true,
            'communication_consent' => true,
            'show_in_directory' => true,
            'created_by' => $this->admin->id
        ]);
    }

    public function test_gdpr_export_request_flow(): void
    {
        // 1. Create request
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/gdpr/export-request', [
                'member_id' => $this->member->id
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('gdpr_requests', [
            'member_id' => $this->member->id,
            'request_type' => 'export',
            'status' => 'pending'
        ]);

        $requestId = $response->json('data.id');

        // 2. Approve request (requires recent 2FA verified token)
        \Laravel\Sanctum\Sanctum::actingAs($this->admin, ['2fa_verified']);
        $approveResponse = $this->postJson("/api/v1/gdpr/requests/{$requestId}/approve");

        $approveResponse->assertStatus(200);
        $this->assertDatabaseHas('gdpr_requests', [
            'id' => $requestId,
            'status' => 'completed'
        ]);

        // Verify json file generated in private storage
        $filePath = $approveResponse->json('data.details.file_path');
        $this->assertNotNull($filePath);
        Storage::assertExists($filePath);
    }

    public function test_gdpr_anonymization_request_flow(): void
    {
        // 1. Create request
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/gdpr/anonymization-request', [
                'member_id' => $this->member->id,
                'action_type' => 'anonymize'
            ]);

        $response->assertStatus(200);
        $requestId = $response->json('data.id');

        // 2. Approve request
        \Laravel\Sanctum\Sanctum::actingAs($this->admin, ['2fa_verified']);
        $approveResponse = $this->postJson("/api/v1/gdpr/requests/{$requestId}/approve");

        $approveResponse->assertStatus(200);
        
        // Verify member is anonymized and NOT hard-deleted
        $this->member->refresh();
        $this->assertEquals('Anonymized', $this->member->first_name);
        $this->assertEquals('Member', $this->member->last_name);
        $this->assertNull($this->member->email);
        $this->assertNull($this->member->phone);
        $this->assertFalse($this->member->gdpr_consent);
        $this->assertFalse($this->member->show_in_directory);
        $this->assertEquals('anonymized', $this->member->membership_status);
        $this->assertNull($this->member->deleted_at); // No hard delete
    }

    public function test_gdpr_consent_and_retention_summaries(): void
    {
        $response1 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/gdpr/consent-summary');

        $response1->assertStatus(200);
        $response1->assertJsonStructure([
            'success',
            'data' => [
                'total_members',
                'gdpr_consent_count',
                'communication_consent_count'
            ]
        ]);

        $response2 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/gdpr/data-retention-summary');

        $response2->assertStatus(200);
        $response2->assertJsonStructure([
            'success',
            'data' => [
                'retention_rules',
                'expired_counts'
            ]
        ]);
    }
}
