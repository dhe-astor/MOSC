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

class IncomeEntryTest extends TestCase
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
            'code' => 'TEST-BANK-1',
            'name' => 'Test Bank',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true
        ]);

        $this->incomeHead = FinanceIncomeHead::create([
            'chart_account_id' => $coaRevenue->id,
            'code' => 'TEST-INC-HEAD-1',
            'name' => 'Test Income Head',
            'is_active' => true
        ]);

        $this->fundClass = FinanceFundClass::where('code', 'GEN')->first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);
    }

    public function test_can_create_and_confirm_income(): void
    {
        $headerData = [
            'church_id' => $this->church->id,
            'income_date' => now()->toDateString(),
            'money_account_id' => $this->moneyAccount->id,
            'reference_no' => 'REF-12345',
            'remarks' => 'Sunday collection',
        ];

        $linesData = [
            [
                'income_head_id' => $this->incomeHead->id,
                'fund_class_id' => $this->fundClass->id,
                'amount' => 150.00,
                'remarks' => 'Regular Member contribution'
            ]
        ];

        $income = IncomeEntryService::createIncome($headerData, $linesData, $this->user);

        $this->assertDatabaseHas('finance_income_headers', [
            'id' => $income->id,
            'status' => 'draft',
        ]);

        $incomeConfirmed = IncomeEntryService::confirmIncome($income->id, $this->user);

        $this->assertEquals('confirmed', $incomeConfirmed->status);
        $this->assertDatabaseHas('finance_receipts', [
            'income_header_id' => $income->id,
            'total_amount' => 150.00,
        ]);
    }
}
