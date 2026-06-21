<?php

namespace Tests\Feature;

use App\Models\FinanceChartAccount;
use App\Models\FinanceIncomeHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeHeadMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_heads_are_seeded_correctly(): void
    {
        $this->seed();

        $this->assertDatabaseHas('finance_income_heads', ['code' => 'INC-001']);
        $this->assertDatabaseHas('finance_income_heads', ['name' => 'Monthly Family Contribution']);
        $this->assertDatabaseHas('finance_income_heads', ['name' => 'Sunday Offering']);
    }

    public function test_custom_income_head_can_be_added(): void
    {
        $coa = FinanceChartAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Revenue', 'type' => 'revenue', 'is_active' => true]
        );

        $custom = FinanceIncomeHead::create([
            'chart_account_id' => $coa->id,
            'code' => 'INC-CUSTOM-01',
            'name' => 'Special Parish Hall Renovation Fund',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('finance_income_heads', ['code' => 'INC-CUSTOM-01']);
    }
}
