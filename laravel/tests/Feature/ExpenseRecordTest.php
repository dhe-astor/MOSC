<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\ExpenseRecord;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseRecordTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->expenseCategory = FinanceCategory::where('category_type', 'expense')->first();
    }

    public function test_create_submit_approve_and_pay_expense(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/expenses', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'finance_category_id' => $this->expenseCategory->id,
                'title' => 'Hall Rent June',
                'amount' => 300.00,
                'currency' => 'EUR',
                'expense_date' => date('Y-m-d'),
                'payment_method' => 'bank_transfer',
                'vendor_name' => 'Vienna Hall Owner',
                'bill_number' => 'BILL-1029',
                'status' => 'draft'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $expenseId = $response->json('data.id');

        $submitResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit");
        $submitResponse->assertStatus(200)->assertJsonPath('data.status', 'submitted');

        $approveResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve");
        $approveResponse->assertStatus(200)->assertJsonPath('data.status', 'approved');

        $payResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/expenses/{$expenseId}/mark-paid");
        $payResponse->assertStatus(200)->assertJsonPath('data.status', 'paid');
    }
}
