<?php

namespace App\Services;

use App\Models\FinanceBankStatementImport;
use App\Models\FinanceBankStatementLine;
use App\Models\FinanceBankMatch;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceTransfer;
use App\Models\User;
use App\Services\IncomeEntryService;
use App\Services\ExpenseEntryService;
use App\Services\FinanceTransferService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    /**
     * Import a bank statement file.
     */
    public static function importStatement(int $moneyAccountId, UploadedFile $file, User $user): FinanceBankStatementImport
    {
        return DB::transaction(function () use ($moneyAccountId, $file, $user) {
            // 1. Create Import Header
            $import = FinanceBankStatementImport::create([
                'money_account_id' => $moneyAccountId,
                'import_date' => date('Y-m-d'),
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => $user->id,
            ]);

            // 2. Parse CSV
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                throw new Exception("Unable to read uploaded statement file.");
            }

            // Read header row
            $header = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 3) {
                    continue;
                }

                // Expecting standard format: Booking Date, Partner Name, Description, Amount
                $bookingDate = date('Y-m-d', strtotime($row[0] ?? now()->toDateString()));
                $partnerName = $row[1] ?? '';
                $description = $row[2] ?? '';
                $amount = (float)($row[3] ?? 0.0);

                FinanceBankStatementLine::create([
                    'bank_statement_import_id' => $import->id,
                    'booking_date' => $bookingDate,
                    'value_date' => $bookingDate,
                    'partner_name' => $partnerName,
                    'description' => $description,
                    'amount' => $amount,
                    'is_matched' => false,
                ]);
            }

            fclose($handle);

            AuditLogService::log(
                'Finance',
                'Bank Statement Imported',
                "Imported bank statement file {$import->file_name}",
                null,
                $import->toArray(),
                $import,
                null,
                $user->default_diocese_id ?? 1
            );

            return $import;
        });
    }

    /**
     * Match a statement line to an income, expense, or transfer voucher.
     */
    public static function matchStatementLine(int $lineId, string $matchableType, int $matchableId, User $user): FinanceBankMatch
    {
        return DB::transaction(function () use ($lineId, $matchableType, $matchableId, $user) {
            $line = FinanceBankStatementLine::findOrFail($lineId);

            if ($line->is_matched) {
                throw new Exception("Statement line is already matched.");
            }

            // 1. Resolve matchable entity and confirm/post if draft
            if ($matchableType === 'App\Models\FinanceIncomeHeader') {
                $entity = FinanceIncomeHeader::findOrFail($matchableId);
                if ($entity->status === 'draft') {
                    IncomeEntryService::confirmIncome($entity->id, $user);
                }
            } elseif ($matchableType === 'App\Models\FinanceExpenseHeader') {
                $entity = FinanceExpenseHeader::findOrFail($matchableId);
                if ($entity->status === 'draft') {
                    ExpenseEntryService::payExpense($entity->id, $user);
                }
            } elseif ($matchableType === 'App\Models\FinanceTransfer') {
                $entity = FinanceTransfer::findOrFail($matchableId);
                if ($entity->status === 'draft') {
                    FinanceTransferService::confirmTransfer($entity->id, $user);
                }
            } else {
                throw new Exception("Invalid matchable entity type.");
            }

            // 2. Create Match record
            $match = FinanceBankMatch::create([
                'bank_statement_line_id' => $lineId,
                'matchable_type' => $matchableType,
                'matchable_id' => $matchableId,
                'matched_by' => $user->id,
            ]);

            // 3. Mark line as matched
            $line->update(['is_matched' => true]);

            AuditLogService::log(
                'Finance',
                'Bank Transaction Matched',
                "Matched bank statement line ID {$lineId} to {$matchableType} ID {$matchableId}",
                null,
                $match->toArray(),
                $match,
                null,
                $user->default_diocese_id ?? 1
            );

            return $match;
        });
    }
}
