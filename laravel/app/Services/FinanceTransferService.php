<?php

namespace App\Services;

use App\Models\FinanceTransfer;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class FinanceTransferService
{
    /**
     * Create a new draft transfer.
     */
    public static function createTransfer(array $data, User $user): FinanceTransfer
    {
        $data['created_by'] = $user->id;
        $data['status'] = 'draft';

        if ($data['from_account_id'] == $data['to_account_id']) {
            throw new Exception("Source and destination money accounts cannot be the same.");
        }

        $transfer = FinanceTransfer::create($data);

        AuditLogService::log(
            'Finance',
            'Transfer Created',
            "Created money transfer draft ID {$transfer->id} amount {$transfer->amount}",
            null,
            $transfer->toArray(),
            $transfer,
            $transfer->church_id,
            $user->default_diocese_id ?? 1
        );

        return $transfer;
    }

    /**
     * Confirm a transfer and post to the double-entry ledger.
     */
    public static function confirmTransfer(int $id, User $user): FinanceTransfer
    {
        return DB::transaction(function () use ($id, $user) {
            $transfer = FinanceTransfer::findOrFail($id);

            if ($transfer->status === 'confirmed') {
                throw new Exception("This transfer is already confirmed.");
            }

            $oldValues = $transfer->toArray();

            // 1. Post to double-entry ledger
            LedgerPostingService::postTransfer($transfer, $user);

            // 2. Mark transfer as confirmed
            $transfer->update(['status' => 'confirmed']);

            AuditLogService::log(
                'Finance',
                'Transfer Confirmed',
                "Confirmed and posted money transfer ID {$transfer->id}",
                $oldValues,
                $transfer->toArray(),
                $transfer,
                $transfer->church_id,
                $user->default_diocese_id ?? 1
            );

            return $transfer;
        });
    }
}
