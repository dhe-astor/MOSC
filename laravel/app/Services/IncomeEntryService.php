<?php

namespace App\Services;

use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\ReceiptGenerationService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class IncomeEntryService
{
    /**
     * Create a new multi-line income entry (draft).
     */
    public static function createIncome(array $headerData, array $linesData, User $user): FinanceIncomeHeader
    {
        return DB::transaction(function () use ($headerData, $linesData, $user) {
            $headerData['created_by'] = $user->id;
            $headerData['status'] = 'draft';

            $header = FinanceIncomeHeader::create($headerData);

            $totalAmount = 0.0;
            foreach ($linesData as $lineData) {
                $lineData['income_header_id'] = $header->id;
                FinanceIncomeLine::create($lineData);
                $totalAmount += (float)$lineData['amount'];
            }

            AuditLogService::log(
                'Finance',
                'Income Header Created',
                "Created income entry date {$header->income_date} total amount {$totalAmount}",
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
     * Update an existing draft income entry.
     */
    public static function updateIncome(int $id, array $headerData, array $linesData, User $user): FinanceIncomeHeader
    {
        return DB::transaction(function () use ($id, $headerData, $linesData, $user) {
            $header = FinanceIncomeHeader::findOrFail($id);

            if ($header->status !== 'draft') {
                throw new Exception("Only draft income entries can be updated.");
            }

            $oldValues = $header->load('lines')->toArray();

            $header->update($headerData);

            // Delete old lines and recreate
            $header->lines()->delete();

            $totalAmount = 0.0;
            foreach ($linesData as $lineData) {
                $lineData['income_header_id'] = $header->id;
                FinanceIncomeLine::create($lineData);
                $totalAmount += (float)$lineData['amount'];
            }

            $header->load('lines');

            AuditLogService::log(
                'Finance',
                'Income Header Updated',
                "Updated income entry ID {$header->id} total amount {$totalAmount}",
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
     * Confirm income entry, post to ledger, and generate receipts.
     */
    public static function confirmIncome(int $id, User $user): FinanceIncomeHeader
    {
        return DB::transaction(function () use ($id, $user) {
            $header = FinanceIncomeHeader::with('lines.incomeHead', 'church')->findOrFail($id);

            if ($header->status === 'confirmed') {
                throw new Exception("This income entry is already confirmed.");
            }

            $oldValues = $header->toArray();

            // 1. Post to double-entry ledger
            LedgerPostingService::postIncomeHeader($header, $user);

            // 2. Update header status
            $header->update(['status' => 'confirmed']);

            // 3. Generate receipts for each line
            foreach ($header->lines as $line) {
                ReceiptService::generateReceiptForIncomeLine($header, $line, $user);
            }

            AuditLogService::log(
                'Finance',
                'Income Confirmed',
                "Confirmed and posted income entry ID {$header->id}",
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
