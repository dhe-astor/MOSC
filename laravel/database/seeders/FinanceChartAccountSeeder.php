<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceChartAccount;

class FinanceChartAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => '1000',
                'name' => 'Assets & Cash/Bank Accounts',
                'type' => 'asset',
                'description' => 'Current and fixed assets, including cash, bank accounts, and properties.',
                'is_active' => true,
            ],
            [
                'code' => '2000',
                'name' => 'Liabilities & Creditors',
                'type' => 'liability',
                'description' => 'Current and long-term liabilities, loans, and payable stipends.',
                'is_active' => true,
            ],
            [
                'code' => '3000',
                'name' => 'Equity, Reserves & Funds',
                'type' => 'equity',
                'description' => 'Parish and Diocesan accumulated reserves, capital funds, and restricted funds.',
                'is_active' => true,
            ],
            [
                'code' => '4000',
                'name' => 'Operating Revenues / Income',
                'type' => 'revenue',
                'description' => 'All sources of incoming funds, offerings, donations, subscriptions, and fees.',
                'is_active' => true,
            ],
            [
                'code' => '5000',
                'name' => 'Operating Expenses',
                'type' => 'expense',
                'description' => 'All categories of expenses, stipends, maintenance, diocesan shares, and charitable aid.',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $account) {
            FinanceChartAccount::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }
}
