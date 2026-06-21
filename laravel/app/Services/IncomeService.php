<?php

namespace App\Services;

use App\Models\IncomeRecord;
use App\Models\User;
use App\Services\ReceiptGenerationService;
use App\Services\AuditLogService;
use App\Services\FinanceApprovalService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IncomeService
{
    /**
     * Create a new income record.
     */
    public static function createIncome(array $data, User $user): IncomeRecord
    {
        return DB::transaction(function () use ($data, $user) {
            $data['created_by'] = $user->id;
            
            // Check for duplicate registration link
            if (!empty($data['source_type']) && !empty($data['source_id'])) {
                $exists = IncomeRecord::where('source_type', $data['source_type'])
                    ->where('source_id', $data['source_id'])
                    ->exists();
                if ($exists) {
                    throw new Exception("An income record already exists for this registration.");
                }
            }

            if (!isset($data['status'])) {
                $data['status'] = 'draft';
            }

            $income = IncomeRecord::create($data);

            AuditLogService::log(
                'Finance',
                'Income Record Created',
                "Created income record '{$income->title}' of amount {$income->amount}",
                null,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            return $income;
        });
    }

    /**
     * Submit income record for approval.
     */
    public static function submitIncome(IncomeRecord $income, User $user): IncomeRecord
    {
        if ($income->status !== 'draft') {
            throw new Exception("Only draft income records can be submitted.");
        }

        return DB::transaction(function () use ($income, $user) {
            $oldValues = $income->toArray();

            $income->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
            ]);

            AuditLogService::log(
                'Finance',
                'Income Submitted',
                "Submitted income ID {$income->id} for approval",
                $oldValues,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            // Create finance approval request
            FinanceApprovalService::createApprovalRequest($income, 'income_approval', $user);

            return $income;
        });
    }

    /**
     * Approve income record.
     */
    public static function approveIncome(IncomeRecord $income, ?string $remarks, User $user): IncomeRecord
    {
        if ($income->status !== 'submitted') {
            throw new Exception("Only submitted income records can be approved.");
        }

        return DB::transaction(function () use ($income, $remarks, $user) {
            $oldValues = $income->toArray();

            $income->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            AuditLogService::log(
                'Finance',
                'Income Approved',
                "Approved income ID {$income->id}",
                $oldValues,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            // Update associated finance approval request
            FinanceApprovalService::resolveApprovalRequest($income, 'approved', $remarks, null, $user);

            return $income;
        });
    }

    /**
     * Reject income record.
     */
    public static function rejectIncome(IncomeRecord $income, string $reason, User $user): IncomeRecord
    {
        if ($income->status !== 'submitted') {
            throw new Exception("Only submitted income records can be rejected.");
        }

        return DB::transaction(function () use ($income, $reason, $user) {
            $oldValues = $income->toArray();

            $income->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            AuditLogService::log(
                'Finance',
                'Income Rejected',
                "Rejected income ID {$income->id}",
                $oldValues,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            // Update associated finance approval request
            FinanceApprovalService::resolveApprovalRequest($income, 'rejected', null, $reason, $user);

            return $income;
        });
    }

    /**
     * Mark income record as received and generate receipt.
     */
    public static function markReceived(IncomeRecord $income, User $user): IncomeRecord
    {
        return DB::transaction(function () use ($income, $user) {
            $oldValues = $income->toArray();

            $updateData = [
                'status' => 'received',
            ];

            if (!$income->approved_at) {
                $updateData['approved_by'] = $user->id;
                $updateData['approved_at'] = now();
            }

            $income->update($updateData);

            AuditLogService::log(
                'Finance',
                'Income Received',
                "Marked income ID {$income->id} as received",
                $oldValues,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            // Generate Receipt
            ReceiptGenerationService::generateReceipt($income, $user);

            return $income;
        });
    }

    /**
     * Connect/link a course or event registration manual payment to finance.
     */
    public static function linkRegistrationPayment(string $sourceType, Model $registration, float $amount, string $paymentMethod, ?string $paymentReference, User $user): IncomeRecord
    {
        if (!in_array($sourceType, ['course_registration', 'event_registration'])) {
            throw new Exception("Invalid registration source type.");
        }

        $paymentStatus = $registration->payment_status; 
        if ($paymentStatus !== 'paid' && $paymentStatus !== 'waived') {
            throw new Exception("Registration payment status must be 'paid' or 'waived' to generate income record.");
        }

        // Check if duplicate already exists
        $exists = IncomeRecord::where('source_type', $sourceType)
            ->where('source_id', $registration->id)
            ->exists();
        if ($exists) {
            throw new Exception("An income record has already been linked to this registration.");
        }

        // If waived, amount is 0
        $finalAmount = ($paymentStatus === 'waived') ? 0.00 : $amount;

        // Lookup category
        $categoryName = ($sourceType === 'course_registration') ? 'Course Fee' : 'Event Fee';
        $category = \App\Models\FinanceCategory::where('name', $categoryName)->first();

        $title = ($sourceType === 'course_registration') 
            ? "Course Fee: " . ($registration->batch?->course?->name ?? 'Course') 
            : "Event Fee: " . ($registration->event?->title ?? 'Event');

        $incomeData = [
            'diocese_id' => $registration->diocese_id ?? $user->default_diocese_id ?? 1,
            'church_id' => $registration->church_id ?? $registration->member?->church_id ?? $registration->family?->church_id ?? null,
            'finance_category_id' => $category?->id,
            'source_type' => $sourceType,
            'source_id' => $registration->id,
            'family_id' => $registration->family_id,
            'member_id' => $registration->member_id,
            'title' => $title,
            'description' => "Linked manual payment from " . str_replace('_', ' ', $sourceType),
            'amount' => $finalAmount,
            'currency' => 'EUR',
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'income_date' => date('Y-m-d'),
            'status' => 'received', 
        ];

        $income = self::createIncome($incomeData, $user);

        // Generate receipt
        ReceiptGenerationService::generateReceipt($income, $user);

        // Audit Log
        AuditLogService::log(
            'Finance',
            'Course/Event Linked',
            "Linked {$sourceType} ID {$registration->id} to income record",
            null,
            $income->toArray(),
            $income,
            $income->church_id,
            $income->diocese_id
        );

        return $income;
    }
}
