<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyFinanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_legacy_data_migration_is_accurate_and_complete(): void
    {
        // 1. Temporarily rename legacy tables back to original names so migration up() can run on them
        Schema::rename('legacy_donations', 'donations');
        Schema::rename('legacy_income_records', 'income_records');
        Schema::rename('legacy_expense_records', 'expense_records');
        Schema::rename('legacy_receipts', 'receipts');
        Schema::rename('legacy_finance_approvals', 'finance_approvals');

        // 2. Truncate V2 tables to start clean for testing migration mapping
        DB::table('finance_income_headers')->delete();
        DB::table('finance_income_lines')->delete();
        DB::table('finance_expense_headers')->delete();
        DB::table('finance_expense_lines')->delete();
        DB::table('finance_receipts')->delete();
        DB::table('finance_receipt_lines')->delete();

        // 3. Insert dummy legacy data
        $dioceseId = 1;
        $churchId = 1;

        $catId = DB::table('finance_categories')->where('category_type', 'donation')->value('id') ?? DB::table('finance_categories')->insertGetId([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'name' => 'Legacy Donation Category',
            'slug' => 'legacy-donation-category',
            'category_type' => 'donation',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert legacy donation
        $donationId = DB::table('donations')->insertGetId([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'finance_category_id' => $catId,
            'donor_name' => 'Legacy Donor John',
            'donation_type' => 'general',
            'amount' => 450.00,
            'payment_method' => 'cash',
            'payment_reference' => 'REF-LEGACY-DON',
            'notes' => 'Legacy donation notes',
            'received_date' => '2026-05-10',
            'status' => 'received',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert legacy income record
        $incomeId = DB::table('income_records')->insertGetId([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'finance_category_id' => $catId,
            'amount' => 250.00,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-LEGACY-INC',
            'title' => 'Legacy Income Title',
            'description' => 'Legacy income description',
            'income_date' => '2026-05-11',
            'status' => 'received',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert legacy expense record
        $expenseId = DB::table('expense_records')->insertGetId([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'finance_category_id' => $catId,
            'amount' => 320.00,
            'payment_method' => 'cash',
            'bill_number' => 'BILL-LEGACY-1',
            'vendor_name' => 'Legacy Vendor Inc',
            'title' => 'Legacy Expense Title',
            'description' => 'Legacy expense description',
            'expense_date' => '2026-05-12',
            'status' => 'paid',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert legacy receipt for the donation
        DB::table('receipts')->insert([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'receipt_number' => 'REC-LEG-001',
            'receipt_date' => '2026-05-10',
            'payer_name' => 'Legacy Donor John',
            'payment_method' => 'cash',
            'amount' => 450.00,
            'status' => 'active',
            'receiptable_type' => 'App\\Models\\Donation',
            'receiptable_id' => $donationId,
            'receipt_type' => 'donation',
            'issued_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Load the migration and execute the up() method manually
        $migration = require database_path('migrations/2026_06_15_080014_migrate_legacy_finance_data_to_v2.php');
        $migration->up();

        // 5. Assertions on renamed legacy tables
        $this->assertFalse(Schema::hasTable('donations'));
        $this->assertTrue(Schema::hasTable('legacy_donations'));
        $this->assertTrue(Schema::hasTable('legacy_income_records'));
        $this->assertTrue(Schema::hasTable('legacy_expense_records'));
        $this->assertTrue(Schema::hasTable('legacy_receipts'));

        // 6. Assertions on migrated V2 data
        // Donations
        $this->assertDatabaseHas('finance_income_headers', [
            'church_id' => $churchId,
            'income_date' => '2026-05-10',
            'reference_no' => 'REF-LEGACY-DON',
            'remarks' => 'Legacy donation notes',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('finance_income_lines', [
            'donor_name' => 'Legacy Donor John',
            'amount' => 450.00,
            'remarks' => 'Legacy donation notes',
        ]);

        // Income
        $this->assertDatabaseHas('finance_income_headers', [
            'church_id' => $churchId,
            'income_date' => '2026-05-11',
            'reference_no' => 'REF-LEGACY-INC',
            'remarks' => 'Legacy income description',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('finance_income_lines', [
            'amount' => 250.00,
            'remarks' => 'Legacy Income Title - Legacy income description',
        ]);

        // Expense
        $this->assertDatabaseHas('finance_expense_headers', [
            'church_id' => $churchId,
            'expense_date' => '2026-05-12',
            'reference_no' => 'BILL-LEGACY-1',
            'payee_name' => 'Legacy Vendor Inc',
            'remarks' => 'Legacy expense description',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('finance_expense_lines', [
            'amount' => 320.00,
            'remarks' => 'Legacy Expense Title - Legacy expense description',
        ]);

        // Receipts
        $this->assertDatabaseHas('finance_receipts', [
            'receipt_number' => 'REC-LEG-001',
            'receipt_date' => '2026-05-10',
            'received_from' => 'Legacy Donor John',
            'payment_method' => 'cash',
            'total_amount' => 450.00,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('finance_receipt_lines', [
            'amount' => 450.00,
        ]);
    }
}
