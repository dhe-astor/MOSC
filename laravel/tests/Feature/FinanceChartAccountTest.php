<?php

namespace Tests\Feature;

use App\Models\FinanceChartAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceChartAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_chart_of_accounts(): void
    {
        $coa = FinanceChartAccount::create([
            'code' => '1100',
            'name' => 'Cash in Hand',
            'type' => 'asset',
            'description' => 'Petty cash and cash safe',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('finance_chart_accounts', [
            'code' => '1100',
            'name' => 'Cash in Hand',
            'type' => 'asset',
        ]);
        $this->assertTrue($coa->is_active);
    }
}
