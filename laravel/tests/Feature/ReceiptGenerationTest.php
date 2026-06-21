<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Receipt;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vienna;
    protected $donationCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->donationCategory = FinanceCategory::where('category_type', 'donation')->first();
    }

    public function test_generate_unique_receipt_numbers_and_prevent_duplicates(): void
    {
        $donation = Donation::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'finance_category_id' => $this->donationCategory->id,
            'donor_name' => 'Jane Smith',
            'donation_type' => 'thanksgiving',
            'amount' => 150.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'received_date' => date('Y-m-d'),
            'status' => 'received',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/donations/{$donation->id}/generate-receipt");

        $response->assertStatus(200);
        $receiptNumber1 = $response->json('data.receipt_number');
        $year = date('Y');
        $this->assertStringContainsString("-{$year}-000001", $receiptNumber1);

        // Try duplicate generation
        $duplicateResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/donations/{$donation->id}/generate-receipt");
        
        $duplicateResponse->assertStatus(500); 
    }
}
