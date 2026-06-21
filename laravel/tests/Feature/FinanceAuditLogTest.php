<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use App\Models\AuditLog;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vienna;
    protected $donationCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->donationCategory = FinanceCategory::where('category_type', 'donation')->first();
    }

    public function test_audit_log_created_for_finance_activity(): void
    {
        AuditLog::truncate();

        // Perform donation creation
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/donations', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'finance_category_id' => $this->donationCategory->id,
                'donor_name' => 'John Auditor Doe',
                'donation_type' => 'general',
                'amount' => 150.00,
                'currency' => 'EUR',
                'payment_method' => 'card',
                'received_date' => date('Y-m-d'),
                'status' => 'pending'
            ]);

        $response->assertStatus(201);

        // Verify audit log entry exists
        $auditLog = AuditLog::where('module', 'Finance')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('Donation Created', $auditLog->action);
    }

    public function test_audit_log_created_for_v2_income_and_cancellation(): void
    {
        AuditLog::truncate();

        $moneyAccount = \App\Models\FinanceMoneyAccount::create([
            'church_id' => $this->vienna->id,
            'code' => 'AUDIT-CASH',
            'name' => 'Audit Cash',
            'type' => 'cash',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $incomeHead = \App\Models\FinanceIncomeHead::create([
            'chart_account_id' => \App\Models\FinanceChartAccount::where('type', 'revenue')->value('id') ?? 1,
            'code' => 'AUDIT-INC-HEAD',
            'name' => 'Audit Income Head',
            'is_active' => true,
        ]);

        $fundClass = \App\Models\FinanceFundClass::where('code', 'GEN')->first() ?: \App\Models\FinanceFundClass::create([
            'code' => 'GEN', 'name' => 'General Fund', 'is_active' => true
        ]);

        // 1. Create Income
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/income-headers', [
                'church_id' => $this->vienna->id,
                'money_account_id' => $moneyAccount->id,
                'income_date' => now()->toDateString(),
                'reference_no' => 'INC-AUDIT-101',
                'payment_method' => 'cash',
                'remarks' => 'Test remarks',
                'lines' => [
                    [
                        'income_head_id' => $incomeHead->id,
                        'fund_class_id' => $fundClass->id,
                        'amount' => 350.00,
                        'remarks' => 'Line remarks'
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $incomeId = $response->json('data.id');

        $log = AuditLog::where('module', 'Finance')->where('action', 'Income Header Created')->first();
        $this->assertNotNull($log);

        // 2. Confirm Income
        $confirmResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/finance/income-headers/{$incomeId}/confirm");

        $confirmResponse->assertStatus(200);

        $confirmLog = AuditLog::where('module', 'Finance')->where('action', 'Income Confirmed')->first();
        $this->assertNotNull($confirmLog);

        // Retrieve the receipt generated
        $receipt = \App\Models\FinanceReceipt::where('income_header_id', $incomeId)->first();
        $this->assertNotNull($receipt);

        // 3. Cancel Receipt (requires 2FA)
        $this->superAdmin->update(['two_factor_enabled' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($this->superAdmin, ['2fa_verified']);

        $cancelResponse = $this->postJson("/api/v1/finance/receipts/{$receipt->id}/cancel", [
            'reason' => 'Duplicate receipt created in error',
        ]);

        $cancelResponse->assertStatus(200);

        $cancelLog = AuditLog::where('module', 'Finance')->where('action', 'Receipt Cancelled')->first();
        $this->assertNotNull($cancelLog);
    }
}
