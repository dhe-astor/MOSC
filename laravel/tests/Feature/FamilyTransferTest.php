<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\FamilyTransferRequest;
use App\Models\FamilyChurchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FamilyTransferTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $herneAdmin;
    protected $dioceseAdmin;
    protected $priest;
    protected $vienna;
    protected $herne;
    protected $family;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        $this->dioceseAdmin = User::where('email', 'admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_code' => 'MSOC-VIE-F-000001',
            'family_name' => 'Transferring Family',
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
            'member_code' => 'MSOC-VIE-M-000001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Add initial history
        FamilyChurchHistory::create([
            'family_id' => $this->family->id,
            'church_id' => $this->vienna->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_family_transfer_full_flow_with_diocese_approval(): void
    {
        // 1. Ensure config is true
        Config::set('settings.require_diocese_transfer_approval', true);

        // Initiate transfer request from Vienna to Herne
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/family-transfer-requests', [
                'family_id' => $this->family->id,
                'to_church_id' => $this->herne->id,
                'reason' => 'Moving to another city'
            ]);

        $response->assertStatus(201);
        $request = FamilyTransferRequest::first();
        $this->assertEquals('requested', $request->status);

        // 2. Source priest approves
        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/source-approve", [
                'remarks' => 'Approved by source vicar'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('source_approved', $request->refresh()->status);

        // 3. Target accepts should fail because diocese has not approved yet
        $response = $this->actingAs($this->herneAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/target-accept");
        $response->assertStatus(400); // throws ArgumentException

        // 4. Diocese Admin approves
        $response = $this->actingAs($this->dioceseAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/diocese-approve", [
                'remarks' => 'Approved by diocese admin'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('diocese_approved', $request->refresh()->status);

        // 5. Target accepts
        $response = $this->actingAs($this->herneAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/target-accept", [
                'remarks' => 'Accepted by target parish'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('target_accepted', $request->refresh()->status);

        // 6. Complete the transfer
        $response = $this->actingAs($this->herneAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/complete");

        $response->assertStatus(200);
        $this->assertEquals('completed', $request->refresh()->status);

        // 7. Verify database updates
        $this->family->refresh();
        $this->member->refresh();

        $this->assertEquals($this->herne->id, $this->family->church_id);
        $this->assertEquals($this->herne->id, $this->member->church_id);
        $this->assertEquals('MSOC-VIE-F-000001', $this->family->family_code);
        $this->assertEquals('MSOC-VIE-M-000001', $this->member->member_code);

        // Verify history logs
        $oldHistory = FamilyChurchHistory::where('family_id', $this->family->id)
            ->where('church_id', $this->vienna->id)
            ->first();
        $this->assertEquals('transferred', $oldHistory->status);
        $this->assertNotNull($oldHistory->end_date);

        $newHistory = FamilyChurchHistory::where('family_id', $this->family->id)
            ->where('church_id', $this->herne->id)
            ->first();
        $this->assertEquals('active', $newHistory->status);
    }

    public function test_family_transfer_bypasses_diocese_approval(): void
    {
        // 1. Ensure config is false
        Config::set('settings.require_diocese_transfer_approval', false);

        // Initiate transfer request
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/family-transfer-requests', [
                'family_id' => $this->family->id,
                'to_church_id' => $this->herne->id,
                'reason' => 'Moving to another city'
            ]);

        $response->assertStatus(201);
        $request = FamilyTransferRequest::first();

        // 2. Source priest approves
        $response = $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/source-approve");
        $response->assertStatus(200);

        // 3. Target accepts directly (since diocese approval is bypassed)
        $response = $this->actingAs($this->herneAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/target-accept");

        $response->assertStatus(200);
        $this->assertEquals('target_accepted', $request->refresh()->status);

        // 4. Complete
        $response = $this->actingAs($this->herneAdmin, 'sanctum')
            ->postJson("/api/v1/family-transfer-requests/{$request->id}/complete");

        $response->assertStatus(200);
        $this->assertEquals('completed', $request->refresh()->status);
        $this->assertEquals($this->herne->id, $this->family->refresh()->church_id);
    }
}
