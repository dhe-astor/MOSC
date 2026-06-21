<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\PriestTransferRequest;
use App\Services\ClergyTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_create_and_approve_transfer(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();

        // Create transfer request
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/clergy/transfers', [
            'priest_profile_id' => $priest->id,
            'to_church_id' => $rome->id,
            'new_assignment_role' => 'visiting_priest',
            'effective_date' => '2026-07-01',
            'transfer_type' => 'new_assignment',
            'appointment_reference' => 'REF-001'
        ]);

        $response->assertStatus(201);
        $transferId = $response->json('data.id');

        $this->assertDatabaseHas('priest_transfer_requests', [
            'id' => $transferId,
            'status' => 'draft',
            'transfer_type' => 'new_assignment'
        ]);

        // Approve transfer
        $approveResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/transfers/{$transferId}/approve");
        $approveResponse->assertStatus(200);

        $this->assertDatabaseHas('priest_transfer_requests', [
            'id' => $transferId,
            'status' => 'approved'
        ]);
    }

    public function test_complete_transfer_request(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        // Create an active assignment at Vienna
        $assignment = PriestChurchAssignment::create([
            'diocese_id' => $vienna->diocese_id,
            'priest_profile_id' => $priest->id,
            'member_id' => $priest->member_id,
            'church_id' => $vienna->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2026-06-01',
            'status' => 'active'
        ]);

        // Create and approve transfer request
        $transfer = PriestTransferRequest::create([
            'diocese_id' => $vienna->diocese_id,
            'priest_profile_id' => $priest->id,
            'from_church_id' => $vienna->id,
            'to_church_id' => $rome->id,
            'from_assignment_id' => $assignment->id,
            'new_assignment_role' => 'assistant_vicar',
            'effective_date' => '2026-07-01',
            'transfer_type' => 'transfer',
            'status' => 'approved',
            'requested_by' => $admin->id
        ]);

        // Complete the transfer
        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/transfers/{$transfer->id}/complete");
        $response->assertStatus(200);

        // Verify transfer status is completed
        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);

        // Verify old assignment is ended
        $assignment->refresh();
        $this->assertEquals('ended', $assignment->status);
        $this->assertEquals('2026-06-30', $assignment->end_date->toDateString()); // Day before effective date

        // Verify new assignment is active
        $newAssignment = PriestChurchAssignment::where('priest_profile_id', $priest->id)
            ->where('church_id', $rome->id)
            ->where('assignment_role', 'assistant_vicar')
            ->first();
        $this->assertNotNull($newAssignment);
        $this->assertEquals('active', $newAssignment->status);
        $this->assertEquals('2026-07-01', $newAssignment->start_date->toDateString());
    }
}
