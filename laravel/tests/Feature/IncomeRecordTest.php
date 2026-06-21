<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\IncomeRecord;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeRecordTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->incomeCategory = FinanceCategory::where('category_type', 'income')->first();
    }

    public function test_create_and_submit_income(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/income', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'finance_category_id' => $this->incomeCategory->id,
                'title' => 'Parish Fundraiser',
                'amount' => 500.00,
                'currency' => 'EUR',
                'payment_method' => 'bank_transfer',
                'income_date' => date('Y-m-d'),
                'status' => 'draft'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $incomeId = $response->json('data.id');

        $submitResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/income/{$incomeId}/submit");

        $submitResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }
}
