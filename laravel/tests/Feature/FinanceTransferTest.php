<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceTransfer;
use App\Models\FinanceChartAccount;
use App\Models\FinanceJournalBatch;
use App\Models\FinanceLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTransferTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $church;
    protected $sourceAccount;
    protected $destinationAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::first() ?: User::factory()->create();
        $this->church = Church::first();

        // Create two different money accounts
        $this->sourceAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'ACC-SOURCE',
            'name' => 'Source Bank Account',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->destinationAccount = FinanceMoneyAccount::create([
            'church_id' => $this->church->id,
            'code' => 'ACC-DEST',
            'name' => 'Destination Bank Account',
            'type' => 'bank',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Standard Asset Account
        FinanceChartAccount::firstOrCreate(
            ['code' => '1000'],
            ['name' => 'Cash', 'type' => 'asset', 'is_active' => true]
        );
    }

    public function test_can_create_transfer_draft(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/finance/transfers', [
                'church_id' => $this->church->id,
                'transfer_date' => now()->toDateString(),
                'from_account_id' => $this->sourceAccount->id,
                'to_account_id' => $this->destinationAccount->id,
                'amount' => 1200.00,
                'reference' => 'TX-1002',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('finance_transfers', [
            'amount' => 1200.00,
            'reference' => 'TX-1002',
            'status' => 'draft',
        ]);
    }

    public function test_cannot_transfer_to_same_account(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/finance/transfers', [
                'church_id' => $this->church->id,
                'transfer_date' => now()->toDateString(),
                'from_account_id' => $this->sourceAccount->id,
                'to_account_id' => $this->sourceAccount->id, // Same account
                'amount' => 500.00,
                'reference' => 'TX-SAME',
            ]);

        $response->assertStatus(500); // Throws exception
    }

    public function test_confirm_transfer_posts_to_ledger(): void
    {
        $transfer = FinanceTransfer::create([
            'church_id' => $this->church->id,
            'transfer_date' => now()->toDateString(),
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id' => $this->destinationAccount->id,
            'amount' => 1500.00,
            'reference' => 'TX-CONFIRM',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/finance/transfers/{$transfer->id}/confirm");

        $response->assertStatus(200);

        // Check transfer status updated to confirmed
        $this->assertEquals('confirmed', $transfer->fresh()->status);

        // Check journal batch posted
        $batch = FinanceJournalBatch::where('source', 'transfer')
            ->where('source_id', $transfer->id)
            ->first();
        
        $this->assertNotNull($batch);
        $this->assertEquals('posted', $batch->status);

        // Check debits and credits exist and balance
        $debits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('debit');
        $credits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('credit');

        $this->assertEquals(1500.00, $debits);
        $this->assertEquals(1500.00, $credits);
    }
}
