<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_demo_seeder_populates_minimum_v2_counts(): void
    {
        // Run the demo:seed command
        $exitCode = Artisan::call('demo:seed');
        $this->assertEquals(0, $exitCode);

        // Verify minimum counts are met in the database
        $this->assertTrue(DB::table('finance_fund_classes')->count() >= 8, 'Should have >= 8 fund classes');
        $this->assertTrue(DB::table('finance_programme_accounts')->count() >= 10, 'Should have >= 10 programme accounts');
        $this->assertTrue(DB::table('finance_money_accounts')->count() >= 10, 'Should have >= 10 money accounts');
        $this->assertTrue(DB::table('finance_income_headers')->count() >= 200, 'Should have >= 200 income records');
        $this->assertTrue(DB::table('finance_receipts')->count() >= 100, 'Should have >= 100 receipts');
        $this->assertTrue(DB::table('finance_expense_headers')->count() >= 80, 'Should have >= 80 expense records');
        $this->assertTrue(DB::table('finance_priest_payments')->count() >= 20, 'Should have >= 20 priest payments');
        $this->assertTrue(DB::table('finance_cash_batches')->count() >= 20, 'Should have >= 20 cash batches');
        $this->assertTrue(DB::table('finance_bank_statement_lines')->count() >= 15, 'Should have >= 15 statement lines');
        $this->assertTrue(DB::table('finance_bank_matches')->count() >= 10, 'Should have >= 10 matches');
        $this->assertTrue(DB::table('finance_transfers')->count() >= 5, 'Should have >= 5 transfers');
    }
}
