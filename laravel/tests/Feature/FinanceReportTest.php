<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
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

        // Create received donation for reporting
        Donation::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'finance_category_id' => $this->donationCategory->id,
            'donor_name' => 'Jane Reporting Smith',
            'donation_type' => 'general',
            'amount' => 450.00,
            'currency' => 'EUR',
            'payment_method' => 'bank_transfer',
            'received_date' => date('Y-m-d'),
            'status' => 'received',
            'created_by' => $this->superAdmin->id,
        ]);

        $chartAccountId = \Illuminate\Support\Facades\DB::table('finance_chart_accounts')->where('code', '4000')->value('id');
        $fundClassId = \Illuminate\Support\Facades\DB::table('finance_fund_classes')->where('code', 'GEN')->value('id');
        
        $batchId = \Illuminate\Support\Facades\DB::table('finance_journal_batches')->insertGetId([
            'church_id' => $this->vienna->id,
            'batch_date' => date('Y-m-d'),
            'reference' => 'DON-TEST-123',
            'source' => 'income',
            'status' => 'posted',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('finance_ledger_entries')->insert([
            'journal_batch_id' => $batchId,
            'chart_account_id' => $chartAccountId,
            'fund_class_id' => $fundClassId,
            'entry_date' => date('Y-m-d'),
            'debit' => 0,
            'credit' => 450.00,
            'description' => 'Donation from Jane Reporting Smith',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_report_summary(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/finance/reports/summary?church_id=' . $this->vienna->id);

        $response->assertStatus(200);
        $this->assertEquals(450.00, $response->json('data.total_donations'));
    }

    public function test_get_monthly_report(): void
    {
        $year = date('Y');
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/finance/reports/monthly?church_id={$this->vienna->id}&year={$year}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['year', 'data']]);
    }

    public function test_get_consolidated_report(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/finance/reports/by-church');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['start_date', 'end_date', 'data']]);
    }

    public function test_export_csv_report(): void
    {
        $this->superAdmin->update(['two_factor_enabled' => true]);

        \Laravel\Sanctum\Sanctum::actingAs($this->superAdmin, ['2fa_verified']);
        $response = $this->getJson('/api/v1/finance/reports/export?report_type=summary');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
