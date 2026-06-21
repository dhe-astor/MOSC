<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $vienna;
    protected $herne;
    protected $donationCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();
        $this->donationCategory = FinanceCategory::where('category_type', 'donation')->first();

        // Assign Parish Treasurer role to Vienna Admin for testing scoping
        $this->viennaAdmin->assignRole('Parish Treasurer');
        // Assign Parish Treasurer role to Herne Admin for testing scoping
        $this->herneAdmin->assignRole('Parish Treasurer');

        // Create Vienna donation
        Donation::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'finance_category_id' => $this->donationCategory->id,
            'donor_name' => 'Vienna Donor',
            'donation_type' => 'general',
            'amount' => 120.00,
            'currency' => 'EUR',
            'payment_method' => 'cash',
            'received_date' => date('Y-m-d'),
            'status' => 'received',
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Create Herne donation
        Donation::create([
            'diocese_id' => $this->herne->diocese_id,
            'church_id' => $this->herne->id,
            'finance_category_id' => $this->donationCategory->id,
            'donor_name' => 'Herne Donor',
            'donation_type' => 'general',
            'amount' => 200.00,
            'currency' => 'EUR',
            'payment_method' => 'cash',
            'received_date' => date('Y-m-d'),
            'status' => 'received',
            'created_by' => $this->herneAdmin->id,
        ]);
    }

    public function test_parish_treasurer_scoping_isolation(): void
    {
        // Vienna Treasurer lists donations - should see ONLY Vienna's donation (120)
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/finance/donations');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Vienna Donor', $data[0]['donor_name']);

        // Vienna Treasurer attempts to view Herne's donation by ID directly - should fail (403)
        $herneDonation = Donation::where('donor_name', 'Herne Donor')->first();
        
        $failResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/finance/donations/{$herneDonation->id}");

        $failResponse->assertStatus(403);
    }

    public function test_parish_treasurer_scoping_isolation_v2_income_and_expenses(): void
    {
        $viennaMoneyAccount = \App\Models\FinanceMoneyAccount::create([
            'church_id' => $this->vienna->id,
            'code' => 'VIENNA-CASH',
            'name' => 'Vienna Cash',
            'type' => 'cash',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $herneMoneyAccount = \App\Models\FinanceMoneyAccount::create([
            'church_id' => $this->herne->id,
            'code' => 'HERNE-CASH',
            'name' => 'Herne Cash',
            'type' => 'cash',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Create Vienna income header
        $viennaIncome = \App\Models\FinanceIncomeHeader::create([
            'church_id' => $this->vienna->id,
            'money_account_id' => $viennaMoneyAccount->id,
            'income_date' => now()->toDateString(),
            'reference_no' => 'INC-VIENNA-SCOPING',
            'payment_method' => 'cash',
            'status' => 'draft',
            'created_by' => $this->viennaAdmin->id,
        ]);

        // Create Herne income header
        $herneIncome = \App\Models\FinanceIncomeHeader::create([
            'church_id' => $this->herne->id,
            'money_account_id' => $herneMoneyAccount->id,
            'income_date' => now()->toDateString(),
            'reference_no' => 'INC-HERNE-SCOPING',
            'payment_method' => 'cash',
            'status' => 'draft',
            'created_by' => $this->herneAdmin->id,
        ]);

        // Vienna Treasurer lists income-headers - should see ONLY Vienna's
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/finance/income-headers');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Find if any of the items belong to Herne
        $herneItems = array_filter($data, function($item) use ($herneIncome) {
            return $item['id'] === $herneIncome->id;
        });
        $this->assertCount(0, $herneItems);

        // Vienna Treasurer attempts to view Herne's income header by ID directly - should fail (403)
        $failResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson("/api/v1/finance/income-headers/{$herneIncome->id}");

        $failResponse->assertStatus(403);
    }
}
