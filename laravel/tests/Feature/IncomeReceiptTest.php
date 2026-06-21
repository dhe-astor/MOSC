<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceChartAccount;
use App\Models\FinanceIncomeHead;
use App\Models\FinanceFundClass;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceIncomeHeader;
use App\Services\IncomeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $moneyAccount;
    protected $incomeHead;
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
        $coaRevenue = FinanceChartAccount::where('code', '4000')->first() ?: FinanceChartAccount::create([
            'code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true
        ]);

        $this->moneyAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'TEST-BANK-2',
            'name' => 'Test Bank',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true
        ]);

        $this->incomeHead = FinanceIncomeHead::create([
            'chart_account_id' => $coaRevenue->id,
            'code' => 'TEST-INC-HEAD-2',
            'name' => 'Test Income Head',
            'is_active' => true
        ]);

        $this->fundClass = FinanceFundClass::where('code', 'GEN')->first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);
    }

    public function test_confirm_income_creates_active_finance_receipt(): void
    {
        $headerData = [
            'church_id' => $this->church->id,
            'income_date' => now()->toDateString(),
            'money_account_id' => $this->moneyAccount->id,
            'reference_no' => 'REF-554433',
            'remarks' => 'Sunday offertory',
        ];

        $linesData = [
            [
                'income_head_id' => $this->incomeHead->id,
                'fund_class_id' => $this->fundClass->id,
                'amount' => 250.00,
                'remarks' => 'Nercha collection'
            ]
        ];

        $income = IncomeEntryService::createIncome($headerData, $linesData, $this->user);
        $incomeConfirmed = IncomeEntryService::confirmIncome($income->id, $this->user);

        $this->assertDatabaseHas('finance_receipts', [
            'income_header_id' => $income->id,
            'total_amount' => 250.00,
            'status' => 'active'
        ]);
    }
}
