<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceBankStatementImport;
use App\Models\FinanceBankStatementLine;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\FinanceChartAccount;
use App\Models\FinanceFundClass;
use App\Models\FinanceIncomeHead;
use App\Models\FinanceBankMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $moneyAccount;
    protected $fundClass;
    protected $incomeHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        $this->moneyAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'RECON-BANK',
            'name' => 'Reconciliation Bank',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->fundClass = FinanceFundClass::where('code', 'GEN')->first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);

        $coaAsset = FinanceChartAccount::where('code', '1000')->first() ?: FinanceChartAccount::create([
            'code' => '1000', 'name' => 'Asset', 'type' => 'asset', 'is_active' => true
        ]);

        $coaRevenue = FinanceChartAccount::where('code', '4000')->first() ?: FinanceChartAccount::create([
            'code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true
        ]);

        $this->incomeHead = FinanceIncomeHead::create([
            'chart_account_id' => $coaRevenue->id,
            'code' => 'INC-RECON-TEST',
            'name' => 'Recon Test Income Head',
            'is_active' => true,
        ]);
    }

    public function test_can_import_bank_statement_csv(): void
    {
        $csvContent = "Booking Date,Partner Name,Description,Amount\n"
                    . "2026-06-15,John Donor,Sunday collection bank transfer,150.00\n"
                    . "2026-06-15,Gas Utility,Heating bill,-120.00\n";

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csvContent);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/finance/bank-statements/import', [
                'money_account_id' => $this->moneyAccount->id,
                'statement_file' => $file,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('finance_bank_statement_imports', [
            'money_account_id' => $this->moneyAccount->id,
            'file_name' => 'statement.csv',
        ]);

        $this->assertDatabaseHas('finance_bank_statement_lines', [
            'partner_name' => 'John Donor',
            'amount' => 150.00,
            'is_matched' => false,
        ]);

        $this->assertDatabaseHas('finance_bank_statement_lines', [
            'partner_name' => 'Gas Utility',
            'amount' => -120.00,
            'is_matched' => false,
        ]);
    }

    public function test_can_match_bank_statement_line_to_income(): void
    {
        // 1. Setup statement line
        $import = FinanceBankStatementImport::create([
            'money_account_id' => $this->moneyAccount->id,
            'import_date' => date('Y-m-d'),
            'file_name' => 'manual.csv',
            'imported_by' => $this->user->id,
        ]);

        $line = FinanceBankStatementLine::create([
            'bank_statement_import_id' => $import->id,
            'booking_date' => date('Y-m-d'),
            'value_date' => date('Y-m-d'),
            'partner_name' => 'John Donor',
            'description' => 'Direct transfer donation',
            'amount' => 150.00,
            'is_matched' => false,
        ]);

        // 2. Setup draft income header
        $income = FinanceIncomeHeader::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'income_date' => now()->toDateString(),
            'reference_no' => 'INC-RECON-MATCH-1',
            'payment_method' => 'bank_transfer',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        FinanceIncomeLine::create([
            'income_header_id' => $income->id,
            'income_head_id' => $this->incomeHead->id,
            'fund_class_id' => $this->fundClass->id,
            'amount' => 150.00,
        ]);

        // 3. Match via API
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/finance/bank-statements/lines/{$line->id}/match", [
                'matchable_type' => 'App\Models\FinanceIncomeHeader',
                'matchable_id' => $income->id,
            ]);

        $response->assertStatus(200);

        // Check line marked as matched
        $this->assertTrue($line->fresh()->is_matched);

        // Check match record exists
        $this->assertDatabaseHas('finance_bank_matches', [
            'bank_statement_line_id' => $line->id,
            'matchable_type' => 'App\Models\FinanceIncomeHeader',
            'matchable_id' => $income->id,
        ]);

        // Check income header is auto-confirmed
        $this->assertEquals('confirmed', $income->fresh()->status);
    }
}
