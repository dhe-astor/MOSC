<?php

namespace App\Services;

use App\Models\FinanceCashBatch;
use App\Models\FinanceIncomeHeader;
use App\Models\User;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class CashBatchService
{
    /**
     * Open a new cash batch.
     */
    public static function openBatch(int $churchId, int $moneyAccountId, User $user): FinanceCashBatch
    {
        // Enforce only one open batch per cash account
        $exists = FinanceCashBatch::where('money_account_id', $moneyAccountId)
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            throw new Exception("There is already an open cash batch for this money account.");
        }

        $batch = FinanceCashBatch::create([
            'church_id' => $churchId,
            'money_account_id' => $moneyAccountId,
            'opened_at' => now(),
            'opened_by' => $user->id,
            'status' => 'open',
            'declared_amount' => 0.00,
            'system_amount' => 0.00,
            'difference' => 0.00,
        ]);

        AuditLogService::log(
            'Finance',
            'Cash Batch Opened',
            "Opened cash batch ID {$batch->id} for money account {$moneyAccountId}",
            null,
            $batch->toArray(),
            $batch,
            $churchId,
            $user->default_diocese_id ?? 1
        );

        return $batch;
    }

    /**
     * Close a cash batch and perform reconciliation.
     */
    public static function closeBatch(int $id, array $countingDetails, float $declaredAmount, User $user): FinanceCashBatch
    {
        return DB::transaction(function () use ($id, $countingDetails, $declaredAmount, $user) {
            $batch = FinanceCashBatch::findOrFail($id);

            if ($batch->status !== 'open') {
                throw new Exception("Only open cash batches can be closed.");
            }

            $oldValues = $batch->toArray();
            $closedAt = now();

            // Calculate system computed amount:
            // Sum of all confirmed cash income entries recorded for this account since the batch opened
            $systemAmount = DB::table('finance_income_headers')
                ->join('finance_income_lines', 'finance_income_headers.id', '=', 'finance_income_lines.income_header_id')
                ->where('finance_income_headers.money_account_id', $batch->money_account_id)
                ->where('finance_income_headers.status', 'confirmed')
                ->whereBetween('finance_income_headers.created_at', [$batch->opened_at, $closedAt])
                ->sum('finance_income_lines.amount');

            $systemAmount = (float)$systemAmount;
            $difference = $declaredAmount - $systemAmount;

            $batch->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
                'closed_by' => $user->id,
                'counting_details' => $countingDetails,
                'declared_amount' => $declaredAmount,
                'system_amount' => $systemAmount,
                'difference' => $difference,
            ]);

            AuditLogService::log(
                'Finance',
                'Cash Batch Closed',
                "Closed cash batch ID {$batch->id}. Declared: {$declaredAmount}, System: {$systemAmount}, Diff: {$difference}",
                $oldValues,
                $batch->toArray(),
                $batch,
                $batch->church_id,
                $user->default_diocese_id ?? 1
            );

            return $batch;
        });
    }
}
