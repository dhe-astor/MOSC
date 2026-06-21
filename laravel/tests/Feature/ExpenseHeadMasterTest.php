<?php

namespace Tests\Feature;

use App\Models\FinanceChartAccount;
use App\Models\FinanceExpenseHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseHeadMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_heads_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('finance_expense_heads', ['code' => 'EXP-001']);
        $this->assertDatabaseHas('finance_expense_heads', ['name' => 'Priest Stipend / Salary']);
        $this->assertDatabaseHas('finance_expense_heads', ['name' => 'Holy Qurbana Bread & Wine']);
    }

    public function test_can_add_custom_expense_head(): void
    {
        $coa = FinanceChartAccount::firstOrCreate(
            ['code' => '5000'],
            ['name' => 'Expense', 'type' => 'expense', 'is_active' => true]
        );

        $custom = FinanceExpenseHead::create([
            'chart_account_id' => $coa->id,
            'code' => 'EXP-CUSTOM-01',
            'name' => 'Custom Community Outing',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('finance_expense_heads', ['code' => 'EXP-CUSTOM-01']);
    }
}
