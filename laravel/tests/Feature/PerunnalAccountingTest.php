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

class PerunnalAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $perunnalProg;
    protected $revenueAccount;
    protected $expenseAccount;
    protected $fundClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        // Create Perunnal Programme Account
        $this->perunnalProg = FinanceProgrammeAccount::create([
            'church_id' => $this->church->id,
            'code' => 'PROG-PERUNNAL-FEAST',
            'name' => 'Perunnal Feast 2026',
            'description' => 'Patron Saint Feast of the Parish',
            'start_date' => '2026-05-15',
            'end_date' => '2026-05-20',
            'is_active' => true,
        ]);

        $this->revenueAccount = FinanceChartAccount::where('type', 'revenue')->first() ?: FinanceChartAccount::create([
            'code' => '4000', 'name' => 'General Revenue', 'type' => 'revenue', 'is_active' => true
        ]);

        $this->expenseAccount = FinanceChartAccount::where('type', 'expense')->first() ?: FinanceChartAccount::create([
            'code' => '5000', 'name' => 'General Expense', 'type' => 'expense', 'is_active' => true
        ]);

        $this->fundClass = FinanceFundClass::first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);
    }

    public function test_perunnal_feast_income_and_expense_postings(): void
    {
        // 1. Create a journal batch for Perunnal Feast
        $batch = FinanceJournalBatch::create([
            'church_id' => $this->church->id,
            'diocese_id' => $this->church->diocese_id,
            'batch_date' => date('Y-m-d'),
            'reference' => 'PERUNNAL-FEAST-POSTINGS',
            'source' => 'journal',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        // Post Auction income (Credit 1500 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->revenueAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 0.00,
            'credit' => 1500.00,
            'description' => 'Perunnal Auction Collections',
        ]);

        // Post Feast Sponsorships (Credit 800 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->revenueAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 0.00,
            'credit' => 800.00,
            'description' => 'Perunnal Feast Sponsorships',
        ]);

        // Post Decoration expense (Debit 600 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->expenseAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 600.00,
            'credit' => 0.00,
            'description' => 'Perunnal Church Decoration',
        ]);

        // Post Food expense (Debit 1000 EUR)
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->expenseAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 1000.00,
            'credit' => 0.00,
            'description' => 'Perunnal Feast Nercha Food',
        ]);

        // Verify rollups
        $totalCredits = DB::table('finance_ledger_entries')
            ->where('programme_account_id', $this->perunnalProg->id)
            ->sum('credit');

        $totalDebits = DB::table('finance_ledger_entries')
            ->where('programme_account_id', $this->perunnalProg->id)
            ->sum('debit');

        $netBalance = $totalCredits - $totalDebits;

        $this->assertEquals(2300.00, $totalCredits);
        $this->assertEquals(1600.00, $totalDebits);
        $this->assertEquals(700.00, $netBalance);
    }

    public function test_programme_report(): void
    {
        // 1. Setup double entry postings for the Perunnal programme
        $batch = FinanceJournalBatch::create([
            'church_id' => $this->church->id,
            'diocese_id' => $this->church->diocese_id,
            'batch_date' => date('Y-m-d'),
            'reference' => 'TEST-PERUNNAL-REPORT',
            'source' => 'journal',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        // Revenue credit: 500.00
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->revenueAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 0.00,
            'credit' => 500.00,
            'description' => 'Test Revenue Credit',
        ]);

        // Expense debit: 200.00
        FinanceLedgerEntry::create([
            'journal_batch_id' => $batch->id,
            'chart_account_id' => $this->expenseAccount->id,
            'fund_class_id' => $this->fundClass->id,
            'programme_account_id' => $this->perunnalProg->id,
            'entry_date' => date('Y-m-d'),
            'debit' => 200.00,
            'credit' => 0.00,
            'description' => 'Test Expense Debit',
        ]);

        // 2. Request the reports/programmes endpoint
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/finance/reports/programmes?church_id=' . $this->church->id);

        // 3. Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'programme_id',
                    'code',
                    'name',
                    'description',
                    'income',
                    'expense',
                    'profit_loss',
                ]
            ]
        ]);

        // Assert values match our postings
        $data = $response->json('data');
        $progReport = collect($data)->firstWhere('programme_id', $this->perunnalProg->id);
        
        $this->assertNotNull($progReport);
        $this->assertEquals(500.00, $progReport['income']);
        $this->assertEquals(200.00, $progReport['expense']);
        $this->assertEquals(300.00, $progReport['profit_loss']);
    }
}
