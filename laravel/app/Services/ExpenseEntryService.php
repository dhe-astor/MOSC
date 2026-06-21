<?php

namespace App\Services;

use App\Models\FinanceExpenseHeader;
use App\Models\FinanceExpenseLine;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseEntryService
{
    /**
     * Create a new draft expense entry.
     */
    public static function createExpense(array $headerData, array $linesData, ?UploadedFile $billFile, User $user): FinanceExpenseHeader
    {
        return DB::transaction(function () use ($headerData, $linesData, $billFile, $user) {
            $headerData['created_by'] = $user->id;
            $headerData['status'] = 'draft';

            // Generate voucher number if not set
            if (empty($headerData['voucher_number'])) {
                $headerData['voucher_number'] = 'VOUCH-' . time() . '-' . rand(100, 999);
            }

            if ($billFile) {
                $path = $billFile->store('bills', 'private');
                $headerData['reference_no'] = $path; // store path in reference/bill_path
            }

            $header = FinanceExpenseHeader::create($headerData);

            $totalAmount = 0.0;
            foreach ($linesData as $lineData) {
                $lineData['expense_header_id'] = $header->id;
                FinanceExpenseLine::create($lineData);
                $totalAmount += (float)$lineData['amount'];
            }

            AuditLogService::log(
                'Finance',
                'Expense Header Created',
                "Created expense voucher {$header->voucher_number} total amount {$totalAmount}",
                null,
                $header->load('lines')->toArray(),
                $header,
                $header->church_id,
                $user->default_diocese_id ?? 1
            );

            return $header;
        });
    }

    /**
     * Update a draft expense entry.
     */
    public static function updateExpense(int $id, array $headerData, array $linesData, ?UploadedFile $billFile, User $user): FinanceExpenseHeader
    {
        return DB::transaction(function () use ($id, $headerData, $linesData, $billFile, $user) {
            $header = FinanceExpenseHeader::findOrFail($id);

            if ($header->status !== 'draft') {
                throw new Exception("Only draft expense entries can be updated.");
            }

            $oldValues = $header->load('lines')->toArray();

            if ($billFile) {
                // Delete old bill if exists
                if ($header->reference_no && Storage::disk('private')->exists($header->reference_no)) {
                    Storage::disk('private')->delete($header->reference_no);
                }
                $path = $billFile->store('bills', 'private');
                $headerData['reference_no'] = $path;
            }

            $header->update($headerData);

            // Recreate lines
            $header->lines()->delete();

            $totalAmount = 0.0;
            foreach ($linesData as $lineData) {
                $lineData['expense_header_id'] = $header->id;
                FinanceExpenseLine::create($lineData);
                $totalAmount += (float)$lineData['amount'];
            }

            $header->load('lines');

            AuditLogService::log(
                'Finance',
                'Expense Header Updated',
                "Updated expense voucher ID {$header->id} total amount {$totalAmount}",
                $oldValues,
                $header->toArray(),
                $header,
                $header->church_id,
                $user->default_diocese_id ?? 1
            );

            return $header;
        });
    }

    /**
     * Pay/Confirm an expense entry and post to the ledger.
     */
    public static function payExpense(int $id, User $user): FinanceExpenseHeader
    {
        return DB::transaction(function () use ($id, $user) {
            $header = FinanceExpenseHeader::with('lines.expenseHead')->findOrFail($id);

            if ($header->status === 'paid') {
                throw new Exception("This expense entry is already marked as paid.");
            }

            $oldValues = $header->toArray();

            // 1. Post to double-entry ledger
            LedgerPostingService::postExpenseHeader($header, $user);

            // 2. Update status
            $header->update(['status' => 'paid']);

            AuditLogService::log(
                'Finance',
                'Expense Paid',
                "Confirmed and posted expense voucher {$header->voucher_number}",
                $oldValues,
                $header->toArray(),
                $header,
                $header->church_id,
                $user->default_diocese_id ?? 1
            );

            return $header;
        });
    }
}
