<?php

namespace App\Services;

use App\Models\ExpenseRecord;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FinanceApprovalService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ExpenseService
{
    /**
     * Create a new expense record.
     */
    public static function createExpense(array $data, ?UploadedFile $billFile, User $user): ExpenseRecord
    {
        return DB::transaction(function () use ($data, $billFile, $user) {
            $data['created_by'] = $user->id;

            if (!isset($data['status'])) {
                $data['status'] = 'draft';
            }

            if ($billFile) {
                $churchDir = $data['church_id'] ?? 'diocese';
                $filename = uniqid() . '_' . $billFile->getClientOriginalName();
                $billPath = "private/bills/{$churchDir}/{$filename}";
                
                Storage::put($billPath, file_get_contents($billFile));
                $data['bill_path'] = $billPath;

                // Log bill upload
                AuditLogService::log(
                    'Finance',
                    'Bill Uploaded',
                    "Uploaded bill document to {$billPath}",
                    null,
                    ['bill_path' => $billPath],
                    null,
                    $data['church_id'] ?? null,
                    $data['diocese_id'] ?? null
                );
            }

            $expense = ExpenseRecord::create($data);

            AuditLogService::log(
                'Finance',
                'Expense Created',
                "Created expense '{$expense->title}' of amount {$expense->amount}",
                null,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            return $expense;
        });
    }

    /**
     * Submit expense for approval.
     */
    public static function submitExpense(ExpenseRecord $expense, User $user): ExpenseRecord
    {
        if ($expense->status !== 'draft') {
            throw new Exception("Only draft expenses can be submitted.");
        }

        return DB::transaction(function () use ($expense, $user) {
            $oldValues = $expense->toArray();

            $expense->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
            ]);

            AuditLogService::log(
                'Finance',
                'Expense Submitted',
                "Submitted expense ID {$expense->id} for approval",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            // Create approval request
            FinanceApprovalService::createApprovalRequest($expense, 'expense_approval', $user);

            // Trigger notification
            \App\Services\NotificationTriggerService::triggerFinanceExpenseApprovalRequested($expense);

            return $expense;
        });
    }

    /**
     * Approve expense.
     */
    public static function approveExpense(ExpenseRecord $expense, ?string $remarks, User $user): ExpenseRecord
    {
        if ($expense->status !== 'submitted') {
            throw new Exception("Only submitted expenses can be approved.");
        }

        return DB::transaction(function () use ($expense, $remarks, $user) {
            $oldValues = $expense->toArray();

            $expense->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            AuditLogService::log(
                'Finance',
                'Expense Approved',
                "Approved expense ID {$expense->id}",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            // Resolve approval request
            FinanceApprovalService::resolveApprovalRequest($expense, 'approved', $remarks, null, $user);

            // Trigger notification
            \App\Services\NotificationTriggerService::triggerFinanceExpenseApproved($expense);

            return $expense;
        });
    }

    /**
     * Reject expense.
     */
    public static function rejectExpense(ExpenseRecord $expense, string $reason, User $user): ExpenseRecord
    {
        if ($expense->status !== 'submitted') {
            throw new Exception("Only submitted expenses can be rejected.");
        }

        return DB::transaction(function () use ($expense, $reason, $user) {
            $oldValues = $expense->toArray();

            $expense->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            AuditLogService::log(
                'Finance',
                'Expense Rejected',
                "Rejected expense ID {$expense->id}",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            // Resolve approval request
            FinanceApprovalService::resolveApprovalRequest($expense, 'rejected', null, $reason, $user);

            // Trigger notification
            \App\Services\NotificationTriggerService::triggerFinanceExpenseRejected($expense, $reason);

            return $expense;
        });
    }

    /**
     * Mark expense as paid.
     */
    public static function markPaid(ExpenseRecord $expense, User $user): ExpenseRecord
    {
        if ($expense->status !== 'approved') {
            throw new Exception("Only approved expenses can be marked as paid.");
        }

        return DB::transaction(function () use ($expense, $user) {
            $oldValues = $expense->toArray();

            $expense->update([
                'status' => 'paid',
                'paid_by' => $user->id,
                'paid_at' => now(),
            ]);

            AuditLogService::log(
                'Finance',
                'Expense Paid',
                "Marked expense ID {$expense->id} as paid",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            return $expense;
        });
    }

    /**
     * Cancel expense.
     */
    public static function cancelExpense(ExpenseRecord $expense, User $user): ExpenseRecord
    {
        if (in_array($expense->status, ['paid', 'cancelled'])) {
            throw new Exception("Expense is already paid or cancelled.");
        }

        return DB::transaction(function () use ($expense, $user) {
            $oldValues = $expense->toArray();

            $expense->update([
                'status' => 'cancelled',
            ]);

            AuditLogService::log(
                'Finance',
                'Expense Cancelled',
                "Cancelled expense ID {$expense->id}",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            // If there's a pending approval, cancel it
            FinanceApprovalService::resolveApprovalRequest($expense, 'cancelled', 'Expense cancelled', null, $user);

            return $expense;
        });
    }
}
