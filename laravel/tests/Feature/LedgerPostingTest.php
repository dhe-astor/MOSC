<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceExpenseLine;
use App\Models\FinanceChartAccount;
use App\Models\FinanceFundClass;
use App\Models\FinanceJournalBatch;
use App\Models\FinanceLedgerEntry;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ReflectionMethod;

class LedgerPostingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $assetAccount;
    protected $revenueAccount;
    protected $expenseAccount;
    protected $fundClass;
    protected $moneyAccount;
    protected $incomeHead;
    protected $expenseHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        // Standard Asset Account
        $this->assetAccount = FinanceChartAccount::where('code', '1000')->first() ?: FinanceChartAccount::create([
            'code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true
        ]);

        $this->revenueAccount = FinanceChartAccount::where('code', '4000')->first() ?: FinanceChartAccount::create([
            'code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true
        ]);

        $this->expenseAccount = FinanceChartAccount::where('code', '5000')->first() ?: FinanceChartAccount::create([
            'code' => '5000', 'name' => 'Expense', 'type' => 'expense', 'is_active' => true
        ]);

        $this->fundClass = FinanceFundClass::first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);

        $this->moneyAccount = \App\Models\FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'TEST-BANK-LEDGER',
            'name' => 'Test Bank',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true
        ]);

        $this->incomeHead = \App\Models\FinanceIncomeHead::create([
            'chart_account_id' => $this->revenueAccount->id,
            'code' => 'INC-BAL-HEAD',
            'name' => 'Income Head',
            'is_active' => true
        ]);

        $this->expenseHead = \App\Models\FinanceExpenseHead::create([
            'chart_account_id' => $this->expenseAccount->id,
            'code' => 'EXP-BAL-HEAD',
            'name' => 'Expense Head',
            'is_active' => true
        ]);
    }

    public function test_post_income_header_creates_balanced_ledger_entries(): void
    {
        $income = FinanceIncomeHeader::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'income_date' => now()->toDateString(),
            'reference_no' => 'INC-BAL-100',
            'payment_method' => 'cash',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        FinanceIncomeLine::create([
            'income_header_id' => $income->id,
            'income_head_id' => $this->incomeHead->id,
            'fund_class_id' => $this->fundClass->id,
            'amount' => 500.00,
        ]);

        $batch = LedgerPostingService::postIncomeHeader($income, $this->user);

        $this->assertNotNull($batch);
        $this->assertEquals('posted', $batch->status);

        // Assert debits == credits
        $debits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('debit');
        $credits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('credit');

        $this->assertEquals(500.00, $debits);
        $this->assertEquals(500.00, $credits);
    }

    public function test_post_expense_header_creates_balanced_ledger_entries(): void
    {
        $expense = FinanceExpenseHeader::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'payee_name' => 'Test Supplier',
            'expense_date' => now()->toDateString(),
            'reference_no' => 'EXP-BAL-200',
            'payment_method' => 'cash',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        FinanceExpenseLine::create([
            'expense_header_id' => $expense->id,
            'expense_head_id' => $this->expenseHead->id,
            'fund_class_id' => $this->fundClass->id,
            'amount' => 300.00,
        ]);

        $batch = LedgerPostingService::postExpenseHeader($expense, $this->user);

        $this->assertNotNull($batch);
        $this->assertEquals('posted', $batch->status);

        $debits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('debit');
        $credits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('credit');

        $this->assertEquals(300.00, $debits);
        $this->assertEquals(300.00, $credits);
    }

    public function test_verify_batch_balance_throws_exception_on_unbalanced_batch(): void
    {
        $batch = FinanceJournalBatch::create([
            'church_id' => $this->church->id,
            'diocese_id' => $this->church->diocese_id,
            'batch_date' => date('Y-m-d'),
            'reference' => 'UNBALANCED-BATCH',
            'source' => 'journal',
            'status' => 'draft', // Draft because we are manually testing verification
            'created_by' => $this->user->id,
        ]);

        // Unbalanced ledger entries
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->assetAccount->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 100.00,
            'credit' => 0.00,
            'description' => 'Unbalanced Debit',
        ]);

        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->revenueAccount->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 0.00,
            'credit' => 50.00, // Credits do not equal debits
            'description' => 'Unbalanced Credit',
        ]);

        // Use reflection to invoke the private verifyBatchBalance method
        $method = new ReflectionMethod(LedgerPostingService::class, 'verifyBatchBalance');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/is out of balance/');

        $method->invoke(null, $batch);
    }
}
