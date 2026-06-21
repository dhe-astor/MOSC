<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\Donation;
use App\Models\IncomeRecord;
use App\Models\User;
use App\Services\ReceiptNumberService;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Exception;

class ReceiptGenerationService
{
    /**
     * Generate receipt for a given Donation or IncomeRecord
     */
    public static function generateReceipt(Model $record, User $user): Receipt
    {
        // 1. Verify instance type
        if (!($record instanceof Donation) && !($record instanceof IncomeRecord)) {
            throw new Exception("Invalid record type for receipt generation.");
        }

        // 2. Validate receipt status constraints
        if ($record instanceof Donation) {
            if ($record->status !== 'received') {
                throw new Exception("Donation must be in 'received' status to generate a receipt.");
            }
        } else {
            if ($record->status !== 'received' && $record->status !== 'approved') {
                throw new Exception("Income record must be in 'received' or 'approved' status to generate a receipt.");
            }
        }

        // 3. Prevent duplicate receipt generation
        if ($record->receipt_id) {
            throw new Exception("A receipt has already been generated for this record.");
        }

        // 4. Gather metadata for the receipt
        $diocese = $record->diocese;
        if (!$diocese) {
            throw new Exception("Diocese details not found.");
        }
        $record->loadMissing(['church', 'family', 'member', 'category']);

        $payerName = '';
        $payerEmail = null;
        $payerPhone = null;
        $description = '';
        $receiptType = 'manual';

        if ($record instanceof Donation) {
            $payerName = $record->donor_name;
            $payerEmail = $record->donor_email;
            $payerPhone = $record->donor_phone;
            $description = $record->notes ?? ("Donation: " . ($record->category?->name ?? 'General'));
            $receiptType = 'donation';
        } else {
            // IncomeRecord
            if ($record->member) {
                $payerName = $record->member->full_name;
                $payerEmail = $record->member->email;
                $payerPhone = $record->member->primary_phone;
            } elseif ($record->family) {
                $payerName = $record->family->family_name . ' Family';
                $payerEmail = $record->family->email;
                $payerPhone = $record->family->primary_phone;
            } else {
                $payerName = 'General Contribution';
            }
            $description = $record->description ?? $record->title;

            if ($record->source_type === 'course_registration') {
                $receiptType = 'course_fee';
            } elseif ($record->source_type === 'event_registration') {
                $receiptType = 'event_fee';
            } else {
                $receiptType = 'income';
            }
        }

        // 5. Generate Receipt Number using row locking
        $receiptNumber = ReceiptNumberService::generateNextNumber($record->diocese_id, $record->church_id);

        // 6. Renders receipt layout PDF using Dompdf
        $pdfData = [
            'diocese_name' => $diocese->name,
            'church_name' => $record->church ? $record->church->name : 'MSOC Europe Consolidated',
            'church_address' => $record->church ? $record->church->address : 'Vienna, Austria',
            'receipt_number' => $receiptNumber,
            'receipt_date' => date('Y-m-d'),
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
            'description' => $description,
            'payment_reference' => $record->payment_reference,
            'currency' => $record->currency,
            'amount' => $record->amount,
            'payment_method' => $record->payment_method,
            'issued_by' => $user->name,
        ];

        $pdf = Pdf::loadView('pdf.receipt', $pdfData);
        $pdfContent = $pdf->output();

        // 7. Save PDF in private storage
        $pdfPath = "private/receipts/{$receiptNumber}.pdf";
        Storage::put($pdfPath, $pdfContent);

        // 8. Create receipt record
        $receipt = Receipt::create([
            'diocese_id' => $record->diocese_id,
            'church_id' => $record->church_id,
            'receipt_number' => $receiptNumber,
            'receipt_type' => $receiptType,
            'receiptable_type' => get_class($record),
            'receiptable_id' => $record->id,
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
            'family_id' => $record->family_id,
            'member_id' => $record->member_id,
            'amount' => $record->amount,
            'currency' => $record->currency,
            'payment_method' => $record->payment_method,
            'payment_reference' => $record->payment_reference,
            'receipt_date' => date('Y-m-d'),
            'description' => $description,
            'pdf_path' => $pdfPath,
            'issued_by' => $user->id,
            'status' => 'issued',
            'metadata' => [
                'generated_by_user_id' => $user->id,
                'source_type' => get_class($record),
                'source_id' => $record->id
            ]
        ]);

        // 9. Link receipt back to source record
        $record->update(['receipt_id' => $receipt->id]);

        // 10. Audit Logging
        AuditLogService::log(
            'Finance',
            'Receipt Generated',
            "Generated receipt {$receiptNumber} for " . strtolower(class_basename($record)),
            null,
            $receipt->toArray(),
            $receipt,
            $record->church_id,
            $record->diocese_id
        );

        // Trigger notification
        \App\Services\NotificationTriggerService::triggerReceiptGenerated($receipt);

        return $receipt;
    }
}
