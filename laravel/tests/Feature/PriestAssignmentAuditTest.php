<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestAssignmentAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_assigning_priest_writes_to_audit_logs(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/clergy/assignments', [
            'priest_profile_id' => $priest->id,
            'church_id' => $rome->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2026-06-01',
            'is_primary' => false
        ]);

        $response->assertStatus(201);
        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'assignments',
            'action' => 'priest_assignment_created',
            'auditable_type' => PriestChurchAssignment::class,
            'auditable_id' => $assignmentId,
            'user_id' => $admin->id
        ]);
    }
}
