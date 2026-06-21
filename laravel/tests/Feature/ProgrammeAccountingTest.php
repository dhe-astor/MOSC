<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceProgrammeAccount;
use App\Models\FinanceChartAccount;
use App\Models\FinanceFundClass;
use App\Models\FinanceJournalBatch;
use App\Models\FinanceLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgrammeAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $programme;
    protected $revenueAccount;
    protected $expenseAccount;
    protected $fundClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        // Create a Programme Account (e.g. Parish Day 2026)
        $this->programme = FinanceProgrammeAccount::create([
            'church_id' => $this->church->id,
            'code' => 'PROG-PARISH-DAY',
            'name' => 'Parish Day 2026',
            'description' => 'Parish Day 2026 Feast and Cultural Event',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        // Revenue and Expense chart accounts
        $this->revenueAccount = FinanceChartAccount::where('type', 'revenue')->first() ?: FinanceChartAccount::create([
            'code' => '4000',
            'name' => 'General Revenue',
            'type' => 'revenue',
            'is_active' => true,
        ]);

        $this->expenseAccount = FinanceChartAccount::where('type', 'expense')->first() ?: FinanceChartAccount::create([
            'code' => '5000',
            'name' => 'General Expense',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->fundClass = FinanceFundClass::first() ?: FinanceFundClass::create([
            'code' => 'GEN',
            'name' => 'General Fund',
            'is_active' => true,
        ]);
    }

    public function test_can_list_programme_accounts(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/finance/programme-accounts?church_id={$this->church->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'code' => 'PROG-PARISH-DAY',
            'name' => 'Parish Day 2026',
        ]);
    }

    public function test_programme_surplus_deficit_rollup(): void
    {
        // Create a journal batch
        $batch = FinanceJournalBatch::create([
            'church_id' => $this->church->id,
            'diocese_id' => $this->church->diocese_id,
            'batch_date' => date('Y-m-d'),
            'reference' => 'PROG-ROLLUP-TEST',
            'source' => 'journal',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        // Post revenue to programme (Credit 1000 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->revenueAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->programme->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 0.00,
            'credit' => 1000.00,
            'description' => 'Parish Day Sponsorship',
        ]);

        // Post expense to programme (Debit 400 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->expenseAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->programme->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 400.00,
            'credit' => 0.00,
            'description' => 'Parish Day Catering',
        ]);

        // Calculate Surplus / Deficit
        // Surplus = Revenue Credits - Expense Debits
        $revenueSum = DB::table('finance_ledger_entries')
            ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
            ->where('finance_ledger_entries.programme_account_id', $this->programme->id)
            ->where('finance_chart_accounts.type', 'revenue')
            ->sum('finance_ledger_entries.credit');

        $expenseSum = DB::table('finance_ledger_entries')
            ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
            ->where('finance_ledger_entries.programme_account_id', $this->programme->id)
            ->where('finance_chart_accounts.type', 'expense')
            ->sum('finance_ledger_entries.debit');

        $surplus = $revenueSum - $expenseSum;

        $this->assertEquals(1000.00, $revenueSum);
        $this->assertEquals(400.00, $expenseSum);
        $this->assertEquals(600.00, $surplus);
    }
}
