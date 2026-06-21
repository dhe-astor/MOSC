<?php

namespace Tests\Feature;

use App\Models\FinanceProgrammeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_programme_accounts(): void
    {
        $prog = FinanceProgrammeAccount::create([
            'code' => 'PERUNNAL-2026',
            'name' => 'Perunnal Feast 2026',
            'description' => 'Feast operations',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('finance_programme_accounts', [
            'code' => 'PERUNNAL-2026',
            'is_active' => true,
        ]);
    }
}
