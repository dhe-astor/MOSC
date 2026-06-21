<?php

namespace Tests\Feature;

use App\Models\FinanceFundClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_query_fund_classes(): void
    {
        $fund = FinanceFundClass::firstOrCreate(
            ['code' => 'BLDG'],
            ['name' => 'Building Fund', 'description' => 'Restricted for church construction', 'is_active' => true]
        );

        $this->assertDatabaseHas('finance_fund_classes', [
            'code' => 'BLDG',
            'name' => 'Building Fund',
        ]);
    }
}
