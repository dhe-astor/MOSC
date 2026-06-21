<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $donationCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->donationCategory = FinanceCategory::where('category_type', 'donation')->first();
    }

    public function test_create_and_receive_donation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/donations', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'finance_category_id' => $this->donationCategory->id,
                'donor_name' => 'John Doe',
                'donor_email' => 'john@doe.com',
                'donor_phone' => '+436640001112',
                'donation_type' => 'general',
                'amount' => 100.00,
                'currency' => 'EUR',
                'payment_method' => 'cash',
                'received_date' => date('Y-m-d'),
                'status' => 'pending',
                'notes' => 'General Sunday donation'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $donationId = $response->json('data.id');

        $receiveResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/donations/{$donationId}/mark-received");

        $receiveResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'received');
        
        $this->assertNotNull($receiveResponse->json('data.receipt_id'));
    }
}
