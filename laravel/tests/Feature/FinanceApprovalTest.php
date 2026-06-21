<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\ExpenseRecord;
use App\Models\FinanceApproval;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vienna;
    protected $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->expenseCategory = FinanceCategory::where('category_type', 'expense')->first();
    }

    public function test_approval_list_and_resolution(): void
    {
        $expense = ExpenseRecord::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'finance_category_id' => $this->expenseCategory->id,
            'title' => 'Hall Rent July',
            'amount' => 300.00,
            'currency' => 'EUR',
            'expense_date' => date('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'status' => 'draft',
            'submitted_by' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
        ]);

        // Submit triggers approval request
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/expenses/{$expense->id}/submit");

        $approval = FinanceApproval::where('approvable_type', ExpenseRecord::class)
            ->where('approvable_id', $expense->id)
            ->first();
        
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);

        // Resolve via approval endpoint
        $resolveResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/approvals/{$approval->id}/approve", [
                'remarks' => 'Approved'
            ]);

        $resolveResponse->assertStatus(200);
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
    }
}
