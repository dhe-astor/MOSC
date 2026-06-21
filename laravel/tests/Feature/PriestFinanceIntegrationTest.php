<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\FinancePriestPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestFinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_priest_payment(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = PriestProfile::first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        // Assign Parish Treasurer role to admin user to ensure finance permissions
        $admin->assignRole('Parish Treasurer');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/finance/priest-payments', [
            'church_id' => $vienna->id,
            'priest_id' => $priest->id,
            'payment_date' => '2026-06-15',
            'type' => 'stipend',
            'amount' => 1200.00,
            'description' => 'Monthly Stipend for Vienna Priest'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('finance_priest_payments', [
            'priest_profile_id' => $priest->id,
            'church_id' => $vienna->id,
            'amount' => 1200.00,
            'type' => 'stipend'
        ]);
    }
}
