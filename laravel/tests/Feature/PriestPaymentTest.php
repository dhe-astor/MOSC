<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\FinancePriestPayment;
use App\Models\FinanceChartAccount;
use App\Models\FinanceExpenseHead;
use App\Models\FinanceFundClass;
use App\Models\FinanceMoneyAccount;
use App\Services\PriestPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriestPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $priest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        // Ensure priest profile exists
        $member = \App\Models\Member::create([
            'diocese_id' => 1,
            'church_id' => $this->church->id,
            'first_name' => 'PriestName',
            'last_name' => 'Koch',
            'full_name' => 'Rev. Fr. PriestName Koch',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => 1
        ]);

        $this->priest = PriestProfile::create([
            'diocese_id' => 1,
            'member_id' => $member->id,
            'display_name' => 'Rev. Fr. PriestName Koch',
            'clergy_type' => 'priest',
            'status' => 'active'
        ]);

        // Ensure EXP-002 exists
        $coaExpense = FinanceChartAccount::where('code', '5000')->first() ?: FinanceChartAccount::create([
            'code' => '5000', 'name' => 'Expense', 'type' => 'expense', 'is_active' => true
        ]);
        FinanceExpenseHead::firstOrCreate(
            ['code' => 'EXP-002'],
            ['chart_account_id' => $coaExpense->id, 'name' => 'Priest Travel & Mileage Allowance', 'is_active' => true]
        );
        FinanceExpenseHead::firstOrCreate(
            ['code' => 'EXP-001'],
            ['chart_account_id' => $coaExpense->id, 'name' => 'Priest Stipend / Salary', 'is_active' => true]
        );

        // Ensure money account exists
        FinanceMoneyAccount::firstOrCreate(
            ['code' => "BANK-CHURCH-{$this->church->id}"],
            ['church_id' => $this->church->id, 'name' => 'Bank', 'type' => 'bank', 'currency' => 'EUR', 'is_active' => true]
        );
    }

    public function test_can_calculate_travel_amount(): void
    {
        $amount = PriestPaymentService::calculateTravelAmount(150.0, 0.42);
        $this->assertEquals(63.0, $amount);
    }

    public function test_can_create_and_confirm_priest_payment(): void
    {
        $payment = PriestPaymentService::createPaymentClaim([
            'church_id' => $this->church->id,
            'priest_profile_id' => $this->priest->id,
            'payment_date' => now()->toDateString(),
            'type' => 'travel',
            'travel_distance_km' => 200.0,
            'travel_rate_per_km' => 0.4200,
            'description' => 'Travel payout claim',
        ], $this->user);

        $this->assertDatabaseHas('finance_priest_payments', [
            'id' => $payment->id,
            'amount' => 84.00,
            'status' => 'draft'
        ]);

        $confirmed = PriestPaymentService::confirmPaymentClaim($payment->id, $this->user);

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->expense_header_id);
    }
}
