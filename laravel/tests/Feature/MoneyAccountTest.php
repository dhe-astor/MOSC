<?php

namespace Tests\Feature;

use App\Models\FinanceMoneyAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_money_accounts(): void
    {
        $acc = FinanceMoneyAccount::create([
            'code' => 'SPARKASSE-VIB',
            'name' => 'Vienna Sparkasse Bank',
            'type' => 'bank',
            'bank_name' => 'Sparkasse',
            'account_number' => 'AT123456',
            'iban' => 'AT123456',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('finance_money_accounts', [
            'code' => 'SPARKASSE-VIB',
            'type' => 'bank',
        ]);
    }
}
