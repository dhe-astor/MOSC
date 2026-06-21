<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceCashBatch;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\FinanceChartAccount;
use App\Models\FinanceFundClass;
use App\Models\FinanceIncomeHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBatchTest extends TestCase
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

        // Create money account of type cash
        $this->moneyAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'CASH-MAIN',
            'name' => 'Main Cash Box',
            'type' => 'cash',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->fundClass = FinanceFundClass::where('code', 'GEN')->first() ?: FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);

        $coaRevenue = FinanceChartAccount::where('code', '4000')->first() ?: FinanceChartAccount::create([
            'code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true
        ]);

        $this->incomeHead = FinanceIncomeHead::create([
            'chart_account_id' => $coaRevenue->id,
            'code' => 'INC-CASH-TEST',
            'name' => 'Cash Test Income Head',
            'is_active' => true,
        ]);
    }

    public function test_can_open_cash_batch(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/finance/cash-batches/open', [
                'church_id' => $this->church->id,
                'money_account_id' => $this->moneyAccount->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('finance_cash_batches', [
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'status' => 'open',
        ]);
    }

    public function test_cannot_open_duplicate_batch_for_same_account(): void
    {
        FinanceCashBatch::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'opened_at' => now(),
            'opened_by' => $this->user->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/finance/cash-batches/open', [
                'church_id' => $this->church->id,
                'money_account_id' => $this->moneyAccount->id,
            ]);

        $response->assertStatus(500); // Throws exception for duplicate open batch
    }

    public function test_can_close_cash_batch_and_reconcile(): void
    {
        $batch = FinanceCashBatch::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'opened_at' => now()->subHour(),
            'opened_by' => $this->user->id,
            'status' => 'open',
        ]);

        // Create a confirmed income header inside the batch timeframe
        $income = FinanceIncomeHeader::create([
            'church_id' => $this->church->id,
            'money_account_id' => $this->moneyAccount->id,
            'income_date' => now()->toDateString(),
            'reference_no' => 'INC-CASH-BATCH-1',
            'payment_method' => 'cash',
            'status' => 'confirmed', // Must be confirmed to be counted by system
            'created_by' => $this->user->id,
            'created_at' => now()->subMinutes(30),
        ]);

        FinanceIncomeLine::create([
            'income_header_id' => $income->id,
            'income_head_id' => $this->incomeHead->id,
            'fund_class_id' => $this->fundClass->id,
            'amount' => 450.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/finance/cash-batches/{$batch->id}/close", [
                'declared_amount' => 460.00,
                'counting_details' => [
                    '50_euro' => 8,
                    '20_euro' => 2,
                    '10_euro' => 2,
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('finance_cash_batches', [
            'id' => $batch->id,
            'status' => 'closed',
            'declared_amount' => 460.00,
            'system_amount' => 450.00,
            'difference' => 10.00, // 460 declared - 450 system = 10 difference
        ]);
    }
}
