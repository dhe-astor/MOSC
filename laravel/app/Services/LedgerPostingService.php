<?php

namespace App\Services;

use App\Models\FinanceJournalBatch;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceTransfer;
use App\Models\FinanceChartAccount;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class LedgerPostingService
{
    /**
     * Post a confirmed Income entry to the double-entry ledger.
     */
    public static function postIncomeHeader(FinanceIncomeHeader $header, User $user): FinanceJournalBatch
    {
        return DB::transaction(function () use ($header, $user) {
            $totalAmount = $header->lines()->sum('amount');
            if ($totalAmount <= 0) {
                throw new Exception("Cannot post an income entry with zero or negative total amount.");
            }

            // Find Asset Chart Account (default 1000)
            $assetAccount = FinanceChartAccount::where('code', '1000')->first();
            if (!$assetAccount) {
                throw new Exception("Standard asset chart of account (1000) not found.");
            }

            $dioceseId = null;
            if ($header->church_id) {
                $dioceseId = DB::table('churches')->where('id', $header->church_id)->value('diocese_id');
            } else {
                $dioceseId = $user->default_diocese_id ?? 1;
            }

            // 1. Create Journal Batch
            $batch = FinanceJournalBatch::create([
                'diocese_id' => $dioceseId,
                'church_id' => $header->church_id,
                'batch_date' => $header->income_date,
                'reference' => $header->reference_no,
                'source' => 'income',
                'source_id' => $header->id,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // 2. Post Debit Entry (Asset increase)
            FinanceLedgerEntry::create([
                'journal_batch_id' => $batch->id,
                'chart_account_id' => $assetAccount->id,
                'entry_date' => $header->income_date,
                'debit' => $totalAmount,
                'credit' => 0.00,
                'description' => "Received income into " . ($header->moneyAccount ? $header->moneyAccount->name : 'Cash/Bank'),
            ]);

            // 3. Post Credit Entries (Revenue increase)
            foreach ($header->lines as $line) {
                $chartAccountId = $line->incomeHead ? $line->incomeHead->chart_account_id : null;
                if (!$chartAccountId) {
                    $revenueAccount = FinanceChartAccount::where('code', '4000')->first();
                    $chartAccountId = $revenueAccount ? $revenueAccount->id : null;
                }

                if (!$chartAccountId) {
                    throw new Exception("Revenue chart account not resolved for line ID {$line->id}.");
                }

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $batch->id,
                    'chart_account_id' => $chartAccountId,
                    'fund_class_id' => $line->fund_class_id,
                    'programme_account_id' => $line->programme_account_id,
                    'entry_date' => $header->income_date,
                    'debit' => 0.00,
                    'credit' => $line->amount,
                    'description' => $line->remarks ?: ($line->incomeHead ? $line->incomeHead->name : 'Income Line Item'),
                ]);
            }

            // 4. Verify Double-Entry Balance
            self::verifyBatchBalance($batch);

            return $batch;
        });
    }

    /**
     * Post a paid/confirmed Expense entry to the double-entry ledger.
     */
    public static function postExpenseHeader(FinanceExpenseHeader $header, User $user): FinanceJournalBatch
    {
        return DB::transaction(function () use ($header, $user) {
            $totalAmount = $header->lines()->sum('amount');
            if ($totalAmount <= 0) {
                throw new Exception("Cannot post an expense entry with zero or negative total amount.");
            }

            // Find Asset Chart Account (default 1000)
            $assetAccount = FinanceChartAccount::where('code', '1000')->first();
            if (!$assetAccount) {
                throw new Exception("Standard asset chart of account (1000) not found.");
            }

            $dioceseId = null;
            if ($header->church_id) {
                $dioceseId = DB::table('churches')->where('id', $header->church_id)->value('diocese_id');
            } else {
                $dioceseId = $user->default_diocese_id ?? 1;
            }

            // 1. Create Journal Batch
            $batch = FinanceJournalBatch::create([
                'diocese_id' => $dioceseId,
                'church_id' => $header->church_id,
                'batch_date' => $header->expense_date,
                'reference' => $header->voucher_number ?: $header->reference_no,
                'source' => 'expense',
                'source_id' => $header->id,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // 2. Post Credit Entry (Asset decrease)
            FinanceLedgerEntry::create([
                'journal_batch_id' => $batch->id,
                'chart_account_id' => $assetAccount->id,
                'entry_date' => $header->expense_date,
                'debit' => 0.00,
                'credit' => $totalAmount,
                'description' => "Paid expense from " . ($header->moneyAccount ? $header->moneyAccount->name : 'Cash/Bank'),
            ]);

            // 3. Post Debit Entries (Expense increase)
            foreach ($header->lines as $line) {
                $chartAccountId = $line->expenseHead ? $line->expenseHead->chart_account_id : null;
                if (!$chartAccountId) {
                    $expenseAccount = FinanceChartAccount::where('code', '5000')->first();
                    $chartAccountId = $expenseAccount ? $expenseAccount->id : null;
                }

                if (!$chartAccountId) {
                    throw new Exception("Expense chart account not resolved for line ID {$line->id}.");
                }

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $batch->id,
                    'chart_account_id' => $chartAccountId,
                    'fund_class_id' => $line->fund_class_id,
                    'programme_account_id' => $line->programme_account_id,
                    'entry_date' => $header->expense_date,
                    'debit' => $line->amount,
                    'credit' => 0.00,
                    'description' => $line->remarks ?: ($line->expenseHead ? $line->expenseHead->name : 'Expense Line Item'),
                ]);
            }

            // 4. Verify Double-Entry Balance
            self::verifyBatchBalance($batch);

            return $batch;
        });
    }

    /**
     * Post a confirmed Transfer entry to the double-entry ledger.
     */
    public static function postTransfer(FinanceTransfer $transfer, User $user): FinanceJournalBatch
    {
        return DB::transaction(function () use ($transfer, $user) {
            $assetAccount = FinanceChartAccount::where('code', '1000')->first();
            if (!$assetAccount) {
                throw new Exception("Standard asset chart of account (1000) not found.");
            }

            $transfer->loadMissing(['fromAccount', 'toAccount']);

            $dioceseId = null;
            if ($transfer->church_id) {
                $dioceseId = DB::table('churches')->where('id', $transfer->church_id)->value('diocese_id');
            } else {
                $dioceseId = $user->default_diocese_id ?? 1;
            }

            // 1. Create Journal Batch
            $batch = FinanceJournalBatch::create([
                'diocese_id' => $dioceseId,
                'church_id' => $transfer->church_id,
                'batch_date' => $transfer->transfer_date,
                'reference' => $transfer->reference,
                'source' => 'transfer',
                'source_id' => $transfer->id,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // 2. Post Credit (Decrease from fromAccount)
            FinanceLedgerEntry::create([
                'journal_batch_id' => $batch->id,
                'chart_account_id' => $assetAccount->id,
                'entry_date' => $transfer->transfer_date,
                'debit' => 0.00,
                'credit' => $transfer->amount,
                'description' => "Transfer OUT from " . ($transfer->fromAccount ? $transfer->fromAccount->name : 'Account'),
            ]);

            // 3. Post Debit (Increase to toAccount)
            FinanceLedgerEntry::create([
                'journal_batch_id' => $batch->id,
                'chart_account_id' => $assetAccount->id,
                'entry_date' => $transfer->transfer_date,
                'debit' => $transfer->amount,
                'credit' => 0.00,
                'description' => "Transfer IN to " . ($transfer->toAccount ? $transfer->toAccount->name : 'Account'),
            ]);

            // 4. Verify Double-Entry Balance
            self::verifyBatchBalance($batch);

            return $batch;
        });
    }

    /**
     * Post a reversal batch for a cancelled receipt.
     */
    public static function reverseReceipt(\App\Models\FinanceReceipt $receipt, User $user): FinanceJournalBatch
    {
        return DB::transaction(function () use ($receipt, $user) {
            $receipt->loadMissing(['incomeHeader.moneyAccount', 'lines.incomeHead']);

            $header = $receipt->incomeHeader;
            if (!$header) {
                throw new Exception("Cannot reverse receipt without an income header.");
            }

            $churchId = $header->church_id;
            $dioceseId = null;
            if ($churchId) {
                $dioceseId = DB::table('churches')->where('id', $churchId)->value('diocese_id');
            } else {
                $dioceseId = $user->default_diocese_id ?? 1;
            }

            // Find Asset Chart Account (default 1000)
            $assetAccount = FinanceChartAccount::where('code', '1000')->first();
            if (!$assetAccount) {
                throw new Exception("Standard asset chart of account (1000) not found.");
            }

            // 1. Create Journal Batch
            $batch = FinanceJournalBatch::create([
                'diocese_id' => $dioceseId,
                'church_id' => $churchId,
                'batch_date' => date('Y-m-d'),
                'reference' => 'REV-' . $receipt->receipt_number,
                'source' => 'receipt_cancellation',
                'source_id' => $receipt->id,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // 2. Post Credit Entry (reverses original Debit to Asset)
            FinanceLedgerEntry::create([
                'journal_batch_id' => $batch->id,
                'chart_account_id' => $assetAccount->id,
                'entry_date' => date('Y-m-d'),
                'debit' => 0.00,
                'credit' => $receipt->total_amount,
                'description' => "Reversal of receipt {$receipt->receipt_number} due to cancellation",
            ]);

            // 3. Post Debit Entries (reverses original Credits to Revenue)
            foreach ($receipt->lines as $line) {
                $chartAccountId = $line->incomeHead ? $line->incomeHead->chart_account_id : null;
                if (!$chartAccountId) {
                    $revenueAccount = FinanceChartAccount::where('code', '4000')->first();
                    $chartAccountId = $revenueAccount ? $revenueAccount->id : null;
                }

                if (!$chartAccountId) {
                    throw new Exception("Revenue chart account not resolved for line ID {$line->id}.");
                }

                FinanceLedgerEntry::create([
                    'journal_batch_id' => $batch->id,
                    'chart_account_id' => $chartAccountId,
                    'fund_class_id' => null,
                    'programme_account_id' => null,
                    'entry_date' => date('Y-m-d'),
                    'debit' => $line->amount,
                    'credit' => 0.00,
                    'description' => "Reversal of revenue: " . ($line->description ?: 'Receipt Line Item'),
                ]);
            }

            // 4. Verify Double-Entry Balance
            self::verifyBatchBalance($batch);

            return $batch;
        });
    }

    /**
     * Strict verification rule: total debit must equal total credit for any posted journal batch.
     */
    private static function verifyBatchBalance(FinanceJournalBatch $batch): void
    {
        $debits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('debit');
        $credits = FinanceLedgerEntry::where('journal_batch_id', $batch->id)->sum('credit');

        // Allow minor decimal precision variance of 0.0001
        if (abs((float)$debits - (float)$credits) > 0.01) {
            throw new Exception("Journal batch {$batch->id} is out of balance. Debits: {$debits}, Credits: {$credits}");
        }
    }
}
