<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceChartAccount;
use App\Models\FinanceExpenseHead;
use App\Models\FinanceFundClass;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceExpenseHeader;
use App\Services\ExpenseEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseEntryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $moneyAccount;
    protected $expenseHead;
    protected $fundClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        $coaAsset = FinanceChartAccount::where('code', '1000')->first() ?: FinanceChartAccount::create([
            'code' => '1000', 'name' => 'Asset', 'type' => 'asset', 'is_active' => true
        ]);
        $coaExpense = FinanceChartAccount::where('code', '5000')->first() ?: FinanceChartAccount::create([
            'code' => '5000', 'name' => 'Expense', 'type' => 'expense', 'is_active' => true
        ]);

        $this->moneyAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'TEST-BANK-3',
            'name' => 'Test Bank',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true
        ]);

        $this->expenseHead = FinanceExpenseHead::create([
            'chart_account_id' => $coaExpense->id,
            'code' => 'TEST-EXP-HEAD-3',
            'name' => 'Test Expense Head',
            'is_active' => true
        ]);

        $this->fundClass = FinanceFundClass::where('code', 'GEN')->first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);
    }

    public function test_can_create_and_pay_expense(): void
    {
        $headerData = [
            'church_id' => $this->church->id,
            'expense_date' => now()->toDateString(),
            'money_account_id' => $this->moneyAccount->id,
            'payee_name' => 'Altar Bread Supplier',
            'remarks' => 'Liturgical purchase',
        ];

        $linesData = [
            [
                'expense_head_id' => $this->expenseHead->id,
                'fund_class_id' => $this->fundClass->id,
                'amount' => 85.00,
                'remarks' => 'Liturgical bread and wine'
            ]
        ];

        $expense = ExpenseEntryService::createExpense($headerData, $linesData, null, $this->user);

        $this->assertDatabaseHas('finance_expense_headers', [
            'id' => $expense->id,
            'status' => 'draft',
        ]);

        $paid = ExpenseEntryService::payExpense($expense->id, $this->user);

        $this->assertEquals('paid', $paid->status);
    }
}
