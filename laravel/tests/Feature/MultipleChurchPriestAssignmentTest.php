<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Services\PriestAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleChurchPriestAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_assign_priest_to_multiple_churches(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $rome = Church::where('short_name', 'Rome')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        // Assign to Rome
        $response1 = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/assignments", [
            'priest_profile_id' => $priest->id,
            'church_id' => $rome->id,
            'assignment_role' => 'visiting_priest',
            'start_date' => '2026-06-01',
            'is_primary' => false
        ]);
        $response1->assertStatus(201);

        // Assign to Vienna as assistant vicar (to avoid primary vicar conflict if Rome/Vienna already has one)
        $response2 = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/assignments", [
            'priest_profile_id' => $priest->id,
            'church_id' => $vienna->id,
            'assignment_role' => 'assistant_vicar',
            'start_date' => '2026-06-01',
            'is_primary' => false
        ]);
        $response2->assertStatus(201);

        // Verify active assignments count
        $activeAssignments = PriestAssignmentService::getActiveAssignmentsForPriest($priest->id);
        $this->assertGreaterThanOrEqual(2, $activeAssignments->count());
        $churchIds = $activeAssignments->pluck('church_id');
        $this->assertTrue($churchIds->contains($rome->id));
        $this->assertTrue($churchIds->contains($vienna->id));
    }
}
